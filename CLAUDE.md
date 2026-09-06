# CLAUDE.md

Guía para Claude Code al trabajar en este repositorio (checkout **dev**, rama `dev`).

> Documentación en español por convención del proyecto (igual que statCoin / spoticlone). Nombres
> de clases, comandos y términos técnicos se dejan tal cual.

## 🧠 Brain

Spec y contexto del proyecto en el segundo cerebro:
`/home/david/Documents/workspace/Brain/03 - Proyectos/LibraryVue.md`. Léela para el panorama
(decisiones, estado, roadmap); este `CLAUDE.md` cubre el detalle técnico del repo.

## Qué es libraryVue

App web + móvil para **gestionar una biblioteca personal multimedia**: libros, películas, juegos,
álbumes de música y vídeos, con búsqueda contra APIs externas, fichas, estadísticas y un feed
social. Es el proyecto **base** del que derivan statCoin, trackit, spoticlone y galleryVue (mismo
patrón: endpoint único + router de acciones + middleware declarativo).

Stack: **Docker · PHP 8.2 (clean architecture / hexagonal, PHP-DI 7) · Vue 3.5 (Vue CLI) + Pinia +
PrimeVue + Chart.js · Capacitor 8 (Android) · MySQL 8**.

## Repos dev vs. prod

Hay **dos checkouts del mismo repositorio Git** en el workspace:

| | `libraryVue` (este) | `libraryVue_prod` |
|---|---|---|
| Rama | `dev` | `master` |
| Compose | `docker-compose.yml` | `docker-compose.prod.yml` |
| Frontend | `:8080` (vue-cli serve) | `:8082` (Nginx estático) |
| Backend | `:8888` → `:80` | interno `:80` (no expuesto) |
| MySQL | `:3308` (db `library_db`) | `:3307` (db `library_db_prod`) |
| API URL | `http://127.0.0.1:8888/index.php` | `https://library.dcahomelab.com/api` |
| Extras | dev deps, sin CORS | `composer --no-dev`, red `library_network`, CORS a `library.dcahomelab.com`, contenedores `libraryvue-*-prod` |

Producción se publica en **https://library.dcahomelab.com** vía **Cloudflare Tunnel**.

Pero los stacks son **tres**, no dos: el mirror de catálogos tiene el suyo
(`docker-compose.mirror.yml`, proyecto `libraryvue-mirror`, MySQL en `:3313`) y **lo comparten
dev y producción**. No es una optimización: son 2,2 GB de catálogo público y dos cachés con
cuota ajena (TMDB, MusicBrainz), y duplicarlo por entorno costaría el doble de todo para servir
exactamente los mismos datos. Detalle abajo, en *Mirror local de catálogos*.

## Comandos

Todo corre en Docker; no se ejecuta PHP/Node en el host.

```bash
cp .env.example .env        # claves de APIs externas + DB; ver "Variables de entorno"
./dev-setup.sh              # la vía recomendada: levanta, arranca el mirror y MIGRA
./dev-setup.sh --reset      # lo mismo, pero borrando volúmenes (se lleva la BD)
docker compose up --build   # equivalente crudo: NO migra ni arranca el mirror

# URLs (dev)
#   Frontend  http://localhost:8080
#   Backend   http://localhost:8888/index.php   (endpoint ÚNICO; la acción va en el body)
#   MySQL     localhost:3308  (db library_db)
#   Mirror    localhost:3313  (db library_mirror; stack aparte, compartido con prod)

# Mirror local de catálogos (stack propio, compartido dev + prod)
./mirror-sync.sh --bootstrap  # crea red, volúmenes y usuarios, y levanta el mirror
./mirror-sync.sh --status   # dump vigente, filas por tabla y estado de la caché TMDB
./mirror-sync.sh --imdb     # reimporta los dumps de IMDb (~760 MB, ~13 min)
./mirror-sync.sh --musicbrainz  # reimporta el dump de MusicBrainz (7,6 GB, ~26 min en frío)
./mirror-sync.sh --tracks   # baja de MusicBrainz las pistas que falten de tus álbumes
./mirror-sync.sh --purge    # caduca el catálogo ajeno: TMDB y el de Spotify en albums
./mirror-sync.sh --covers   # caduca la caché del catálogo y baja las portadas pendientes

# Tests backend (PHPUnit 11, dentro del contenedor backend)
docker compose --profile test up -d mysql-test   # lo necesita la suite de integración
docker compose exec backend composer test        # las DOS suites: 1472 tests
docker compose exec backend composer test:unit   # la rápida: 1279, sin necesitar mysql-test
docker compose exec backend composer test:integration   # 193, contra una BD desechable

# Tests frontend (Vitest 3, dentro del contenedor frontend)
docker compose exec frontend npm test            # 514 tests
docker compose exec frontend npm run test:watch
docker compose exec frontend npx vue-cli-service lint --no-fix   # lo corre también ./dev-setup.sh
docker compose exec frontend npm run lint:styles                 # stylelint; también en ./dev-setup.sh
docker compose exec frontend npm run build   # OBLIGATORIO si tocas SCSS: es lo ÚNICO que lo compila

# Barrera de desbordamiento horizontal — EN EL HOST, no en el contenedor (necesita Firefox +
# geckodriver, que no están en la imagen). Cero dependencias npm. Sale 1 si algo desborda O si
# una ruta no se pudo medir. El token por variable de entorno: en --jwt= quedaría en el historial.
cd frontend && LIBRARYVUE_JWT=<token> npm run test:responsive -- --width=360,390

# Frontend / móvil (Capacitor)
cd frontend && npm run cap:sync && npm run build:mobile
```

> ⚠️ **`docker compose exec` corre como ROOT, y eso puede tumbar el backend entero.** Si un
> `composer test` es lo primero que escribe logs en un día nuevo, los
> `storage/logs/*-YYYY-MM-DD.log` nacen `root:root` con modo 644 y Apache (`www-data`) ya no puede
> añadir: **toda** petición pasa a 500, `ping` incluido, y el frontend borra el JWT de
> `localStorage` al fallar `check_auth`, así que el síntoma que ves es «no puedo entrar en la app».
> Se arregla con `docker compose exec backend chown -R www-data:www-data storage/logs`.

> ⚠️ **Una devDependency nueva del frontend obliga a `docker compose build frontend`.** El
> contenedor monta `package.json`, `vitest.config.js`, `tests/` y `.stylelintrc.json`
> (`docker-compose.yml:15-24`), pero
> `node_modules` va horneado en la imagen (`docker/frontend/Dockerfile.frontend.dev:10,14`).
> Instalar en el host no llega al contenedor.

## Arquitectura

### Backend: endpoint único + router de acciones + middleware declarativo
- **Una sola URL** (`backend/public/index.php`), con **una única excepción**: `GET ?cover=` para
  las portadas locales (ver abajo). El cliente manda `POST` con JSON
  `{ "action": "...", ...payload }`. No hay rutas REST.
- `config/routes.php` mapea cada acción → `[Controller, método]` + pila de middleware
  (`Logging` → `Auth` → `CSRF` → `Validation`, más `Admin` en las dos rutas de LibraryX, que
  exigen `users.is_admin`). `ActionRouter` ejecuta la pila y despacha con un
  `match($action)`. **Añadir un endpoint = tocar `routes.php`, el `match`/`getController` de
  `ActionRouter`, y el método del controller.**
- Controllers en `backend/src/Controllers`: `Book`, `Movie`, `Game`, `Album`, `Video` (cada medio),
  `Library` / `LibraryX` (colección del usuario), `Feed` + `Social` (feed social), `List` (listas de
  medios), `Club` (clubs), `Journal` (el diario), `Stats`, `Auth`. Extienden `BaseController`
  (`successResponse`/`errorResponse`).
- Dominio: `src/Domain/**` con interfaces de repositorio (modelo Work/Edition) + ~40 use cases;
  persistencia `MySql*Repository` con PDO. Registro en `config/container.php` (interfaz → impl.).

### APIs externas
Búsqueda y enriquecimiento de fichas contra: **Google Books**, **Google OAuth**, **Spotify**,
**Last.fm**, **IGDB**, **TMDB** y **YouTube**. Cada una con su cliente en `src/Infrastructure`.
Las claves van en `.env` (ver abajo); la app degrada con elegancia si falta alguna.

**Ni películas ni álbumes se buscan ya contra una API**: los sirven los mirrors locales de abajo.
De Spotify solo quedan vivas tres acciones —pistas, artista y novedades—, cacheadas con TTL.

Las **pistas** de los álbumes del mirror ya no salen de Spotify: se piden a la API de MusicBrainz y
se cachean en `mb_track` (ver abajo).

- **🪤 IGDB IGNORA EN SILENCIO LOS CAMPOS QUE NO CONOCE, y eso ya ha costado dos defectos de meses.**
  Comprobado el 2026-09-01 pidiéndole un campo literalmente inventado: la respuesta fue **`success`**
  con el resto de datos correctos. No hay error, no hay aviso y no hay log — el campo deja de venir
  y lo que lo consume degrada a su rama por defecto. Así vivieron sin que nadie los viera
  `websites.category` (retirado: los quince enlaces externos de una ficha de juego se llamaban todos
  «Open link») y `age_ratings.category` (retirado: el badge de clasificación por edad **no se pintó
  nunca**). Los reemplazos son `websites.type.type` y
  `age_ratings.organization.name` + `age_ratings.rating_category.rating`, y son **referencias que hay
  que expandir**. Tres consecuencias prácticas: **(1)** una proyección de IGDB no se puede verificar
  leyéndola ni ejecutando la suite, solo consultando la API y mirando la respuesta; **(2)** al
  corregir una, **quita los campos muertos antes de probar los nuevos** — mientras conviven, IGDB
  devuelve la relación entera **solo con su `id`** y las expansiones válidas parecen no existir
  tampoco; **(3)** IGDB **renumeró** los tipos de website, así que reutilizar un mapa viejo contra
  los ids nuevos etiqueta Epic como «Google+» y GOG como «Tumblr»: un rótulo genérico se ignora, uno
  falso se cree. Lo único que lo defiende es
  `tests/Unit/Domain/Services/IGDBProjectionTest.php`, que fija el texto de las proyecciones.
