#!/usr/bin/env node
/**
 * Barrera de desbordamiento horizontal — recorre las rutas de la SPA en un viewport
 * estrecho y falla si algún elemento se sale del viewport.
 *
 * POR QUÉ NO MIRA `scrollWidth`: `assets/styles/base/_reset.scss` declara
 * `html, body { overflow-x: hidden }`, así que el documento NUNCA genera scroll
 * horizontal. Medido el 2026-08-29 en `/library` a 360px: `scrollWidth === innerWidth`
 * con 24 elementos fuera del viewport y los controles inalcanzables. Una barrera que
 * comparase `scrollWidth` con `innerWidth` nacería verde con la pantalla rota.
 * Lo que se mide es `getBoundingClientRect().right > clientWidth`, elemento a elemento.
 *
 * POR QUÉ NO CORRE EN DOCKER: necesita Firefox y geckodriver, que están en el host
 * (snap). Los tests de Vitest sí van dentro del contenedor; este no.
 *
 * POR QUÉ MIDE CON EL MOVIMIENTO APAGADO: una animación que desplaza la caja de un
 * elemento lo mete y lo saca del viewport varias veces por segundo, y como aquí se
 * mide `getBoundingClientRect()` —que incluye el desplazamiento— el resultado dependía
 * del fotograma. Pasó de verdad: `.progress-bar-shine` animaba `left: -100% → 100%` y
 * `/books/:isbn` salía `right=366` en una pasada y `ok` en la siguiente (Roadmap #21).
 * La sesión arranca con `ui.prefersReducedMotion: 1`, que dispara el bloque de
 * `assets/styles/base/_globals.scss`: la app se queda quieta y la barrera repite.
 * Es la MISMA preferencia que respeta la app para quien la pide al sistema, no un
 * truco del test — y el iframe la hereda, comprobado.
 *
 * POR QUÉ UN IFRAME POR DEBAJO DE 500px: Firefox no acepta ventanas de menos de 500px
 * CSS — `POST /window/rect` con `width: 360` devuelve 200 y deja la ventana en 500, y
 * `MOZ_HEADLESS_WIDTH` tampoco sirve. La página va dentro de un iframe del ancho pedido,
 * escrito con `document.write` sobre el propio origen para que el hijo NO quede
 * particionado y siga viendo el `jwt_token` de `localStorage`.
 *
 *   node tests/visual/overflow.mjs [--width=360,390] [--url=http://127.0.0.1:8080]
 *                                 [--api=http://127.0.0.1:8888] [--jwt=<token>]
 *                                 [--driver=4455] [--verbose]
 *
 * El token también se lee de la variable de entorno LIBRARYVUE_JWT, que es la vía
 * recomendada: un `--jwt=` queda escrito en el historial del shell.
 *
 * Salida 0 solo si TODAS las rutas del recorrido se midieron y ninguna dejó elementos
 * fuera. Una ruta saltada también devuelve 1: no medida no es verde, y darla por buena
 * sería la misma trampa que medir `scrollWidth` sobre un `overflow-x: hidden`. Las que
 * no se pueden construir por falta de datos en la BD se avisan aparte y no cuentan como
 * fallo: son una limitación del entorno, no del código. Sin token recorre solo las
 * rutas públicas y avisa de las que se salta.
 */
import { spawn } from 'node:child_process';
import process from 'node:process';

const arg = (name, fallback) => {
  const hit = process.argv.slice(2).find(a => a.startsWith(`--${name}=`));
  return hit ? hit.slice(name.length + 3) : fallback;
};
const flag = name => process.argv.slice(2).includes(`--${name}`);

const WIDTHS = arg('width', '360').split(',').map(w => parseInt(w, 10));
const BASE = arg('url', 'http://127.0.0.1:8080').replace(/\/$/, '');
const API = arg('api', 'http://127.0.0.1:8888');
const JWT = arg('jwt', process.env.LIBRARYVUE_JWT || '');
const PORT = parseInt(arg('driver', '4455'), 10);
const VERBOSE = flag('verbose');
const DRIVER = `http://127.0.0.1:${PORT}`;

