<template>
  <div class="search-view">
    <h1 class="search-view__title">
      {{ t('generalSearch.title') }}
    </h1>

    <div class="search-view__box">
      <input
        v-model="query"
        type="search"
        class="search-view__input"
        :placeholder="t('generalSearch.placeholder')"
        :aria-label="t('generalSearch.title')"
      >
    </div>

    <!-- Un filtro por medio, con el mismo aspecto que los de `/library`: la
         píldora sale de `components/_filter-pills.scss`, compartido con ella.
         Desmarcar un medio lo oculta Y deja de buscarlo. -->
    <div class="search-view__filtros">
      <label
        v-for="medio in mediaKeys"
        :key="medio"
        class="filter-checkbox-pill"
      >
        <input
          v-model="activos[medio]"
          type="checkbox"
        >
        <i
          :class="iconoDe(medio)"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ etiquetaDe(medio) }}</span>
      </label>
    </div>

    <!-- La franja de degradación cuenta SOLO los medios remotos que la declaran:
         lo local sale del mirror y no puede estar rancio. -->
    <StaleNotice
      v-for="aviso in avisos"
      :key="aviso.media"
      :stale="true"
      :cached-at="aviso.cachedAt"
      :provider="aviso.provider"
    />

    <p
      v-if="fallidos.length > 0"
      class="search-view__failed"
      role="status"
    >
      {{ t('generalSearch.failed', { medios: fallidos.join(', ') }) }}
    </p>

    <div
      v-if="resultados.length > 0"
      class="search-view__grid"
    >
      <MediaListItem
        v-for="entrada in resultados"
        :key="entrada.media + '-' + entrada.id"
        :media="entrada.media"
        :item="entrada.item"
        class="search-view__item"
        @click="irADetalle(entrada)"
      />
    </div>

    <!-- Los esqueletos van DEBAJO de lo ya pintado, no en su sitio: reservan el
         hueco de lo que falta para que la reordenación al llegar la red no
         sorprenda. Es lo que compra que se pueda reordenar la lista entera. -->
    <MediaSkeleton
      v-if="esperandoRemoto && hayActivo('remota')"
      variant="list-item"
      :count="5"
      :label="t('generalSearch.loadingRemote')"
    />

    <p
      v-if="sinResultados"
      class="search-view__empty"
    >
      {{ t('generalSearch.empty', { query: ultimaQuery }) }}
    </p>
  </div>
</template>

<script setup>
/**
 * El buscador general: una caja, los seis medios, una sola lista.
 *
 * **Las dos acciones se lanzan a la vez, no en cascada.** `search_catalog_local`
 * sale del mirror y vuelve en milisegundos; `search_catalog_remote` depende de
 * tres APIs. Encadenarlas costaría la suma y haría el buscador general más lento
 * que abrir la página del medio que te interesa. El ciclo es:
 *
 *   1. Se disparan las dos.
 *   2. Llega la local: se pinta la lista ya ordenada, con esqueletos debajo
 *      ocupando el hueco de lo que falta.
 *   3. Llega la remota: se mezcla todo y **se reordena la lista entera**. Los
 *      esqueletos desaparecen. El salto no sorprende porque el hueco estaba
 *      anunciado.
 *
 * Nace conviviendo con las cinco páginas por medio, que no se tocan.
 */