- **Ningún servicio construye su propio cliente HTTP.** `Infrastructure/Http/HttpClientFactory` es la
  única fábrica, y `grep -rn "new Client(" backend/src` debe seguir devolviendo **solo** ese fichero.
  Da un `HandlerStack` con reintento en **dos perfiles**: `PROFILE_WEB` (2 intentos, 250 ms de
  backoff, tope 1 s) para lo que corre dentro de una petición, y `PROFILE_BATCH` (5 intentos,
  exponencial 1-16 s, tope 60 s) para `bin/mirror` y el trabajo diferido. El `User-Agent` es un
  parámetro **obligatorio**, no un default: MusicBrainz rechaza al que no se identifica.
- **Lo que `ResilientCall` devuelve es un sobre, y tirarlo es el fallo por defecto.** `around()` da
  `['data' => …, 'stale' => bool, 'cached_at' => int|null]`, y durante un año tres servicios de
  cuatro escribían `)['data'];` en la misma línea. Desde el 2026-08-26 cada uno tiene un hermano
  `…Resilient()` que devuelve el sobre y un método plano que delega y lo aplana: `searchGamesResilient`,
  `searchVideosResilient` y los **nueve** de `LastFmService` sobre un `cachedCallResilient()` privado.
  **Ninguna firma existente cambió**, y eso no es cortesía: en PHP una firma cambiada rompe en
  *runtime* y los unitarios mockean la interfaz, así que un llamante olvidado no lo ve nadie.
  `search_works`, `search_igdb_games`, `search_youtube_videos` y `get_listening_stats` lo sacan en
  `data`. **`stale` nunca falta** y **`cached_at` puede ser `null` con `stale: true`**, así que jamás
  se pasa por `date('c', …)` sin comprobarlo: con `null` daría hoy y el aviso mentiría.
- **Para varias llamadas a la vez está `ResilientCall::aroundMany()`, no un bucle de `around()`.**
  Dos fases: la caché de todas primero, y luego una sola tanda con `Utils::settle`. **La concurrencia
  de Guzzle solo aparece si las promesas salen del MISMO `Client`** —cada uno trae su handler de cURL
  y `wait()` solo hace avanzar el suyo—: medido, tres clientes distintos dan una ventana igual a la
  suma y uno compartido igual a la petición más larga. No es el reintento del perfil `web`, que era
  lo que se sospechaba. Por eso `SearchCatalogRemoteUseCase` crea uno y se lo presta a los tres
  servicios, que exponen `*Promise()` + `parse*Response()`, con el timeout **por petición**. Y a
  diferencia de `around()`, **no lanza**: un medio sin caché sale con `failed: true` y lista vacía,
  porque un proveedor caído no puede tumbar la búsqueda de los otros dos.
- **Un 404 no es una degradación**, y eso llega hasta el aviso. `GetListeningStatsUseCase` captura la
  excepción de un álbum que Last.fm no tiene y devuelve `data: null` con `stale: false`: marcarlo
  rancio pintaría un aviso de proveedor caído sobre una respuesta.
- **`get_listening_stats` hace UNA llamada por petición, no diez.** Su `match($query->statsType)`
  ejecuta una sola rama —el tipo lo elige el selector de `ListeningStats.vue`—, así que no hay
  frescuras que agregar. Las diez ramas del `match` se leen como diez llamadas y no lo son.
- **El reintento va en el transporte, no en `ResilientCall`**, que se queda intacto. Aquí se insiste;
  por encima, `ResilientCall` decide si lo que falló de verdad se degrada a caché rancia. Un **404 no
  se reintenta nunca**: es una respuesta, no un fallo.
- **En `web` un timeout NO se reintenta, y esto no es un descuido.** El tope del perfil acota el
  *backoff*, no el *timeout*: reintentar un proveedor que agota sus 5 s convierte el peor caso en
  10,3 s por llamada, y `runSearchStrategy` de Google Books hace dos, así que una búsqueda pasaría de
  ~10 s a ~20 s (medido: 8,26 s reales). En `batch` sí se reintenta.
- **`Infrastructure/Http/RateGate` es la puerta de 1 req/s de MusicBrainz**, con `flock` sobre fichero
  porque cada petición de Apache es un proceso distinto. Coge el lock, reserva el turno **futuro** y
  lo **suelta antes de dormir**: dormir con el lock cogido serializa los procesos en cadena.
- **PHP-DI no autowirea parámetros opcionales.** Por eso el `http` de `CoverStore` va explícito en
  `config/container.php`. Cualquier dependencia nueva declarada como `?Tipo $x = null` llegará en
  `null` en producción si no se cablea a mano.

### Mirror local de catálogos (`library_mirror`)
- **Películas y series no dependen de una API para buscarse.** Un **segundo esquema MySQL**,
  alimentado por los dumps abiertos de IMDb, sirve 2,84 M de títulos y 9,84 M de episodios en
  **1-4 ms**. `OmdbService` **ya no existe**: la ficha se enriquece con **TMDB** (sinopsis en
  español, póster, director), persistido en `tmdb_title` con caducidad a 5 meses.
- **La costura es una interfaz, no un `if`**: `MovieCatalogInterface` con tres implementaciones —
  `MySqlMovieCatalog` (local), `TmdbMovieCatalog` (red) y `FallbackMovieCatalog` (el decorador que
  decide). **`search` no sale a la red nunca**; `findByImdbId` cae solo si falta `Plot` o `Poster`, y
  **fusiona con el local ganando**; `seasonEpisodes` cae solo si el local devuelve 0.
- **El contrato con el frontend no cambió**: las acciones siguen siendo `search_movies_omdb`,
  `get_movie_details_omdb` y `get_season_episodes_omdb`, con las claves PascalCase de OMDb. Deuda
  consciente: normalizarlas obligaría a tocar cinco ficheros del frontend.
- **Vive en su propio stack y lo comparten dev y producción** (`docker-compose.mirror.yml`,
  proyecto `libraryvue-mirror`). Dentro del compose de dev no valía: la búsqueda de películas no
  sale a la red por diseño y `MySqlMovieCatalog` no captura la `PDOException`, así que un
  `compose down` en desarrollo dejaría library.dcahomelab.com respondiendo **500**, no degradando.
  Y `--musicbrainz` hace DROP/RENAME y sube el buffer pool a 4 GB, algo que no puede pasar en el
  servidor que atiende a producción. Lo levanta todo `./mirror-sync.sh --bootstrap`, que **desde el
  2026-08-26 lo llama también `start_services`** (`dev-setup.sh:311`), así que un `./dev-setup.sh` o
  un `--reset` dejan el catálogo arriba sin teclearlo aparte; y `prod-deploy.sh` se **niega a
  desplegar** si no lo encuentra (`check_mirror`).
- **Ninguna espera de MySQL se escribe con `mysqladmin ping -h localhost`.** Sobre un volumen virgen
  el entrypoint de `mysql:8.0` levanta un servidor **temporal** con `port: 0` (solo socket) para
  inicializar el datadir y correr lo de `docker-entrypoint-initdb.d`, y lo para al acabar: el ping
  por socket responde contra **ese**, así que la espera termina antes de que exista la base. Se
  consulta la base sembrada con `--protocol=TCP`, donde el falso positivo es imposible —
  `dev-setup.sh:311`, `mirror-sync.sh:211` y `prod-deploy.sh:381`—. Los que **informan** en vez de
  bloquear (`prod-deploy.sh:600`, `mirror-sync.sh:150,158` y el `healthcheck` del compose) siguen con
  `ping` a propósito.
- **La red y los volúmenes compartidos son `external: true` en los tres compose**, y eso es la red
  de seguridad, no burocracia: significa que ningún proyecto los posee y que un
  `docker compose down -v` en dev o en prod **no puede** llevarse el catálogo ni las portadas.
- **El servicio se llama `mirror-mysql`, no `mysql`.** El backend de dev está a la vez en su red y
  en `library_mirror_net`, y con `DB_HOST=mysql` el nombre resolvería a dos contenedores distintos.
- **El mirror tiene su propio usuario** (`library_mirror_user`, vía `DB_MIRROR_USERNAME`/
  `DB_MIRROR_PASSWORD`) porque un servidor MySQL tiene **una** contraseña por usuario y la de
  `library_user` no es la misma en dev que en prod. Los `DB_MIRROR_*` caen a su equivalente de la
  app si no se declaran, así que un despliegue de un solo servidor sigue funcionando igual.
- **Su DDL NO va en `docker/database/migrations/`**, sino en `docker/database/mirror_schema.sql`,
  aplicado con `root` por `mirror-sync.sh --bootstrap`. Motivo: el runner de migraciones conecta como
  `library_user` (sin permiso para crear bases) y **lo comparte `prod-deploy.sh`**, así que una
  migración con el mirror rompería producción de forma permanente.
- **Hay un segundo usuario de MySQL**, `library_mirror_importer`, que solo usa `backend/bin/mirror`:
  `LOAD DATA INFILE` exige el privilegio global `FILE`, y dárselo al usuario de la app web ampliaría
  el alcance de una inyección SQL.
- **La búsqueda va en `BOOLEAN MODE` sobre un `UNION` de dos índices FULLTEXT.** Con
  `OR ... IN (SELECT ...)` MySQL descarta ambos índices y tarda **39 s**; con `UNION`, 1 ms.
- **`bin/mirror` llama a `LoggingService::getInstance()` antes de construir el contenedor.** No es
  opcional: `container.php` invoca `LoggerFactory::createDatabaseLogger()` en directo y bajo Apache
  su config se rellena de rebote. Cualquier CLI nuevo tiene el mismo problema.
- **La atribución de TMDB en las fichas es obligatoria**, no decorativa: va en el bloque
  `attribution` de `movie` y `series` del `mediaRegistry`, y la pinta `MediaDetailView`.

### Mirror de música (`mb_release_group`)
- **Los álbumes tampoco dependen de una API, y aquí el motivo es legal.** Los Spotify Developer Terms
  prohíben *"store, aggregate or create compilations or databases of Spotify Content"*, y `albums`
  guardaba 14 columnas suyas sin caducidad. `library_mirror.mb_release_group` sirve **2,88 M de
  álbumes** (1170 MB) desde los dumps **CC0** de MusicBrainz.