// `localhost` no vale: la API vive en otro puerto y el navegador rechazaría la cookie
// LIBRARY_SESSION por cross-site. Con 127.0.0.1 en ambos, no hay sorpresa.
if (BASE.includes('localhost')) {
  console.warn('⚠ Usa 127.0.0.1 y no localhost: con localhost el navegador rechaza la cookie de sesión.\n');
}

// ---------------------------------------------------------------- WebDriver

const wd = async (method, path, body) => {
  const res = await fetch(DRIVER + path, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) {
    const err = json?.value?.message || res.statusText;
    throw new Error(`WebDriver ${method} ${path}: ${err}`);
  }
  return json.value;
};

const waitForDriver = async (ms = 15000) => {
  const until = Date.now() + ms;
  while (Date.now() < until) {
    try {
      const s = await fetch(`${DRIVER}/status`).then(r => r.json());
      if (s?.value?.ready !== undefined) return true;
    } catch { /* aún no escucha */ }
    await sleep(250);
  }
  return false;
};

const sleep = ms => new Promise(r => setTimeout(r, ms));

// ---------------------------------------------------------------- rutas

// Las rutas con `:param` se resuelven contra la API con el token: cablear ids de la
// base de dev haría fallar el script en cualquier otro entorno. Una ruta cuyo id no
// exista se salta con aviso, no se da por buena.
const api = async action => {
  if (!JWT) return null;
  try {
    const res = await fetch(`${API}/`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${JWT}` },
      body: JSON.stringify({ action }),
    });
    const json = await res.json();
    return json.status === 'success' ? json.data : null;
  } catch {
    return null;
  }
};

// El backend limita a 60 peticiones por minuto y por IP (RATE_LIMIT_MAX_REQUESTS), y
// lo aplica a TODA ruta que no declare lo suyo — `ActionRouter.php:183`. Un recorrido
// de 23 rutas gasta esa cuota a mitad de camino: `check_auth` empieza a devolver 429,
// el front lo lee como sesión caída y el guard manda a Home. Las rutas del final
// salían entonces como "redirigida a Home", que es un falso negativo, no un desborde.
// Antes de reintentar se espera a que la ventana se renueve, con el Retry-After que
// el propio backend envía.
const esperarCuota = async () => {
  if (!JWT) return;
  for (let intento = 0; intento < 3; intento++) {
    let res;
    try {
      res = await fetch(`${API}/`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${JWT}` },
        body: JSON.stringify({ action: 'check_auth' }),
      });
    } catch {
      return;
    }
    if (res.status !== 429) return;
    const espera = (parseInt(res.headers.get('Retry-After') || '0', 10) || 60) + 1;
    console.log(`  … cuota del rate limit agotada, esperando ${espera}s a que se renueve`);
    await sleep(espera * 1000);
  }
};