import { ref, reactive, computed, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from '@/composables/useI18n'
import { useAuthStore } from '@/store/auth'
import { getMediaConfig, detailRouteFor, mediaKeys } from '@/config/mediaRegistry'
import { ordenarPorRelevancia } from '@/utils/searchRelevance'
import CoverService from '@/services/CoverService'
import MediaListItem from '@/components/shared/MediaListItem.vue'
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue'
import StaleNotice from '@/components/shared/StaleNotice.vue'
import Logger from '@/utils/logger'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

// El mismo debounce y el mismo mínimo que `MyLibrary`: estrenar otro criterio
// haría que las dos búsquedas de la app se comportaran distinto.
const DEBOUNCE_MS = 300
const MIN_CARACTERES = 2

/**
 * Qué medio sirve cada acción, y por qué esto es una constante y no un `if`.
 *
 * El corte es por **velocidad**, no por dominio: los tres de `local` salen del
 * mirror en milisegundos y los tres de `remote` dependen de internet. Tenerlo
 * declarado aquí es lo que permite **no pedir una tanda entera** cuando el
 * usuario ha desmarcado sus tres medios: desmarcar libros, juegos y vídeos
 * ahorra la petición lenta completa.
 */
const TANDAS = {
  local: ['movie', 'series', 'album'],
  remota: ['book', 'game', 'video']
}

const query = ref('')
const ultimaQuery = ref('')
const locales = ref([])
const remotos = ref([])
const esperandoRemoto = ref(false)
const buscando = ref(false)
const avisos = ref([])
const fallidos = ref([])

/** Los seis medios, todos activos al entrar. Desmarcar oculta Y deja de buscar. */
const activos = reactive(Object.fromEntries(mediaKeys.map((m) => [m, true])))

/** Qué tandas se han pedido ya para la consulta actual, para no repetirlas. */
const pedidas = reactive({ local: false, remota: false })

const hayActivo = (tanda) => TANDAS[tanda].some((m) => activos[m])

/**
 * El icono de cada medio sale del registry, no se cablea aquí. `movie` y
 * `series` comparten bloque `list`, y su `iconOf` mira el tipo del ítem, así que
 * a series se le pasa uno de mentira para que devuelva el suyo.
 */
function iconoDe (medio) {
  return getMediaConfig(medio).list.iconOf(medio === 'series' ? { type: 'series' } : {})
}

const etiquetaDe = (medio) => getMediaConfig(medio).labelPlural

let temporizador = null
// Cada búsqueda lleva su número: una respuesta lenta de la anterior no puede
// pisar a la de la consulta que el usuario está escribiendo ahora.
let generacion = 0

/**
 * De la respuesta del backend a lo que come `ordenarPorRelevancia`: `media`,
 * `title` y el ítem transformado. El título y la transformación salen del
 * registry, así que esta pantalla no sabe que un álbum llama `name` a lo que una
 * película llama `Title`.
 */
function aEntradas (resultsPorMedio) {
  const entradas = []

  for (const media of mediaKeys) {
    const crudos = resultsPorMedio?.[media]
    if (!Array.isArray(crudos) || crudos.length === 0) continue

    const { search } = getMediaConfig(media).api
    for (const crudo of crudos) {
      const item = search.transform(crudo)
      const id = idDe(media, item)
      entradas.push({
        media,
        item: conPortadaDeCatalogo(media, item, id),
        title: search.titleOf(item),
        id
      })
    }
  }

  return entradas
}

/**
 * La identidad para la `:key`. Las seis son distintas y ninguna es `id`, así que
 * se pregunta al registry como hace `MyLibrary`; la clave combina medio e
 * identidad porque un libro y un juego pueden compartir número.
 */
function idDe (media, item) {
  const { libraryItem } = getMediaConfig(media)
  return libraryItem?.idOf?.(item) ?? item.id ?? item.isbn ?? item.imdbID ?? ''
}

/**
 * La portada de lo que NO está en la biblioteca.
 *
 * `MediaListItem` está pensado para ítems guardados: pregunta por la copia local
 * y, si no hay, pinta lo que traiga `list.coverOf`. Aquí lo que se enseña es
 * catálogo, y el mirror devuelve `Poster: null` en las búsquedas de película y
 * serie —lo hace también `search_movies_omdb`, así que no es cosa de la acción
 * nueva—, con lo que las tres filas del mirror saldrían sin carátula.
 *
 * La cadena de catálogo es la que ya usan `MovieCarouselItem` y
 * `AlbumCarouselItem` para exactamente este caso: `?cover=<medio>/<clave>`, que
 * resuelve contra TMDB o Cover Art Archive y cachea. Solo se rellena si el ítem
 * viene sin portada: lo que ya la trae —juegos, vídeos, libros— se deja intacto.
 */
function conPortadaDeCatalogo (media, item, id) {
  const { coverOf } = getMediaConfig(media).list
  if (coverOf(item) || !id) return item

  return { ...item, coverUrl: CoverService.catalogCoverUrl(media, id) }
}

const resultados = computed(() =>
  ordenarPorRelevancia(
    [...locales.value, ...remotos.value].filter((e) => activos[e.media]),
    ultimaQuery.value
  )
)

const sinResultados = computed(() =>
  !buscando.value &&
  !esperandoRemoto.value &&
  ultimaQuery.value !== '' &&
  resultados.value.length === 0
)

function limpiar () {
  locales.value = []
  remotos.value = []
  avisos.value = []
  fallidos.value = []
}

async function buscar (texto) {
  const mia = ++generacion
  ultimaQuery.value = texto
  limpiar()
  pedidas.local = false
  pedidas.remota = false

  lanzarTandas(mia, texto)
}

/**
 * Lanza las dos tandas **a la vez**, y solo las que hagan falta.
 *
 * Nada de `await` entre ellas: eso las pondría en cascada, que es justo lo que
 * este diseño evita. Y una tanda con sus tres medios desmarcados no se pide
 * siquiera — desmarcar libros, juegos y vídeos ahorra la petición lenta entera.
 */
function lanzarTandas (mia, texto) {
  const vigente = () => mia === generacion

  if (hayActivo('local') && !pedidas.local) {
    pedidas.local = true
    buscando.value = true
    auth.authenticatedApiCall('search_catalog_local', { query: texto })
      .then((r) => { if (vigente()) locales.value = aEntradas(r?.data?.data?.results) })
      .catch((e) => Logger.error('[SearchView] Falló la búsqueda local', e))
      .finally(() => { if (vigente()) buscando.value = false })
  }

  if (hayActivo('remota') && !pedidas.remota) {
    pedidas.remota = true
    esperandoRemoto.value = true
    auth.authenticatedApiCall('search_catalog_remote', { query: texto })
      .then((r) => {
        if (!vigente()) return
        const data = r?.data?.data
        remotos.value = aEntradas(data?.results)
        avisos.value = avisosDe(data)
        fallidos.value = (data?.failed ?? []).map((m) => getMediaConfig(m).labelPlural)
      })
      .catch((e) => Logger.error('[SearchView] Falló la búsqueda remota', e))
      .finally(() => { if (vigente()) esperandoRemoto.value = false })
  }
}

/**
 * Un aviso por medio degradado, y solo de los que declaran `supportsStale`:
 * películas y álbumes no lo declaran porque los sirve el mirror.
 */
function avisosDe (data) {
  return Object.entries(data?.stale ?? {})
    .filter(([media, rancio]) => rancio && getMediaConfig(media).api.supportsStale)
    .map(([media]) => ({
      media,
      cachedAt: data?.cached_at?.[media] ?? null,
      provider: getMediaConfig(media).labelPlural
    }))
}

/**
 * Abre la ficha **llevándose el ítem**, no solo la ruta.
 *
 * `MediaDetailView` arranca con `history.state?.[stateKey]` (`:348`) y solo si no
 * hay nada depende de reconstruirlo desde el id de la ruta. Las cinco páginas de
 * búsqueda llevan años pasándolo así (`BookSearch.vue:170-174`), y esta pantalla
 * no lo hacía: la ficha nacía vacía.
 *
 * **No es un detalle de rendimiento, es que la ficha no abría.** El
 * enriquecimiento de un libro va por `search_google_books_isbn`, o sea
 * `q=isbn:…`, y Google no indexa por ISBN todos los volúmenes que devuelve en una
 * búsqueda: medido el 2026-09-01 con «harry potter», **13 de 19 ISBN válidos no se
 * encontraban**. Con el ítem en el estado, la ficha se pinta igual y el
 * enriquecimiento vuelve a ser lo que debe ser: un extra.
 */
function irADetalle (entrada) {
  const destino = detailRouteFor(entrada.media, entrada.id)
  if (destino === null) {
    Logger.warn('[SearchView] Sin ruta de detalle', { media: entrada.media, id: entrada.id })
    return
  }

  const { stateKey } = getMediaConfig(entrada.media).detail

  router.push({
    ...destino,
    // Clonado: lo que va al `history.state` tiene que ser serializable, y un
    // proxy reactivo de Vue no lo es.
    state: { [stateKey]: JSON.parse(JSON.stringify(entrada.item)) }
  })
}

watch(query, (texto) => {
  clearTimeout(temporizador)
  const limpio = texto.trim()

  if (limpio.length < MIN_CARACTERES) {
    generacion++          // invalida lo que esté en vuelo
    ultimaQuery.value = ''
    limpiar()
    esperandoRemoto.value = false
    buscando.value = false
    return
  }

  temporizador = setTimeout(() => buscar(limpio), DEBOUNCE_MS)
})

/**
 * Marcar un medio cuya tanda no se pidió la pide; desmarcar no pide nada.
 *
 * No se relanza la búsqueda entera a propósito: lo ya traído se conserva y solo
 * se va a por lo que falta. Desmarcar es gratis —el filtro es del `computed`— y
 * volver a marcar cuesta como mucho una petición, que además suele salir de la
 * caché del backend.
 */
watch(activos, () => {
  if (ultimaQuery.value === '') return
  lanzarTandas(generacion, ultimaQuery.value)
})

onUnmounted(() => clearTimeout(temporizador))
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/filter-pills' as *;

// El mismo ancho que `/library` (`MyLibrary.vue:492`) y no la utilidad
// `.u-content-width` de 860 px: aquello acota una columna de lectura, y esto es
// un catálogo. Con 860 salían tres tarjetas por fila y los títulos con badge se
// truncaban a «Blad…».
.search-view {
  display: flex;
  flex-direction: column;
  padding: spacing(lg) spacing(md);
  width: 100%;
  max-width: 1600px;
  margin: auto;
  box-sizing: border-box;
}

.search-view__title {
  text-align: center;
  margin-bottom: spacing(lg);
  color: var(--color-text);
}

.search-view__box {
  display: flex;
  justify-content: center;
  margin-bottom: spacing(lg);
}

.search-view__input {
  width: min(600px, 100%);
  padding: spacing(sm) spacing(md);
  border: 1px solid var(--color-border);
  border-radius: radius(full);
  background: var(--color-background-card);
  color: var(--color-text);
  font-size: var(--font-size-md);

  &::placeholder {
    color: var(--color-text-muted);
  }
}

// La fila de filtros. El aspecto de la píldora sale del parcial compartido con
// `/library`; la disposición se decide aquí, como allí.
.search-view__filtros {
  @include filter-pills;

  display: flex;
  flex-wrap: wrap;
  gap: spacing(sm);
  justify-content: center;
  margin-bottom: spacing(lg);
}

// Catálogo y no listado, con la misma rejilla que `/library` y por las mismas
// razones, que están escritas en `MyLibrary.vue:538-547`: `min(240px, 100%)`
// para que una pista de 240 px no desborde cuando el contenedor mide menos, y
// `auto-fill` en vez de `auto-fit` para que con pocos resultados las tarjetas no
// se estiren a todo el ancho.
.search-view__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr));
  gap: spacing(sm);
  width: 100%;
  padding: 0;
}

.search-view__failed,
.search-view__empty {
  text-align: center;
  color: var(--color-text-muted);
  margin: spacing(md) 0;
}
</style>