- **`AlbumCatalogInterface` con tres implementaciones**, calcado de películas salvo en una regla:
  **`search` SÍ cae a Spotify** si el mirror devuelve cero, porque el mirror solo guarda `Album` y
  `EP` y un single o un disco recién salido está genuinamente ausente. `findById` **enruta por la
  forma del id** (MBID → local, base62 → Spotify), no por un fallo.
- **`release_count` no es decorativa: es el ranking.** MusicBrainz no publica popularidad, así que se
  ordena por reediciones del release group. Sin ella, `+kind +blue` devuelve un tributo reggae antes
  que a Miles Davis.
- **La identidad de un álbum es un `AlbumId`, no un `SpotifyId`.** Acepta MBID o base62;
  `SpotifyId` quedó reducido a su columna, ya anulable, como puente de reconciliación. No se amplió
  el patrón de `SpotifyId` a propósito: guardar MBIDs en una clase con ese nombre habría dejado el
  acoplamiento intacto y encima mintiendo.
- **Lo que impide volver a incumplir está en `AddAlbumUseCase::preferOpenCatalog()`**: si un álbum
  llega de Spotify pero su UPC está en `mb_release_group.barcode`, se guarda la ficha abierta. Dos
  guardas medidas sobre el dump: el barcode debe ser plausible (8+ dígitos, no todo ceros) **y**
  apuntar a un único álbum —hay uno compartido por **98** release groups—. Lo que no se resuelve se
  marca con `catalog_source` y `--purge` le anula el enriquecimiento a los 5 meses, **sin borrar la
  fila**: `user_albums` la referencia.
- **Su DDL va a `mirror_schema.sql`**, como el resto del mirror. Pero el `FULLTEXT` **no puede
  copiarse** a la gemela `_new` con `CREATE TABLE ... LIKE`: medido, con el índice presente el
  `INSERT` tarda 7563 s y sin él 344 s más 192 s de `ALTER`. `MusicBrainzImporter` la crea desnuda.
- **Los dumps de MusicBrainz NO se cargan como los de IMDb.** Son COPY de PostgreSQL de verdad y
  escapan `\t`, `\n` y `\\` dentro del campo, así que el `ESCAPED BY ''` de `ImdbImporter` los
  partiría. Se cargan con el escape por defecto de MySQL, que además convierte `\N` en NULL solo.
- **La importación necesita buffer pool.** Con 4 GB tarda ~26 min; con los 128 MB por defecto, 3,7 h.
  Lo sube y lo restaura `mirror-sync.sh --musicbrainz`, no `docker-compose.yml`.

### Pistas de álbum (`mb_track`)
- **Los dumps traen el conteo de pistas, no la lista.** La lista se pide a la API web de MusicBrainz
  por la **release canónica** (`mb_release_group.canonical_release_gid`, rellena en el 99,94 %) y se
  cachea en `mb_track` **sin caducidad**: MusicBrainz es CC0 y no impone el límite que obliga a
  purgar `tmdb_title`.
- **El fetch va SIEMPRE diferido**, nunca dentro de la petición: la API tarda entre **4 y 45 s**
  (medido). La primera visita a una ficha responde en 0,09 s **sin pistas** y la segunda ya las trae.
  Eso es el diseño, no un fallo — hacerlo síncrono serían 45 s en blanco.
- **No preguntes por el release group entero.** `/release?release-group=…` no termina en discos muy
  reeditados: >2 min en *The Dark Side of the Moon*, con 151. Por eso se guarda la canónica.
- **La API devuelve sus errores DENTRO del JSON**, con clave `error`, y bajo carga lo hace mucho.
  Parsearlo sin mirar da «álbum sin pistas» y envenena la caché para siempre. `MusicBrainzService`
  lo trata como fallo.
- **Un álbum doble repite `position` entre discos.** `AlbumTrackService::flattenTracks()` renumera de
  forma continua o la PK de `mb_track` se come medio disco en silencio. `number` **no** se renumera:
  es lo impreso en el disco, y en la canónica en vinilo de *Abbey Road* son `A1`, `A2`…
- **`mb_track_fetch` no es una tabla de más.** Sin ella, «no se ha pedido nunca» y «se pidió y no
  había nada» son indistinguibles y el backfill entra en bucle.

### Portadas locales (`storage/covers` + `cover_file`)
- **La biblioteca se ve sin depender de ningún CDN.** Al añadir un ítem, `CoverService` (dominio,
  inyectado en los cinco `Add*UseCase`) registra su carátula en `library_mirror.cover_file` y la
  descarga ocurre **después** de la respuesta HTTP. Medido: guardar cuesta lo mismo con portada
  (0,0305 s) que sin ella (0,0324 s).
- **`fastcgi_finish_request` no existe bajo `mod_php`.** El trabajo post-respuesta vive en
  `Infrastructure/Http/PostResponse.php` y es `ob_start()` **antes** de responder → en el apagado
  `Connection: close` + `Content-Length` → `ob_end_flush()` → `flush()` → trabajo. El `ob_start()` no
  es opcional: `Application::sendResponse()` hace `echo` sin `Content-Length` y el contenedor no
  activa `output_buffering`.
- **`GET /index.php?cover=<medio>/<clave>` es la única excepción al endpoint único.** Vive en
  `backend/public/cover.php` y se despacha **antes** de `bootstrap.php`, porque `Application` emite
  `Content-Type: application/json` en su constructor. Como `bin/mirror`, tiene que llamar a
  `LoggingService::getInstance()` antes de construir el contenedor. Responde 200 con la imagen
  (`Cache-Control` 30 días), **302 al origen** si aún no hay copia, o 404.
- **El DDL de `cover_file` va a `mirror_schema.sql`**, no a `migrations/`, por la misma razón que el
  resto del mirror. En su `ON DUPLICATE KEY UPDATE`, **`source_url` se asigna el último**: MySQL
  evalúa de izquierda a derecha, y con él primero una URL nueva nunca resetearía `storage_path`.
- **El volumen de portadas es compartido con producción, y no es una comodidad.** `cover_file` vive
  en el mirror, que es compartido, y su `storage_path` es relativo al volumen de portadas: con un
  volumen por entorno, la fila que escribe dev apuntaría en prod a un fichero inexistente. Y no
  fallaría de forma ruidosa — `CoverStore::localPath()` hace `is_file()` → `null` → 302 al CDN—,
  pero como `claimPending()` filtra por `storage_path IS NULL`, **prod no volvería a bajarla nunca**:
  producción servida desde el CDN ajeno para siempre y en silencio.
- **`bin/mirror covers:seed` es imprescindible, no un extra.** `register()` solo corre al añadir, así
  que sin sembrar, lo que ya estaba en la biblioteca no tiene fila y el endpoint le devuelve 404.
  `covers:backfill` lo ejecuta antes de descargar.
- **Y solo puede sembrar lo que esté en la columna.** `CoverSeeder` sale de `cover_url_*` /
  `coverUrl` / `cover_url` de cada medio: si el alta no persiste la URL, el ítem es **irrecuperable**
  para el sembrado, no solo «pendiente». En libros pasó, y con tres fallos encadenados que no
  rompían nada — `AddBookUseCase` no le pasaba `$command->coverUrl` a `BookImportService`, este
  devolvía las URLs bajo una clave `cover_urls` que `Edition::fromArray()` nunca ha leído, y luego
  leía `$legacyFormat['cover']`, clave que `toLegacyFormat()` no emite (la suya es `coverUrl`). Lo
  fija `tests/Integration/BookCoverTest.php`, y por integración: los tres viven en puntos distintos
  de la misma cadena y un mock de cualquiera la corta justo donde está el fallo.
- **En el frontend son seis consumidores, y dos métodos distintos.** `MediaListItem`,
  `LibraryMediaItem`, `views/shared/MediaDetailView` y —desde el 2026-08-26— `Social/FeedEventCard`
  usan `CoverService.localCoverUrl()` para lo **guardado**; los dos carruseles de búsqueda
  (`AlbumCarouselItem`, `MovieCarouselItem`) usan `catalogCoverUrl()` para lo que **no** lo está.
  Todos llevan `@error` con el escalón doble local → remota → placeholder.
- **La bandeja de recomendaciones es el séptimo consumidor de portada**, y su tarjeta
  (`components/Inbox/RecommendationCard.vue`) necesita los mismos **dos** indicadores que la del feed,
  por el mismo motivo. `InboxView` despacha por un `kind` contra un mapa `CARDS`: las invitaciones de
  los planes de listas y clubs entran como un `kind` más, sin rehacer la pantalla. Y el
  `RecommendDialog` de las fichas se monta con **`v-if`**, no solo con `v-model`: usa dos stores de
  Pinia en su `setup`, así que instanciarlo siempre haría que toda ficha visitada los levantara —lo
  destaparon 28 tests cayéndose a la vez, porque montan sin Pinia activo—.
- **La tarjeta del feed necesita DOS indicadores de fallo, no uno.** Los otros consumidores tienen
  siempre una URL remota que pintar, así que les basta un `localFailed`. En el feed no: sin
  distinguir «estoy pintando la local» (`usingLocal`), un evento sin copia local gasta su primer
  `@error` marcando un fallo que no ha ocurrido y, como el `src` no cambia, el navegador no reintenta
  y **el placeholder no llega nunca**. Y su clave de medio es `entity_type`, **no** el medio del
  registry: una serie se guarda con `AddMovieUseCase` y su fila lleva `media_type = 'movie'`.
- **La caché del catálogo tiene su propio `scope`, y es lo que salva a la biblioteca.**
  `cover_file.scope` es `'library'` o `'catalog'`, y `bin/mirror covers:purge [días]` (60 por defecto,
  lo lanza `./mirror-sync.sh --covers` antes del backfill) **jamás** toca las de biblioteca. Al
  guardar un álbum visto en una búsqueda, `promoteToLibrary()` **reetiqueta** la fila en vez de crear
  otra — y por eso `AddAlbumUseCase` **no** llama a `recordCover()` si la promoción tuvo éxito:
  `register()` vería una `source_url` distinta y bajaría la portada por segunda vez.