const buildRoutes = async () => {
  const publicas = [
    ['/', 'Home'],
    ['/books', 'Buscar libros'],
    ['/movies', 'Buscar películas'],
    ['/games', 'Buscar videojuegos'],
    ['/albums', 'Buscar álbumes'],
    ['/esta-ruta-no-existe', 'NotFound'],
  ].map(([path, nombre]) => ({ path, nombre, auth: false }));

  const privadas = [
    ['/search', 'Buscador general'],
    ['/library', 'Mi biblioteca'],
    ['/dashboard?tab=books', 'Dashboard · libros'],
    ['/dashboard?tab=movies', 'Dashboard · películas'],
    ['/dashboard?tab=games', 'Dashboard · videojuegos'],
    ['/dashboard?tab=albums', 'Dashboard · álbumes'],
    ['/dashboard?tab=videos', 'Dashboard · vídeos'],
    ['/videos', 'Buscar vídeos'],
    ['/profile', 'Mi perfil'],
    ['/friends', 'Amigos y feed'],
    ['/lists', 'Listas'],
    ['/clubs', 'Clubs'],
    ['/inbox', 'Bandeja'],
  ].map(([path, nombre]) => ({ path, nombre, auth: true }));

  const detalle = [];
  const sinDatos = [];
  const añade = (path, nombre) => detalle.push({ path, nombre, auth: true });

  const lib = await api('get_library_items');
  const libro = lib?.books?.[0];
  if (libro?.isbn) añade(`/books/${libro.isbn}`, 'Ficha de libro');
  for (const peli of lib?.movies || []) {
    const id = peli.isbn || peli.imdbID || peli.imdb_id;
    if (!id) continue;
    const esSerie = (peli.media_type || peli.mediaType) === 'series';
    añade(`${esSerie ? '/series' : '/movies'}/${id}`, esSerie ? 'Ficha de serie' : 'Ficha de película');
  }
  if (!libro) sinDatos.push('/books/:isbn — no hay libros en la biblioteca');
  if (!(lib?.movies || []).some(m => (m.media_type || m.mediaType) === 'series')) {
    sinDatos.push('/series/:imdbId — no hay ninguna serie en la biblioteca');
  }
  const juego = (await api('get_games'))?.[0];
  if (juego) añade(`/games/${juego.slug || juego.id}`, 'Ficha de videojuego');
  else sinDatos.push('/games/:gameId — no hay videojuegos');
  const album = (await api('get_albums'))?.[0];
  if (album) añade(`/albums/${album.spotify_id || album.spotifyId || album.id}`, 'Ficha de álbum');
  else sinDatos.push('/albums/:albumId — no hay álbumes');
  const video = (await api('get_videos'))?.[0];
  if (video) añade(`/videos/${video.youtube_id || video.youtubeId || video.id}`, 'Ficha de vídeo');
  else sinDatos.push('/videos/:youtubeId — no hay vídeos');
  const lista = (await api('get_my_lists'))?.lists?.[0];
  if (lista) añade(`/lists/${lista.id}`, 'Detalle de lista');
  else sinDatos.push('/lists/:listId — no hay listas');
  const club = (await api('get_my_clubs'))?.clubs?.[0];
  if (club) añade(`/clubs/${club.id}`, 'Detalle de club');
  else sinDatos.push('/clubs/:clubId — no hay clubs');
  const yo = (await api('check_auth'))?.user;
  if (yo?.username) añade(`/user/${yo.username}`, 'Perfil público');
  else sinDatos.push('/user/:username — el usuario no tiene `username`');

  return { publicas, privadas, detalle, sinDatos };
};

// ---------------------------------------------------------------- medición

// Se ejecuta dentro del navegador. `w` es `window`, o el `contentWindow` del iframe.
const MEDIR = `
  var w = arguments[0] || window, d = w.document;
  var limite = d.documentElement.clientWidth;
  function nombre(e) {
    return e.tagName.toLowerCase() +
      (e.id ? '#' + e.id : '') +
      (typeof e.className === 'string' && e.className
        ? '.' + e.className.trim().split(/\\s+/).join('.') : '');
  }
  // Un carrusel se sale del viewport A PROPÓSITO: sus ítems viven dentro de un
  // ancestro que los desplaza. Eso no es un bug, porque se puede llegar a ellos.
  // Solo cuentan como contención los overflow-x auto y scroll, NO hidden: un hidden
  // recorta sin dejar llegar, y eso es precisamente lo que este script busca — es el
  // caso del p-tablist del dashboard, que esconde tres de los cinco tabs y los deja
  // inalcanzables. El body queda fuera del recorrido a posta: su overflow-x hidden
  // de base/_reset.scss es el que esconde el desborde global.
  function contenidoPorUnScroller(e) {
    for (var p = e.parentElement; p && p !== d.body; p = p.parentElement) {
      var ox = w.getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll') return true;
    }
    return false;
  }
  var fuera = [].slice.call(d.querySelectorAll('*'))
    .filter(function (e) {
      return e.getBoundingClientRect().right > limite + 1 && !contenidoPorUnScroller(e);
    })
    .sort(function (a, b) {
      return b.getBoundingClientRect().right - a.getBoundingClientRect().right;
    });
  return {
    viewport: limite,
    url: d.location.pathname + d.location.search,
    total: fuera.length,
    peores: fuera.slice(0, 5).map(function (e) {
      var r = e.getBoundingClientRect();
      return { sel: nombre(e), right: Math.round(r.right), ancho: Math.round(r.width) };
    })
  };
`;

