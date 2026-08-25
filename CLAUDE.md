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
docker compose up --build

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
./mirror-sync.sh --covers   # siembra y baja las portadas pendientes de la biblioteca

# Tests backend (PHPUnit 11, dentro del contenedor backend)
docker compose exec backend composer test        # los 1170 tests
docker compose exec backend composer test:unit   # solo la suite Unit (hoy son lo mismo)

# Tests frontend (Vitest 3, dentro del contenedor frontend)
docker compose exec frontend npm test            # 199 tests
docker compose exec frontend npm run test:watch
docker compose exec frontend npx vue-cli-service lint --no-fix   # lo corre también ./dev-setup.sh
docker compose exec frontend npm run lint:styles                 # stylelint; también en ./dev-setup.sh

# Frontend / móvil (Capacitor)
cd frontend && npm run cap:sync && npm run build:mobile
```

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
  `Library` / `LibraryX` (colección del usuario), `Feed` + `Social` (feed social), `Stats`, `Auth`.
  Extienden `BaseController` (`successResponse`/`errorResponse`).
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

- **Ningún servicio construye su propio cliente HTTP.** `Infrastructure/Http/HttpClientFactory` es la
  única fábrica, y `grep -rn "new Client(" backend/src` debe seguir devolviendo **solo** ese fichero.
  Da un `HandlerStack` con reintento en **dos perfiles**: `PROFILE_WEB` (2 intentos, 250 ms de
  backoff, tope 1 s) para lo que corre dentro de una petición, y `PROFILE_BATCH` (5 intentos,
  exponencial 1-16 s, tope 60 s) para `bin/mirror` y el trabajo diferido. El `User-Agent` es un
  parámetro **obligatorio**, no un default: MusicBrainz rechaza al que no se identifica.
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
  servidor que atiende a producción. Lo levanta todo `./mirror-sync.sh --bootstrap`, y
  `prod-deploy.sh` se **niega a desplegar** si no lo encuentra (`check_mirror`).
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
- **En el frontend se aplica a lo que está guardado, no a lo que se busca.** Sus tres consumidores
  son `MediaListItem` y `LibraryMediaItem` —la lista y la parrilla de `/library`— y
  `views/shared/MediaDetailView`, la ficha de detalle. Búsqueda y carruseles quedan fuera: ahí el
  ítem no está guardado, no tiene fila, y su portada debe seguir viniendo del CDN. El `<img>` lleva
  `@error` que cae a la URL remota.
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
  suelto, el `px` dentro de un `@media`, `prefers-color-scheme` y `@import`. Dos matices que se olvidan: las
  superposiciones sobre carátula (`--color-overlay-strong`, `--color-on-overlay`,
  `--color-rating-star`, `--color-media-letterbox`) **no** conmutan con el tema —van sobre una
  portada arbitraria—, y `--color-on-status` **sí**, porque es la tinta que acompaña a un relleno
  semántico y en oscuro esos rellenos son claros.
- **Los acentos de entidad se validan con un script, no se eligen a ojo.** Los cinco
  `--color-card-<medio>-accent` pasan las cinco comprobaciones del validador de la skill `dataviz`
  **en modo `--pairs all`** —no adyacente: en `/library` los cinco medios conviven mezclados, así que
  cualquier par puede ser vecino—. Si tocas uno, revalida los cinco contra las dos superficies.
- **El umbral de móvil está en `composables/useBreakpoint.js`**, con un único listener de `resize`
  compartido, y su `isMobile` es `< 768` porque `responsive-below(md)` compila a `max-width: 767px`.
  En SCSS no se escriben píxeles en un `@media`: se usan `responsive()` / `responsive-below()` de
  `abstracts/_breakpoints.scss`.
- **Las gráficas no eligen color: lo leen.** `config/chartTheme.js` es la única fuente
  (`entityColor`, `categoricalPalette`, `chartInk`, `chartTooltip`); `StatsService.generateColors()`
  ya no existe. Chart.js pinta en `<canvas>` y no entiende `var()`, así que el módulo expone un `ref`
  que todas sus funciones tocan: cualquier `computed` que las llame repinta solo al cambiar de tema.
- **Lo que varía por medio se declara, no se copia.** `src/config/mediaRegistry.js` es la única
  descripción de lo que diferencia a un medio, y de ella se configuran **cinco genéricos**:
  `GenericSearch` (los cinco `*Search.vue`), `MediaNotes` + `useMediaNotes` (los `*Notes.vue`),
  `MediaListItem` (los `*ListItem.vue`), `shared/LibraryMediaItem` (los `Library*Item.vue`) y
  `views/shared/MediaDetailView` (las seis `*DetailView.vue`); además, `store/createMediaStore.js`
  genera los cinco stores de Pinia. Los ficheros por medio siguen existiendo como wrappers, así que
  **ningún import cambió**: la factoría genera hasta los alias con nombre de medio (`fetchVideos`,
  `getVideoByYouTubeId`…).
  **Antes de duplicar algo para un medio nuevo, mira si su familia ya tiene genérico.**
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

### Base de datos
Esquema y seed en `docker/database/init.sql` (solo en BD virgen). Cambios posteriores → archivos
datados en `docker/database/migrations/` aplicados a mano.

## Variables de entorno (`.env`)

DB: `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `DB_PASSWORD`.
APIs: `GOOGLE_CLIENT_ID`, `GOOGLE_BOOKS_API_KEY`, `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`,
`LASTFM_API_KEY`, `YOUTUBE_API_KEY`, `TMDB_API_KEY` (IGDB usa las de Spotify/Twitch).
Mirror de catálogos: `DB_MIRROR_DATABASE`, `DB_MIRROR_IMPORT_USER`, `DB_MIRROR_IMPORT_PASSWORD`.
**No subas secretos**: `.env` está gitignored y contiene claves reales.