- **`resolveCatalog()` comprueba la clave contra el mirror ANTES de salir a la red**, y eso no es
  optimización: sin la guarda, `?cover=movie/tt9999999` en bucle es una llamada a TMDB por petición.
- **Dos filas con la misma `source_url` comparten fichero**: `relativePathFor()` hashea la URL, no la
  clave. Borrar el fichero de una sin comprobarlo deja a la otra rota **en silencio** —302 al CDN, y
  `fetchPending()` no la recupera porque filtra por `storage_path IS NULL`—. Lo impide
  `CoverStore::pathIsShared()`.
- **La cadena de Cover Art Archive son dos saltos y hay que parar en el primero.** El segundo lleva a
  un nodo de almacenamiento que rota; el primero (`archive.org/download/mbid-…`) es canónico y cuesta
  un tercio.
- **La fila de `tmdb_title` se escribe completa, con dos llamadas a TMDB.**
  `TmdbMovieCatalog::readCached()` devuelve lo que encuentre dentro de sus 5 meses **sin comprobar si
  los campos están rellenos**, así que una fila parcial deja esa ficha sin sinopsis ni director.
- **En la ficha, la clave sale de `existing`, no de `item`**, y como `computed`: `existing` no está
  en el primer render, así que cachearla en un `ref` deja la ficha con la URL remota para siempre. Y
  el reset del estado de fallo **se ancla a la portada, no a `item`** — el enriquecimiento muta
  `item` a los pocos milisegundos de montar y anularía un fallback recién decidido.
- **Las series piden `?cover=movie/<imdbID>`**, no `series/…`, y no es un error: se guardan con
  `AddMovieUseCase`, así que su fila lleva `media_type = 'movie'`. `cover.php:36` acepta `'series'`
  como medio válido, pero no hay ni una fila con ese `media_type`. La **clave** sí es la suya:
  `mediaRegistry.js:1488` le asigna a `series` el `libraryItem` de `movie` **fuera del literal del
  objeto**, que es también lo que hace que `title` y `notesId` funcionen en esa ficha.

### Frontend (Vue 3 + PrimeVue)
- Vistas/rutas SPA (búsqueda, biblioteca, detalle, dashboard) con `createWebHashHistory`; ~9 stores
  Pinia; composables para CRUD/búsqueda/auth/stats. Gráficas con Chart.js.
- TODA I/O contra el backend propio pasa por `auth.apiCall` / `auth.authenticatedApiCall`
  (`src/store/auth.js`), que centraliza `VUE_APP_API_URL`, CSRF, JWT, `withCredentials`, el timeout
  y el manejo del `429`. `src/services/` son servicios de dominio (importación, stats, ficheros) y
  se apoyan en él, no al revés. No instancies `axios` suelto ni hardcodees la URL: hay una regla de
  ESLint (`no-restricted-imports`) que lo impide, exceptuando el propio `store/auth.js` y
  `composables/useWorkSearch.js`, cuyo fallback llama a Open Library directamente.
- **La accesibilidad la sostiene el lint, no la revisión.** ESLint va en
  `plugin:vue/vue3-recommended` con las **20 reglas de `eslint-plugin-vuejs-accessibility` en
  `error`**: un `<div @click>` nuevo rompe el lint (exit 1), y `npm run lint` corre dentro de
  `./dev-setup.sh`. Lo que se comporta como control es `<button>` con `@include button-reset` —el
  mixin va **sin `width`** a propósito—, y lo que solo existe como icono se dice con `.u-sr-only`, no
  con `aria-label`. Los cuatro modales propios usan `composables/useFocusTrap.js`; los `<Dialog>` de
  PrimeVue traen el suyo y no se envuelven. Detalle en [[LibraryVue/Frontend]] → *Accesibilidad*.
- **El color vive en dos ficheros y en ninguno más.** `assets/styles/tokens/_colors.scss` es el tema
  **claro** (superficie hueso `#F7F2EC`, el teal de marca como **acento**) y `themes/_dark.scss` el
  oscuro; `themes/_light.scss` sigue sin generar CSS a propósito. Cada valor lleva anotado su ratio
  de contraste contra la superficie más exigente de su tema. Fuera de esos dos ficheros **no hay un
  solo hex** salvo colores de marca, y lo impide `stylelint` desde `./dev-setup.sh`: prohíbe el hex
  suelto, el `px` dentro de un `@media`, `prefers-color-scheme`, `@import`, el **`min-width` fijo**
  en `px` o `rem` (2026-08-29) y, desde el 2026-08-30, el **`z-index` de tres cifras o más** —para
  eso está `z()`— y el **`box-shadow` literal**, salvo el anillo de foco `0 0 0 Npx`. Dos matices que se olvidan: las
  superposiciones sobre carátula (`--color-overlay-strong`, `--color-on-overlay`,
  `--color-rating-star`, `--color-media-letterbox`) **no** conmutan con el tema —van sobre una
  portada arbitraria—, y `--color-on-status` **sí**, porque es la tinta que acompaña a un relleno
  semántico y en oscuro esos rellenos son claros.
- **Un `margin` que vale lo que MIDE otra cosa no es espaciado, y la escala se lo come.**
  `Layout.vue` tiene `margin-left: 280px /* Ancho del sidebar */`, `margin-top: 70px` (el header),
  el `margin-left: 60px` del sidebar plegado y el `padding-bottom: 60px` de `MobileNavBar`: los
  cuatro **miden** otra cosa, y el `min-height: calc(100dvh - 70px)` de al lado depende del segundo.
  Pasarlos a `spacing()` **hunde el contenido debajo del sidebar**, y pasó al barrer los 92 SFCs el
  2026-08-31 — con los 382 tests, el lint y el build en verde; solo lo vio la captura. La regla, que
  vale para cualquier barrido futuro: si el valor cambiaría al cambiar la densidad de la interfaz es
  **espacio**; si define el tamaño de una cosa concreta, es **medida** y se queda literal. De los 453
  literales `px`/`rem` que quedan en los SFCs, esa es la razón de casi todos.
- **Los acentos de entidad se validan con un script, no se eligen a ojo.** Los cinco
  `--color-card-<medio>-accent` pasan las cinco comprobaciones del validador de la skill `dataviz`
  **en modo `--pairs all`** —no adyacente: en `/library` los cinco medios conviven mezclados, así que
  cualquier par puede ser vecino—. Si tocas uno, revalida los cinco contra las dos superficies.
- **Un botón, un modal y un estado vacío se escriben de UNA manera.** `assets/styles/components/_buttons.scss`
  es la única forma de escribir un botón —`.btn` con `--primary|--secondary|--accent|--danger|--ghost`,
  `--icon`, `--sm|--lg` y `is-loading|is-success|is-error`—, y sus tres reglas de uso van en la
  cabecera del fichero: **un** botón por pantalla es `--primary`, `--danger` **solo** si destruye
  datos, y `--color-info` **no es un color de botón** (es el hermano de `--color-error` y
  `--color-warning`; su sitio son avisos y badges). `components/common/BaseModal.vue` es el chasis de
  **todos** los modales: `primevue/dialog` no aparece en el repo y no debe volver. Y
  `components/common/EmptyState.vue` **no tiene `tone: 'error'`** a propósito — buscar algo que no
  existe es una respuesta, no un fallo, y mezclarlos era el bug de `GenericSearch`.
  **No todo `<button>` es un botón de la escala:** las tarjetas clicables, los selectores tipo radio
  con `aria-pressed` y los conmutadores (`sort-button`, `tag-pill`, `star-button`) quedan fuera a
  propósito, y así está escrito en cada uno.
- **A través de un `<Teleport>` no viaja ni la `class` heredada ni el `data-v-` del padre.** Vue no
  hereda atributos en un Teleport, y el scope id tampoco cruza, así que un `:deep()` desde fuera no
  engancha nada. Lo que un componente teletransportado necesite del consumidor va por **props**: es
  lo que son `icon-tone` y `accent` de `BaseModal`. Se descubrió porque el icono de
  `ConfirmationModal` salía teal en vez de rojo **con los 382 tests, el lint y el build en verde**.
  Y en los tests, el Teleport saca el marcado del wrapper de Vue Test Utils: el helper
  `tests/unit/helpers/mount.js` lo neutraliza con `stubs: { teleport: true }`.
- **Ningún `min-width` en píxeles, y `:deep()` solo dentro de un bloque `scoped`.** Un `min-width`
  fijo no encoge: empuja su fila fuera del viewport en cuanto la pantalla baja de esa medida. Se
  escribe `min(200px, 100%)` dentro de un contenedor que ya limite, o `min(380px, 80vw)` en overlays
  y modales, que no lo tienen; lo vigila la quinta regla de `.stylelintrc.json`. Y **`:deep()` en un
  `<style>` sin `scoped` tira la regla entera en silencio**: Vue solo lo traduce en bloques scoped,
  fuera de ellos el navegador lo lee como pseudo-clase desconocida y descarta el selector.
  `MyLibrary.vue` tuvo cinco reglas así desde que se escribieron —la rejilla de `/library` nunca
  respondió al ancho— y no lo vio nadie hasta el 2026-08-29. Lo que comprueba las dos cosas en la
  app de verdad es `npm run test:responsive`, no Vitest: jsdom no evalúa CSS.
- **El umbral de móvil está en `composables/useBreakpoint.js`**, con un único listener de `resize`
  compartido, y su `isMobile` es `< 768` porque `responsive-below(md)` compila a `max-width: 767px`.
  En SCSS no se escriben píxeles en un `@media`: se usan `responsive()` / `responsive-below()` de
  `abstracts/_breakpoints.scss`.
- **Las gráficas no eligen color: lo leen.** `config/chartTheme.js` es la única fuente
  (`entityColor`, `categoricalPalette`, `chartInk`, `chartTooltip`); `StatsService.generateColors()`
  ya no existe. Chart.js pinta en `<canvas>` y no entiende `var()`, así que el módulo expone un `ref`
  que todas sus funciones tocan: cualquier `computed` que las llame repinta solo al cambiar de tema.