// El DOM se da por asentado cuando dos muestras seguidas cuentan los mismos nodos.
// Es más fiable que un `sleep` fijo: las vistas de detalle esperan a OMDb, IGDB o
// Spotify, y las de búsqueda no esperan a nada.
const ESTABLE = `
  var cb = arguments[arguments.length - 1];
  var sonda = document.getElementById('sonda');
  var d = sonda ? sonda.contentWindow.document : document;
  var previo = -1, iguales = 0, vueltas = 0;
  (function mira() {
    var n = d.querySelectorAll('*').length;
    iguales = (n === previo) ? iguales + 1 : 0;
    previo = n;
    if (iguales >= 2 || ++vueltas > 24) return cb(n);
    setTimeout(mira, 400);
  })();
`;

const run = async (sid, script, args = [], async_ = false) =>
  wd('POST', `/session/${sid}/execute/${async_ ? 'async' : 'sync'}`, { script, args });

const irA = async (sid, url) => wd('POST', `/session/${sid}/url`, { url });

/** Mide una ruta en la propia ventana (anchos de 500px o más). */
const medirEnVentana = async (sid, ruta) => {
  await irA(sid, BASE + ruta.path);
  await run(sid, ESTABLE, [], true);
  return run(sid, MEDIR, [null]);
};

/** Mide una ruta dentro de un iframe del ancho pedido (anchos menores de 500px). */
const medirEnIframe = async (sid, ruta, ancho) => {
  await irA(sid, BASE + '/');
  // Asentar la Home ANTES de escribir encima: si `document.write` cae mientras Vue
  // aún está montando, el iframe nace a medias y su router acaba redirigiendo a Home.
  await run(sid, ESTABLE, [], true);
  await run(sid, `
    document.open();
    document.write(
      '<body style="margin:0;overflow:hidden">' +
      '<iframe id="sonda" src="' + arguments[0] + '" ' +
      'style="width:' + arguments[1] + 'px;height:1200px;border:0"></iframe></body>'
    );
    document.close();
    return true;
  `, [BASE + ruta.path, ancho]);
  await run(sid, `
    var cb = arguments[arguments.length - 1];
    var f = document.getElementById('sonda');
    if (f.contentDocument && f.contentDocument.readyState === 'complete') return cb(true);
    f.addEventListener('load', function () { cb(true); });
  `, [], true);
  // El mismo asentado de siempre, pero mirando el documento del iframe.
  await run(sid, ESTABLE, [], true);
  return run(sid, `
    var f = document.getElementById('sonda');
    return (function () { ${MEDIR.replace('arguments[0] || window', 'f.contentWindow')} })();
  `, []);
};

// ---------------------------------------------------------------- principal

let driverPropio = null;
let sid = null;