## Buenos comportamientos en este repo

- **Endpoint = tres sitios coherentes** (routes, match/getController, controller).
- **Depende de interfaces de repositorio**; registra los nuevos en `config/container.php`.
- **PHPUnit dentro del contenedor**; warnings hacen fallar la suite.
- **Vitest también dentro del contenedor**, y una devDependency nueva pide rebuild de la imagen.
- **Un medio nuevo se declara en `mediaRegistry`**, no se copia el componente del medio de al lado.
- **Ni un hex ni un `px` de breakpoint fuera de su sitio**: el color va a `tokens/_colors.scss` /
  `themes/_dark.scss` y los umbrales a `abstracts/_breakpoints.scss`. `stylelint` lo comprueba.
- **Trabaja en la rama `dev`** en este checkout; los cambios a producción se promueven a `master`
  y se despliegan desde `libraryVue_prod` con `docker-compose.prod.yml`.

## Verificación end-to-end

1. `docker compose up --build`; `POST http://localhost:8888/index.php` con `{"action":"ping"}`.
2. Busca un libro/película, guárdalo en la biblioteca, comprueba la ficha y el dashboard de stats.
3. `docker compose exec backend composer test` → verde (1170 tests).
4. `docker compose exec frontend npm test` → verde (199 tests) y
   `docker compose exec frontend npm run lint:styles` → sin salida.
5. Si el cambio se ve en pantalla, **haz capturas**: jsdom no evalúa CSS. Procedimiento con Firefox y
   geckodriver en `.github/skills/frontend.md` → *Visual Verification (headless screenshots)*.

> ℹ️ **`composer test` y `composer test:unit` ejecutan hoy lo mismo**, y es intencionado.
> `phpunit.xml` declaraba una testsuite `Integration` sobre un `tests/Integration` inexistente que
> hacía abortar a PHPUnit con `error code 2`; se retiró el 2026-08-24. El día que haya suite de
> integración (Roadmap #5), `test` correrá las dos y `test:unit` seguirá sirviendo para la rápida.