- **Lo que varía por medio se declara, no se copia.** `src/config/mediaRegistry.js` es la única
  descripción de lo que diferencia a un medio, y de ella se configuran **seis genéricos**:
  `GenericSearch` (los cinco `*Search.vue`), `MediaNotes` + `useMediaNotes` (los `*Notes.vue`),
  `MediaListItem` (los `*ListItem.vue`), `shared/LibraryMediaItem` (los `Library*Item.vue`),
  `views/shared/MediaDetailView` (las seis `*DetailView.vue`) y —desde el 2026-08-27—
  `composables/createMediaComposable.js` (los cinco `use<Medio>.js`, de 1.382 líneas a 535); además,
  `store/createMediaStore.js` genera los cinco stores de Pinia. Los ficheros por medio siguen
  existiendo como wrappers, así que **ningún import cambió**: la factoría genera hasta los alias con
  nombre de medio (`fetchVideos`, `getVideoByYouTubeId`…).
  **Antes de duplicar algo para un medio nuevo, mira si su familia ya tiene genérico.**
  El matiz que costó una tarde el 2026-08-28: el genérico sirve si tu ítem tiene **la forma del
  medio**. `MediaListItem` no lee el ítem, lo lee a través de los accessors del registry —`idOf:
  i.isbn` en libros, `i.imdbID` en películas, `subtitleOf` con `i.author`—, así que una fila de
  `media_list_item` (que solo tiene `entity_type`/`_id`/`_title`/`_cover`) pinta pero **miente**:
  todas las tarjetas de libro dirían «Autor desconocido». Por eso `components/Lists/ListItemCard.vue`
  es propia y del registry solo saca lo declarativo de verdad — `list.iconOf`, `list.coverAspect`,
  `label` y el acento—. Y comprueba el medio contra `mediaKeys` antes de llamar a `getMediaConfig`,
  que **lanza** con uno desconocido.
- **En el composable, lo propio de un medio va en su wrapper y SUSTITUYE al núcleo.**
  `createMediaComposable(media, extras)` mezcla `extras` **después**, así que un miembro con el mismo
  nombre gana: es lo que hace `useBooks` con `updateBookStatuses` (la máquina de transiciones) frente
  a la delegación de tres líneas que genera el núcleo. Invertir ese orden borraría la máquina sin que
  nada fallara ruidosamente; hay un test que lo fija. Y el mapa de `useXStore` vive en la factoría, no
  en el registry: el registry es lo que importa `createMediaStore`, y meterlo ahí cerraría el ciclo.
  Desde el 2026-08-27 ese mapa se **exporta** como `mediaStores`, para que `store/inbox.js` dé de alta
  un ítem recomendado sin duplicarlo; importarlo desde ahí no reintroduce el ciclo.
- **`createMediaComposable('series')` lanza a propósito**, como `createMediaStore('series')`. Iterar
  sobre `mediaKeys` (los seis) en vez de `storeMediaKeys` (los cinco) es el error fácil.
- **El destino de una ficha se compone, no se declara otra vez.** `detailRouteFor(media, entityId)`
  (`mediaRegistry.js`, junto a `getMediaConfig`) devuelve el `{ name, params }` de la ruta de detalle
  a partir del `routeName` y el `detail.routeParam` que cada entrada **ya tenía**; devuelve `null`
  para un medio desconocido o un id vacío, en vez de reventar como `getMediaConfig`, porque quien
  llama es la tarjeta del feed y `feed_events.entity_type` es NULLable. Si añades una ruta de
  detalle, no le pongas un campo nuevo al registry: rellena esos dos.
- **Lo que sabe buscar un medio vive en `api.search` del registry, y antes MENTÍA.** Cuatro campos:
  `action`, `payload(query, limit)`, `transform` y `titleOf`. Hasta el 2026-09-01 el registry
  declaraba **cuatro acciones que no existen** en `config/routes.php` —`search_book_isbn`,
  `search_book_name`, `search_movie_name`, `search_game_name`—; las reales son `search_works`,
  `search_movies_omdb` y `search_igdb_games`. No lo vio nadie porque su único consumidor,
  `createMediaStore.search()`, **no lo llamaba nadie**, y un test llegó a fijar la llamada imposible.
  **Verifica contra `routes.php` cualquier acción que escribas ahí.** El `payload` existe porque los
  seis llaman distinto al mismo parámetro —`q`, `title`, `query`— y **vídeos no llama `limit` al
  límite**: lee `maxResults` (`VideoController.php:111`), y mandar `limit` lo dejaba en su defecto sin
  error.
- **`MediaListItem` pinta los SEIS medios, pero está pensado para lo GUARDADO.** Necesita el bloque
  `list` del registry, y `series` lo estrenó el 2026-09-01 tomándolo de `movie` —le vale verbatim,
  salen de la misma respuesta OMDb—; sin él revienta con `config.value.list is undefined` en cuanto
  una serie entra en una lista mezclada, cosa que en `/library` no pasa porque ahí una serie se
  guarda como película. Y como pregunta por la copia **local** y si no hay pinta `list.coverOf`,
  quien enseñe **catálogo** debe rellenar la portada con `CoverService.catalogCoverUrl()`: el mirror
  devuelve `Poster: null` en las búsquedas de película y serie, también en `search_movies_omdb`.
- **Las píldoras de filtro por medio son UNA, en `components/_filter-pills.scss`.** Las comparten
  `/library` y `/search`. El mixin **no impone la disposición de la fila** —ni `display`, ni `gap`, ni
  márgenes—: eso lo pone cada consumidor, y es lo que permitió sacarlo de `MyLibrary` sin moverle un
  píxel.
- **La ficha se estructura alrededor de «¿qué tengo yo con esto?», y eso son cinco reglas.**
  Desde el 2026-09-03: **(1)** `.detail-body` es una rejilla de dos columnas en ≥`lg` con el panel
  *Mi biblioteca* pegajoso a la derecha, y en el DOM el panel va **antes** que `.detail-extra` a
  propósito —en móvil se lee en ese orden y el de tabulación coincide; en ≥`lg` los recoloca
  `grid-column`, **nunca `order`**—. **(2)** El panel **no pinta nada del catálogo** y **no lleva
  botones de la barra**: expone `guardar()` para que lo dispare el CTA, y con `editable` la
  valoración y el estado **emiten** para que los guarde `MediaDetailView` con las tres guardas del
  modal. **(3)** La barra es `volver · CTA · ⋯`, y el CTA conmuta con `existing`. **(4)** Las notas
  van en los **seis** medios y a ancho completo, solo con el ítem guardado; el modal de edición ya no
  las lleva dentro. **(5)** Los identificadores viven plegados en `<details class="detail-technical">`
  al final de la columna izquierda: no se leen, se copian. Los botones propios de un medio se
  declaran en `libraryItem.extraActions`, con `onlyExisting` (mira el estado en tu biblioteca) y
  `when` (mira el ítem).
- **⚠ La ficha de serie le pasa al panel `media`, NO `d.libraryMedia`.** `series.detail.libraryMedia`
  vale `'movie'` y es correcto para el `EditItemModal` —despacha por medio y series no tiene store—,
  pero con eso el panel se configuraba como película y el `extraActions` propio de series no se
  consultaba nunca. Por lo mismo, `series` hereda `list`, `libraryItem` y `notes` de `movie`
  **por prototipo y no por referencia**: con la referencia compartida, un `extraActions` de series
  aparecería también en las películas. Y `notes` no se copia con un spread, que **ejecuta** los
  getters y congelaría los rótulos con el catálogo vacío.
- **⚠ `<details>` no plega solo.** Ocultar el contenido de un `<details>` cerrado lo hace la hoja del
  **navegador**, que es la de MENOR prioridad: cualquier `display` de autor sobre el hijo le gana y
  el contenido se ve con el plegable cerrado. Y no es solo teoría: en el Firefox headless de este
  entorno un `<details>` recién creado en `about:blank` **enseña su contenido cerrado**. Si el
  plegado importa, se escribe (`&:not([open]) .cuerpo { display: none }`).
- **Al abrir una ficha, pásale el ítem en `state`, no solo la ruta.** `MediaDetailView` arranca de
  `history.state?.[stateKey]` (`:348`) y solo si no hay nada depende de reconstruirlo. En libros eso
  significa `search_google_books_isbn`, o sea `q=isbn:…`, y **Google no indexa por ISBN todos los
  volúmenes que devuelve en una búsqueda**: medido, 13 de 19 ISBN válidos no se encontraban. Sin el
  `state`, la ficha no abre.
- **De Google Books se devuelven FILAS, no volúmenes.** `parseVolumesResponse()` mapea a
  `{isbn, title, author, cover_i, …}`, que es lo que espera `searchTransformBook`; devolver el volumen
  crudo dejó el buscador general **sin un solo libro** durante horas, sin ningún error a la vista. Su
  namespace de caché lleva versión por eso. Y los volúmenes **sin ISBN se descartan**: la ficha vive en
  `/books/:isbn` y una fila que no abre es peor que una que falta.
- **El buscador general son DOS acciones, partidas por velocidad y no por dominio.**
  `search_catalog_local` (película, serie, álbum: mirror, ~300 ms, `limit: 120` declarado como
  `search_movies_omdb`) y `search_catalog_remote` (libros, juegos, vídeos: red, los tres a la vez).
  `/search` las lanza **a la vez**, pinta lo local con `MediaSkeleton` reservando el hueco y reordena
  al llegar la red. El orden lo decide `utils/searchRelevance.js`, función pura de cinco escalones que
  **normaliza sin acentos ni signos** y desempata por `mediaKeys`; el escalón de palabra entera existe
  por los títulos cortos («it», «up»), que sin él llenan la primera pantalla de coincidencias casuales.
- **Cuando la búsqueda sirve caché caducada, se dice, y una vez.**
  `components/shared/StaleNotice.vue` es la franja, y la gobierna un `supportsStale` del bloque `api`
  del registry: lo declaran **`book`, `game` y `video`**, y películas y álbumes **no**, que es lo que
  garantiza que no cambien ni un píxel —los sirve el mirror local y no pueden ser rancios—. Tres
  detalles que cuestan una tarde: el sobre entra por el `searchHandler`, que **acepta la lista pelada
  de siempre o `{ results, stale, cached_at }`**; el puente hasta el registry es una clave `media` en
  la config de `GenericSearch`, porque los cinco `*Search.vue` la construyen a mano y no leen el
  registry; y la comprobación va contra `mediaKeys`, **no** llamando a `getMediaConfig`, que **lanza**
  con un medio desconocido. La franja se retira con **cero resultados** y con **búsqueda fallida**: un
  proveedor caído sin caché tiene que dar el error de siempre, no un aviso de caché sobre el vacío.