const main = async () => {
  if (!(await waitForDriver(1000))) {
    console.log(`Arrancando geckodriver en el puerto ${PORT}…`);
    driverPropio = spawn('geckodriver', ['--port', String(PORT)], { stdio: 'ignore' });
    driverPropio.on('error', () => {
      console.error('✖ No se encontró `geckodriver`. Se instala con snap, junto a Firefox.');
      process.exit(2);
    });
    if (!(await waitForDriver())) {
      console.error(`✖ geckodriver no responde en ${DRIVER}`);
      process.exit(2);
    }
  } else if (VERBOSE) {
    console.log(`Reutilizando el geckodriver que ya escucha en ${DRIVER}`);
  }

  const { publicas, privadas, detalle, sinDatos } = await buildRoutes();
  const rutas = JWT ? [...publicas, ...privadas, ...detalle] : publicas;

  // Una ruta de detalle sin dato con el que construirse no es una ruta verde: es una
  // ruta no medida, y callárselo haría que la barrera pareciera cubrir más de lo que cubre.
  if (sinDatos?.length) {
    console.log(`⚠ ${sinDatos.length} rutas de detalle NO se recorren, por falta de datos en esta BD:`);
    for (const q of sinDatos) console.log(`    ${q}`);
    console.log('');
  }

  if (!JWT) {
    console.log('⚠ Sin token: se recorren solo las 6 rutas públicas.');
    console.log('  Se saltan las 12 privadas y las fichas de detalle. Pasa --jwt=<token>');
    console.log('  o exporta LIBRARYVUE_JWT para recorrerlas todas.\n');
  }

  sid = (await wd('POST', '/session', {
    capabilities: {
      alwaysMatch: {
        browserName: 'firefox',
        'moz:firefoxOptions': {
          args: ['-headless', '-width', '1400', '-height', '1300'],
          prefs: {
            // Sin esto, Firefox particiona el localStorage del iframe y las rutas con
            // `requiresAuth` redirigen a Home sin dar error: la barrera mediría la Home.
            'privacy.partition.always_partition_third_party_non_cookie_storage': false,
            // Movimiento reducido: ver el tercer POR QUÉ de la cabecera.
            'ui.prefersReducedMotion': 1,
          },
        },
      },
    },
  })).sessionId;

  if (JWT) {
    await irA(sid, BASE + '/');
    await run(sid, 'localStorage.setItem("jwt_token", arguments[0]); return true;', [JWT]);
  }

  let fallos = 0;
  let saltadas = 0;

  for (const ancho of WIDTHS) {
    console.log(`\n━━ ${ancho} px ${'━'.repeat(Math.max(0, 56 - String(ancho).length))}`);
    const estrecho = ancho < 500;
    await wd('POST', `/session/${sid}/window/rect`,
      estrecho ? { width: 900, height: 1300 } : { width: ancho, height: 1300 });

    for (const ruta of rutas) {
      // El guard del router manda a Home lo que no esté autenticado, y medir la Home
      // creyendo que es `/inbox` daría un verde falso. Una redirección se reintenta
      // una vez antes de darla por buena: la primera puede ser una carrera de arranque.
      const pedida = ruta.path.split('?')[0];
      const llegó = r => !ruta.auth || pedida === '/' || r.url.startsWith(pedida);

      let r;
      try {
        r = estrecho ? await medirEnIframe(sid, ruta, ancho) : await medirEnVentana(sid, ruta);
        if (!llegó(r)) {
          await esperarCuota();
          r = estrecho ? await medirEnIframe(sid, ruta, ancho) : await medirEnVentana(sid, ruta);
        }
      } catch (e) {
        console.log(`  ERROR   ${ruta.path.padEnd(26)} ${e.message}`);
        fallos++;
        continue;
      }

      if (!llegó(r)) {
        console.log(`  SALTADA ${ruta.path.padEnd(26)} redirigida a ${r.url} tras dos intentos ` +
          `(¿token caducado, o el recurso ya no existe?)`);
        saltadas++;
        continue;
      }

      if (r.total === 0) {
        console.log(`  ok      ${ruta.path.padEnd(26)} ${ruta.nombre}`);
      } else {
        fallos++;
        const peor = r.peores[0];
        console.log(`  FAIL    ${ruta.path.padEnd(26)} ${r.total} fuera  ` +
          `peor: ${peor.sel} right=${peor.right} (viewport ${r.viewport})`);
        for (const p of r.peores.slice(1)) {
          console.log(`          ${' '.repeat(26)} · ${p.sel} right=${p.right}`);
        }
      }
    }
  }

  console.log(`\n${'─'.repeat(64)}`);
  console.log(`${rutas.length} rutas × ${WIDTHS.length} ancho(s) · ` +
    `${fallos} con elementos fuera · ${saltadas} saltadas` +
    (sinDatos?.length ? ` · ${sinDatos.length} no medidas por falta de datos` : ''));
  if (saltadas > 0) {
    console.log('✖ Hay rutas que no se pudieron medir: eso no es un verde.');
  }
  return fallos === 0 && saltadas === 0 ? 0 : 1;
};

let codigo = 1;
try {
  codigo = await main();
} catch (e) {
  console.error(`✖ ${e.message}`);
  codigo = 2;
} finally {
  // Cerrar la sesión mata su Firefox; matar el driver es aparte, y solo si es nuestro.
  // Un geckodriver que ya estaba escuchando se deja como estaba.
  if (sid) await wd('DELETE', `/session/${sid}`).catch(() => {});
  if (driverPropio) driverPropio.kill();
  else if (VERBOSE) console.log('El geckodriver reutilizado sigue vivo: `pgrep -a geckodriver`.');
}
process.exit(codigo);