- **La espera también tiene su genérico.** `components/shared/MediaSkeleton.vue` cubre las cuatro
  familias de tarjeta con una prop `variant` (`list-item` · `library-item` · `carousel` · `detail`):
  no escribas otro «Cargando…» ni otro spinner. Sus medidas salen de los mixins SCSS de cada familia
  —y el alto de fila de `$list-item-height` en `assets/styles/components/_list-item.scss`— para que
  al llegar los datos no salte nada; **si tocas esos mixins, toca también sus `VARIANTS`.** Lo mismo
  con los `coverAspect` del registry: hay uno por bloque de familia, no uno por medio, porque el
  aspecto depende de dónde se pinte (los juegos son `1/1` en la fila y `2/3` en la biblioteca).
  - `series` es la sexta entrada del registry y **solo tiene bloque `detail`**: comparte el store de
    películas porque en el backend son la misma entidad. De ahí que convivan `mediaKeys` (los seis) y
    `storeMediaKeys` (los cinco con store); `createMediaStore('series')` falla a propósito.
  - **El CSS con `scoped` no alcanza el marcado de un genérico**, solo su raíz y el contenido de los
    slots. Por eso `MediaDetailView` y `LibraryMediaItem` emiten sus propios mixins además de los
    wrappers. Es un fallo que **jsdom no puede detectar**: se ve con capturas
    (`.github/skills/frontend.md`, *Visual Verification*).
- **Tests de frontend en `frontend/tests/unit/`** (Vitest + `@vue/test-utils`, entorno `jsdom`). Monta
  con el helper `tests/unit/helpers/mount.js`, no con `mount` a pelo: registra PrimeVue y provee el
  `notifications` del `inject`. `tests/unit/setup.js` trae el polyfill de `matchMedia` sin el cual no
  se puede montar nada que lleve un `Dropdown`.
- **Ningún `@keyframes` puede animar `left`, `right`, `top` ni `bottom`.** Anima con `transform`, o
  mueve el fondo con `background-position` como hace `@keyframes shine` en
  `components/common/ReadingProgressBar.vue`. Lo defiende `tests/unit/animaciones.spec.js`, que lee
  los `@keyframes` de `src/**/*.{scss,vue}` contando llaves y **no tiene fichero de excepciones**. El
  motivo no es estético: `tests/visual/overflow.mjs` mide `getBoundingClientRect().right`, que
  incluye el desplazamiento, así que una caja animada entra y sale del viewport varias veces por
  segundo y el veredicto depende del fotograma. Prohíbe **offsets, no `transform`s**.
- **La app respeta `prefers-reduced-motion`** desde `assets/styles/base/_globals.scss`, y
  `tests/visual/overflow.mjs` arranca Firefox con esa misma preferencia para medir una página quieta.
  El bloque usa `animation-duration: 0.01ms`, **no** `animation: none`: así una animación de entrada
  termina en su estado final en vez de quedarse en el inicial.

### Base de datos
Esquema y seed en `docker/database/init.sql` (solo en BD virgen). Cambios posteriores → archivos
datados en `docker/database/migrations/`.

- **`init.sql` NO es el esquema actual, y nunca lo ha sido.** Sigue creando `user_follows` (que la
  migración de mayo elimina) y no crea `friendships`, `feed_events`, `user_privacy_settings`,
  `users.username`, `users.is_admin`, el `'video'` del ENUM de `feed_events` ni las columnas de
  MusicBrainz de `albums`. Consolidarlo se descartó a propósito: regenerarlo desde un `mysqldump`
  perdería los comentarios en español y el seed, y aplicar los deltas a mano se arriesga a dejarse
  uno y quedar igual de mal creyendo que no.
- **La vía elegida es que sea imposible tener una base sin migrar.** `start_services`
  (`dev-setup.sh:311`) arranca el mirror y aplica las migraciones **antes** de dar el entorno por
  levantado, así que `./dev-setup.sh` y `./dev-setup.sh --reset` dejan la base al día sin teclear
  nada más. `--migrate` sigue existiendo para aplicar una migración nueva sin reiniciar. Las dos
  llamadas son idempotentes: `mirror_schema.sql` usa `IF NOT EXISTS` y `run_migrations.sh` lleva su
  propia tabla `schema_migrations`.
- **En producción no es automático**: `prod-deploy.sh` tiene su propio `warn_pending_migrations`
  (`:332`) y `--migrate` se teclea aparte, a propósito.

## Variables de entorno (`.env`)

DB: `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `DB_PASSWORD`.
APIs: `GOOGLE_CLIENT_ID`, `GOOGLE_BOOKS_API_KEY`, `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`,
`LASTFM_API_KEY`, `YOUTUBE_API_KEY`, `TMDB_API_KEY` (IGDB usa las de Spotify/Twitch).
Mirror de catálogos: `DB_MIRROR_DATABASE`, `DB_MIRROR_IMPORT_USER`, `DB_MIRROR_IMPORT_PASSWORD`.
**No subas secretos**: `.env` está gitignored y contiene claves reales.

## Buenos comportamientos en este repo

- **Endpoint = tres sitios coherentes** (routes, match/getController, controller) — **cuatro cuando
  el controller tiene contrato** en `Controllers/Contracts/`, que son **nueve de los trece**. Quitar
  un método del controller sin quitarlo de su interfaz no da un test en rojo: da un *fatal* de PHP
  al cargar la clase («contains 1 abstract method»), que revienta la suite entera antes del primer
  test. Pasó el 2026-08-29 borrando `getAllBooks`.
- **Ningún parámetro puede llamarse `action`.** El payload viaja plano en la raíz junto a la clave
  `action` del protocolo (`Application.php:117-122`), así que un parámetro con ese nombre **pisa al
  enrutado** y la petición muere con «No valid action specified», nombrando una acción que nadie
  pidió. Y **los tests de integración no pueden verlo**: entran por `dispatch($accion, $datos)`, con
  las dos cosas ya separadas. Pasó con `resolve_recommendation` el 2026-08-27 —siete tests en verde,
  y lo cazó un `curl`—; hoy lo impide `tests/Integration/PipelineTest.php` →
  `no_route_may_require_a_field_called_action`.
- **Depende de interfaces de repositorio**; registra los nuevos en `config/container.php`.
- **PHPUnit dentro del contenedor**; warnings hacen fallar la suite.
- **Un `Add*NoteUseCase` emite al feed solo si la nota es pública**, y esa guarda va **en el use
  case**, nunca en `FeedEventService`: ese servicio se traga sus propios errores por diseño, y
  esconder ahí una regla de privacidad convertiría un fallo silencioso en un escape silencioso. Cada
  uno necesita **dos** dependencias: `FeedEventService` y el repositorio de su entidad, porque el
  evento exige título y portada y el repositorio de usuario no los da.
- **Cuidado con los nombres de parámetro entre interfaz e implementación.** PHP liga los argumentos
  **nombrados a la clase concreta**, así que un `$movieIsbn` en la interfaz y un `$movieId` en la
  implementación rompen la llamada — y **los tests unitarios no lo ven, porque mockean la interfaz**.
  Pasó de verdad con `add_movie_note`, y lo encontró la suite de integración.
- **Un endpoint nuevo merece un test de INTEGRACIÓN, no solo unitarios.** Los unitarios mockean PDO y
  por diseño no ven el fallo típico de aquí: la acción declarada a medias en uno de los tres sitios, o
  un SQL que no casa con el esquema. Se entra por `ActionRouter`, no por HTTP: `tests/Integration/`.
- **Una acción que el frontend no llama es una acción que nadie sabe si funciona.** El 2026-08-27, al
  conectar por primera vez `update_book_user_statuses` desde la UI, resultó que respondía **500 a
  todo** por dos motivos a la vez: `ActionRouter.php:261` construía su comando con los argumentos en
  otro orden que el constructor, y `MySqlUserBookRepository::hasBook()` consultaba `user_books`, una
  tabla que el esquema **no tiene** —la `PDOException` caía en su propio `catch`, que devuelve
  `false`, así que contestaba «Book not found» con el libro delante—. `get_user_active_reading_sessions`
  estaba igual: llamaba a `getActiveSessions()` y el repositorio lo tiene como
  `getUserActiveSessions()`. **Las dos suites en verde durante meses**, porque los tres fallos viven
  en la costura que un mock sustituye. El **tercer** caso apareció el 2026-08-29: `get_books`,
  declarada en los tres sitios y **sin un solo consumidor** en `frontend/src`, respondía 500 porque
  `MySqlBookRepository::findAll()` leía `books`, tabla del modelo anterior a Work/Edition que el
  esquema dejó de crear. Aquí la excepción **no** se tragaba —el repositorio la reenvía como
  `RuntimeException`—, así que era un 500 limpio y no un «no encontrado» mentiroso; el `get_library`
  que sí llama `HomePage.vue` va por `GetBooksUseCase`, otro camino, y por eso nunca falló nada a la
  vista. Antes de conectar una acción que no usaba nadie, pruébala con `curl` contra el backend de
  dev. El **cuarto** llegó el 2026-09-03 con `delete_movie`, y deja dos
  avisos nuevos. Uno: **`ValidationMiddleware` exige TODAS las claves de su `required`, no una**, así
  que `['required' => ['imdbID', 'id']] // Either imdbID or id required` rechazaba cualquier llamada
  real; si el contrato es «una de varias», la comprobación va en el comando, no en la ruta. Dos:
  **hay DOS `idPayloadKey` por medio y no significan lo mismo** —el del bloque raíz es el de las
  NOTAS, el del bloque `store` es el de las acciones del store—, y en películas valen `movieIsbn` e
  `isbn` respectivamente; escribir la validación contra el que no es deja la acción muerta con las
  dos suites en verde. Y el aviso que se lleva la palma: **un test de integración escrito con la
  clave equivocada pasa en verde con la app rota**, porque está de acuerdo con tu suposición y no con
  el cliente. Lo que lo destapó fue mirar el `body` de la petición en el navegador.
- **El barrido que caza esta clase de fallo de golpe es cruzar el esquema con el SQL del código**:
  los `CREATE TABLE` de `docker/database/*.sql` contra lo que consulta `backend/src`. Dos avisos de
  quien lo hizo: las palabras clave hay que exigirlas **en MAYÚSCULAS** —en minúsculas el regex caza
  la prosa inglesa de los comentarios y da 146 falsos positivos— y los importadores **crean tablas
  al vuelo** (`mb_stage_*`, `imdb_*_new`), así que hay que recogerlas también del PHP. El barrido del
  2026-08-29 dio cuatro tablas fantasma —`books`, `user_books`, `user_book_notes`,
  `book_has_statuses`— en 18 métodos de tres repositorios de libros, de los que solo `get_books` era
  alcanzable; se borraron esos métodos, `UserLibraryStatisticsService` (cero referencias en el repo),
  `GetLibraryUseCase` (inyectado en `LibraryController`, nunca invocado) y el par
  `BookNoteRepositoryInterface`/`MySqlBookNoteRepository`. De `BookRepositoryInterface` sobrevive
  **un** método, `fetchAllowedStatuses()`, que lee `book_statuses` y sí existe.
- **Nunca apuntes el sembrado de test al MySQL de dev.** `docker/database/init.sql` empieza con
  `DROP DATABASE IF EXISTS library_db` y **el nombre de la base es el mismo** en dev y en test. El
  bootstrap tiene una lista blanca de hosts y aborta si `DB_TEST_HOST` no está en ella; no la quites.
- **El aislamiento entre tests de integración es truncando, no por transacción**: 16 ficheros de
  `src/` abren la suya y PDO no las anida.
- **Una migración nueva llega sola a la base de test, pero no siempre fue así.** Hasta el 2026-08-27,
  `tests/Integration/bootstrap.php` se saltaba el sembrado entero si la base ya tenía tablas, así que
  una migración recién escrita **nunca** llegaba a `mysql-test` y sus tests fallaban con «Table …
  doesn't exist» teniendo el SQL bien. Ahora lleva el mismo `schema_migrations` que
  `run_migrations.sh` y aplica solo las que falten; una base de la era anterior —sin ese registro— se
  **recrea entera**, porque sus migraciones están aplicadas sin registrar y no todas son
  idempotentes.
- **Vitest también dentro del contenedor**, y una devDependency nueva pide rebuild de la imagen.
- **La visibilidad de una lista se pregunta a `Domain/Services/ListAccess.php`, y a nada más.** Es la
  única copia de la regla y su tabla de verdad de 24 casos vive en su docblock, fijada por 29 tests.
  Lo **inyectan cuatro** use cases (`GetList`, `AddListItem`, `RemoveListItem`, `UpdateList`); las
  **once** operaciones que enumera su docblock son las que *necesitarían* la regla si no existiera
  —las otras siete la resuelven por otra vía, con `MediaList::isOwnedBy` o con el `WHERE` de la
  consulta—, no las que la llaman. Tres lecturas que se hacen mal: la **amistad no entra** (amigo y
  desconocido tienen permisos idénticos, y hay un test que lo afirma); `collaborative` **no es
  pública** y la tabla de colaboradores se consulta en las tres visibilidades; y **«editar» es el
  CONTENIDO** —`canEdit` gobierna añadir y quitar ítems, mientras que renombrar, cambiar visibilidad,
  borrar e invitar son del dueño (`MediaList::isOwnedBy`)—. `get_my_lists` y `get_user_lists` **no**
  pasan por ahí a propósito: su filtro es el `WHERE` de la consulta, y hacerlo en PHP significaría
  traerse las listas privadas de otro.
- **`Recommendation` tiene DOS constantes de tipo y confundirlas abre un agujero.**
  `MEDIA_ENTITY_TYPES` son los cinco medios y es contra lo que valida `send_recommendation`;
  `VALID_ENTITY_TYPES` añade `'list'` **y `'club'`** y es lo que la **columna** acepta, porque las
  invitaciones a colaborar y a entrar en un club viajan por el mismo buzón. Si `send_recommendation`
  validara contra la segunda, se podría «recomendar» una lista o un club como si fueran un ítem y la
  bandeja intentaría darlos de alta con el `enrich` de un medio inexistente. Van juntas por `get_inbox_count`, que es la acción más llamada de la app y
  tiene que seguir siendo un `COUNT(*)` que sale entero de `idx_inbox`.
- **Los clubs tienen CUATRO reglas con copia única, y ninguna vive en la plantilla.**
  `Domain/Services/ClubCompletion.php` decide cuándo se cierra solo el ítem —solo si **todos** los
  miembros lo completaron, con «todos» literal: quien no lo tiene en su biblioteca congela el cierre,
  y por eso `finish_club_pick` es la vía habitual y no la excepción—. Y
  `Domain/Services/SpoilerRule.php` decide si una nota te destriparía; lo importante no es la tabla
  de verdad sino que **con `isSpoiler: true` el `text` viaja como `null`**: difuminar con CSS un
  texto que está en el DOM es enseñarlo, y ninguna prueba visual lo detecta. `atPoint` sí viaja.
  Las otras dos son de la votación, y están abajo.
- **La votación son DOS servicios y no uno, y separarlos es lo que la hace comprobable.**
  `Domain/Services/ClubRoundResolver.php` es **lógica pura sin PDO** —rotación, cierre de fase y
  desempate—, y por eso su tabla de verdad (clubs de **1, 2, 3 y 8** miembros × los cuatro atascos
  posibles, en `tests/Unit/Domain/Services/ClubRoundResolverTest.php`) se validó **antes** de escribir
  el esquema. `Domain/Services/ClubRoundProgress.php` es lo que **escribe** con esa respuesta, y vive
  aparte porque lo consultan **dos** llamantes: `GetClubUseCase` y las dos válvulas del dueño. Tres
  reglas que se leen mal: la rotación «quien ganó no propone» **se salta si dejaría menos de dos
  proponentes** —sin eso, un club de dos muere en su segunda ronda y uno de uno no propone nunca
  más—; **ninguna fase avanza sin al menos un elemento**, ni forzándola por el dueño, porque abrir un
  voto vacío deja la ronda clavada un escalón más allá; y el `ballot` **nunca pasa de 2**, porque en
  el 2 siempre se cierra, por sorteo si hace falta.
- **El ganador del sorteo se ESCRIBE en `club_round.winning_proposal_id`, no se deduce.** La ronda se
  resuelve al leer el club, así que un ganador recalculado en cada `get_club` haría que dos miembros
  mirando a la vez vieran libros distintos. Las propuestas **eliminadas**, en cambio, sí se
  recalculan y no necesitan columna: el empate es el máximo de un recuento, que es determinista.
- **De la votación solo viajan el recuento y el voto propio.** Los pares usuario→propuesta ajenos no
  salen del servidor. Es el error gemelo del texto de la nota con spoiler: lo que está en el DOM está
  enseñado, por mucho que la plantilla no lo pinte.
- **`get_club` ESCRIBE, y es a propósito, y desde la votación escribe MUCHO más.** El proyecto no
  tiene cron ni workers (`Infrastructure/Http/PostResponse.php:12`), así que al leer el club se
  evalúan el cierre automático del ítem **y** todo el avance de la ronda: abrirla, abrir el voto
  cuando han propuesto todos, subir el `ballot` del desempate, y cerrarla creando el `club_pick`
  ganador. Engancharlo en los cinco `Update<Medio>UserStatusesUseCase` serían cinco copias de la
  regla. Todas las escrituras van condicionadas en el `WHERE` —`AND finished_at IS NULL`,
  `AND phase = 'proposing'`, `AND phase <> 'closed'`— para que dos lecturas simultáneas no lo hagan
  dos veces, y **ningún fallo suyo puede tumbar la lectura**: un club que no se pinta es peor que uno
  que avanza en la siguiente visita.
- **Abrir la ronda es idempotente o se abren N, y «comprobar antes de insertar» NO basta**: entre el
  `SELECT` y el `INSERT` cabe la petición de la otra pestaña. `MySqlClubRoundRepository::openIfNone()`
  lo hace con `INSERT … SELECT … WHERE NOT EXISTS` en una sola sentencia **y relee siempre**, para que
  quien pierda la carrera use la ronda del otro; `lastInsertId()` daría 0 en ese caso. MySQL no tiene
  índices parciales, así que un `UNIQUE` sobre «abierta» no se puede escribir.
- **`propose_club_item` NO abre la ronda**, la abre `get_club`. Proponer sin haber leído el club da
  **409**, y es correcto: abrirla también ahí significaría copiar la regla de cuándo toca —«no hay
  ítem activo»— a un segundo sitio. Los tests de integración entran como el cliente real, leyendo
  antes de proponer.
- **Las cinco tablas `user_*_notes` NO comparten forma.** `user_edition_notes` cuelga de
  `user_edition_id` (indirecto, vía `user_book_editions`) y es la única con `page_number` real;
  `user_movie_notes` lo tiene pero es `NULL` y **no significa nada**; `user_game_notes`,
  `user_album_notes` y `user_video_notes` **no tienen la columna**. Un `SELECT … page_number`
  genérico sobre las cinco revienta en tres. Y por eso **solo los libros** tienen la regla de spoiler
  fina: las series tienen eje pero sus notas no tienen punto.
- **La interfaz habla dos idiomas, y el texto no se escribe en la plantilla.** El motor es propio
  (`src/config/i18n.js` + `composables/useI18n.js`), **no `vue-i18n`**: solo ocho sitios piden plural
  y es/en son ambos de dos formas. Los catálogos son `src/locales/es.yaml` y `en.yaml` —**682 claves,
  simétricas**—, los compila `yaml-loader` en el build y entran por `import()` dinámico, así que en
  frío solo baja el idioma que se usa. **Vitest no pasa por los loaders de webpack** y necesita su
  propio `@rollup/plugin-yaml`: son **dos** paquetes, no uno. `vue/no-bare-strings-in-template` está
  en **`error`**, así que escribir texto suelto en una plantilla rompe el lint.
- **Lo que se importa antes de que cargue el catálogo va con GETTERS**, no con valores.
  `config/mediaRegistry.js` y `components/Lists/visibility.js` se evalúan al importarse: un valor se
  congelaría con el catálogo vacío y la app pintaría claves. Un getter se evalúa al leerlo y queda
  suscrito al `ref`, así que la interfaz cambia de idioma **sin recargar**. Hay un test que impide
  convertirlos en valores «para simplificar».
- **Un estado se traduce al PINTARLO, nunca antes.** `statusLabel()` (`config/i18n.js`) es la única
  copia: tolera las dos formas del backend (`'owned'` y el `{id, name}` de vídeos) y **cae al slug**
  si el catálogo no lo conoce, para que un estado nuevo no pinte `status.loquesea`. El agrupado, el
  filtrado y la comparación del dashboard siguen yendo **por slug**; traducir antes no rompe nada
  ruidosamente, solo deja de casar.
- **La regla de lint ve mucho menos de lo que parece, y por eso su config está escrita a mano.** Su
  opción `attributes` por defecto solo mira `title`, los cinco `aria-*`, el `placeholder` de `<input>`
  y el `alt` de `<img>`; declararla **reemplaza** al defecto, así que en `package.json` van repetidos
  esos y añadidos `label`, `placeholder` (en cualquier elemento), `message`, `subtitle`, `header`,
  `hint`, `empty-text`, `confirm-label` y `cancel-label`. Aun así **no** ve los literales dentro de
  `{{ ternario }}` ni de una plantilla literal, ni el sidebar —que vive en
  `public/config/sidebar-menu.json` y lleva **claves**, no texto—, ni **nada de lo que se pinta en
  `<canvas>`**: los rótulos de Chart.js de `services/StatsService.js` y
  `composables/useDashboardCharts.js` no están en el DOM. Eso solo lo ve una captura.
- **La regla de lint es la PRIMERA barrera, no la única.** En esta app la mayoría de las cadenas no
  estaban en la plantilla sino en stores, composables y servicios, y para eso está la segunda:
  `tests/unit/i18n.spec.js` recorre `src/` con `tests/unit/helpers/cadenas.js`, reparte cada literal
  en **interfaz / log / identificador** y **falla nombrando fichero y cadena** si aparece uno de
  interfaz fuera del catálogo. Clasifica por la **forma de la llamada**, no por listas: argumento de
  `Logger.*`, primer argumento de `apiCall`, argumento de `t()`, un import, el `name:` de una ruta
  dentro de `router/`, una clave de objeto, un operando de comparación, un `obj['clave']`, o el
  prefijo `[Módulo]` que el repo usa para los mensajes de quien depura. **Si escribes una cadena que
  no es interfaz, dale una de esas formas**; no la metas en
  `tests/unit/i18n-strings.allowlist.json`, que es un candado y no una lista de pendientes.
- **Los errores del backend se traducen por CÓDIGO, y su `message` no se enseña nunca.**
  `composables/useApiError.js` es la única copia —antes eran **cuatro**: `_messageFor` en
  `store/lists.js` y `store/clubs.js`, `handleStoreError` en `utils/storeHelpers.js` y `_handleError`
  en `store/sessions.js`—. La cadena es `claves[código]` del dominio → `errors.<código>` →
  `claves.defecto` → `errors.unknown`, y los valores de `claves` son **claves del catálogo, no
  textos**. El `message` del backend viaja al `Logger`, que es su sitio: llega en inglés y con la
  redacción de quien escribió el endpoint.
- **`err.message` SÍ se enseña, en unos veinte sitios.** `FriendsView.vue` lo mete en un toast y los
  stores lo devuelven como `message`, así que un `throw new Error('Failed to X')` acaba en pantalla:
  esos mensajes se traducen. Los que no se traducen son los de `Logger.*` y los guardas de
  programador, y se distinguen por llevar el prefijo `[Módulo]`.
- **Las fechas y los números van por `intlLocale()`** (`config/i18n.js`), que da la etiqueta
  **BCP-47** del idioma activo. **No es el código del catálogo**: `Intl` quiere `es-ES` / `en-GB`, y
  pasarle `'es'` a secas funciona por casualidad. No debe volver a aparecer un `'es-ES'` escrito a
  mano — con la app en inglés el historial decía «29 de agosto de 2026 a las 15:33», y eso **no lo ve
  ninguna barrera** porque no es una cadena.
- **`defineProps()` se iza fuera de `setup()`**, así que el valor por defecto de una prop **no puede
  leer** el `t` de `useI18n()`: hay que importar `t` de `@/config/i18n`, que sí se iza. Y una clave
  compuesta con plantilla (`t(\`bloque.${x}\`)`) se escapa de la barrera, que solo ve `t('literal')`.
- **Ojo con `no`, `yes`, `on` y `off` como claves del catálogo.** YAML 1.1 los lee como booleanos: el
  código ISO del noruego es `no` y la clave se convertía en `language.False`. Van entrecomillados.
- **Y si vas a verificar traducciones en el navegador, navega por rutas, no por hash.**
  `router/index.js:189` usa `createWebHistory` salvo en móvil: con `#/library` el documento carga
  pero el router se queda en `/`. Pasó el 2026-08-31 — **32 capturas de la misma pantalla de inicio**
  y el informe en verde. El criterio que sí funciona es cruzar las dos pasadas: lo que sale idéntico
  en español y en inglés es o un dato o algo sin traducir.
- **Un medio nuevo se declara en `mediaRegistry`**, no se copia el componente del medio de al lado.
- **Ni un hex ni un `px` de breakpoint fuera de su sitio**: el color va a `tokens/_colors.scss` /
  `themes/_dark.scss` y los umbrales a `abstracts/_breakpoints.scss`. `stylelint` lo comprueba.
- **Trabaja en la rama `dev`** en este checkout; los cambios a producción se promueven a `master`
  y se despliegan desde `libraryVue_prod` con `docker-compose.prod.yml`.

## Verificación end-to-end

1. `docker compose up --build`; `POST http://localhost:8888/index.php` con `{"action":"ping"}`.
2. Busca un libro/película, guárdalo en la biblioteca, comprueba la ficha y el dashboard de stats.
3. `docker compose exec backend composer test` → verde (1472 tests: 1279 unitarios + 193 de
   integración; estos necesitan `docker compose --profile test up -d mysql-test`).
4. `docker compose exec frontend npm test` → verde (514 tests) y
   `docker compose exec frontend npm run lint:styles` → sin salida.
5. **`docker compose exec frontend npm run build` → `Build complete`.** No es redundante con el paso
   anterior: **ninguno de los tres comandos de arriba compila SCSS**. Los helpers de
   `assets/styles/abstracts` validan sus argumentos y **abortan la compilación** —`spacing(xxs)`
   revienta con *«Spacing `xxs` no existe»*, porque la escala es `3xs, 2xs, xs, sm, md, lg, xl, 2xl,
   3xl`—, pero eso solo ocurre al construir: stylelint no resuelve funciones de Sass, ESLint no mira
   los `<style>` y Vitest corre en jsdom, que no evalúa CSS. Pasó el 2026-08-25: plan cerrado con las
   tres verdes y el build roto.
6. **Si el cambio toca layout, `cd frontend && LIBRARYVUE_JWT=<token> npm run test:responsive`**
   (en el host). Recorre las rutas a 360 px y falla si algún elemento se sale del viewport o si una
   ruta no se pudo medir. No mide `scrollWidth`: `base/_reset.scss:15` pone
   `html, body { overflow-x: hidden }`, así que el documento nunca genera scroll horizontal y ese
   criterio daba verde con `/library` dejando 24 elementos fuera.
   **Lee la salida, no solo el exit code**, y separa sus dos cuentas: la de *elementos fuera* es la
   que juzga tu cambio; la de *rutas saltadas* la gobierna el rate limit de 60 req/min —un recorrido
   gasta ~44 peticiones—, así que dos pasadas seguidas dejan a la segunda sin cuota y sus rutas
   privadas salen como saltadas sin que nada esté roto. Espacia las pasadas: **un ancho por
   invocación**, no `--width=360,390`, que son dos recorridos seguidos y el segundo sale sin cuota.
   Y **su lista de rutas privadas está escrita a mano** (`tests/visual/overflow.mjs:156-172`): una
   ruta nueva que no se añada ahí no se mide, y el informe sale «verde» igualmente. Es el mismo
   verde engañoso que el fichero documenta sobre `scrollWidth`. Pasó con `/journal` el 2026-09-04.
7. **Si el cambio se ve en pantalla, ábrelo en el navegador**, y no solo por las capturas: hay una
   clase entera de fallos que **solo aparece en la consola**. `v-tooltip` estuvo sin registrar en
   `main.js` desde el 2026-05-13 y nadie lo vio en tres meses, porque un
   `Failed to resolve directive` no rompe nada — Vue avisa y sigue. Procedimiento con Firefox y
   geckodriver en `.github/skills/frontend.md` → *Visual Verification (headless screenshots)*; para
   los warnings, engancha `console.warn` antes de navegar y navega **dentro** de la SPA, o el hook se
   pierde con la recarga.

   > ⚠️ **Al acabar, mata el proceso entero: las dos cosas.** `DELETE $B` cierra la *sesión* y su
   > Firefox, pero **deja geckodriver escuchando en 4444 para siempre**; y una sesión que no se borra
   > deja además un `firefox -headless` vivo. Ninguno de los dos aparece en una ventana, así que se
   > acumulan en silencio de sesión en sesión. `curl -s -X DELETE $B` **y**
   > `pkill -f 'geckodriver --port 4444'`, y verifica con `pgrep -a geckodriver; pgrep -a firefox` —
   > lo que lleve horas de `ELAPSED` es el navegador de David, no lo mates—. Bajo snap el `kill`
   > puede dar `Permission denied` aun siendo el mismo usuario: entonces no se puede desde aquí y hay
   > que decírselo a David.

> ℹ️ **Desde el 2026-08-25 hay dos suites de verdad.** `composer test` corre las dos;
> `composer test:unit` es la rápida y **no necesita** `mysql-test`. La suite `Integration` estuvo
> declarada sobre un directorio inexistente hasta el 2026-08-24, haciendo abortar a PHPUnit con
> `error code 2`; se retiró entonces y se repuso ahora sobre un directorio con contenido.
