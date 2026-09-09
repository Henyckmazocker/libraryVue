<template>
  <div class="journal-view">
    <header class="journal-view__header">
      <div>
        <h1 class="journal-view__title">
          <i class="fas fa-book-open" />
          {{ t('journal.title') }}
        </h1>
        <p class="journal-view__subtitle">
          {{ t('journal.subtitle') }}
        </p>
      </div>

      <button
        type="button"
        class="btn btn--primary"
        @click="abrirAlta"
      >
        <i class="fas fa-plus" />
        {{ t('journal.add') }}
      </button>
    </header>

    <!-- Filtro por medio. Es de una sola opción —el backend filtra por UN medio—
         así que son botones con `aria-pressed`, no checkboxes como en /library:
         un selector tipo radio queda fuera de la escala de `.btn` a propósito.
         Desde el M4 manda sobre las TRES vistas, no solo sobre el listado: las
         píldoras viven encima del calendario y prometen que lo filtran. -->
    <div class="journal-view__filters">
      <button
        v-for="opcion in filtros"
        :key="opcion.media ?? 'all'"
        type="button"
        class="filter-checkbox-pill journal-view__filter"
        :aria-pressed="media === opcion.media"
        @click="cambiarFiltro(opcion.media)"
      >
        <i
          :class="opcion.icon"
          aria-hidden="true"
        />
        <span>{{ opcion.label }}</span>
      </button>
    </div>

    <!-- El conmutador: listado (por defecto), mes y año. Es un grupo de botones
         con `aria-pressed` —un selector tipo radio—, y este proyecto lo declara
         FUERA de la escala de `.btn` a propósito, junto a `sort-button` y
         `tag-pill`: no se escribe como `.btn`. -->
    <div
      class="journal-view__views"
      role="group"
      :aria-label="t('journal.calendar.viewLabel')"
    >
      <button
        v-for="opcion in vistas"
        :key="opcion.id"
        type="button"
        class="journal-view__view"
        :aria-pressed="vista === opcion.id"
        @click="cambiarVista(opcion.id)"
      >
        <i
          :class="opcion.icon"
          aria-hidden="true"
        />
        <span>{{ opcion.label }}</span>
      </button>
    </div>

    <JournalCalendarYear
      v-if="vista === VISTA_ANIO"
      :year="anioVista"
      @select-day="abrirDia"
      @select-month="abrirMes"
      @select-year="abrirAnio"
    />

    <JournalCalendarMonth
      v-else-if="vista === VISTA_MES"
      :year="mesVista.year"
      :month="mesVista.month"
      @select-day="abrirDia"
      @select-month="abrirMes"
    />

    <template v-else>
      <!-- El listado abierto por un día concreto (`?date=`). Lleva la salida al
           lado: sin ella, quien llega aquí desde la rejilla se queda mirando el
           diario cortado por arriba sin saber por qué. -->
      <p
        v-if="anchorDate"
        class="journal-view__anchor"
      >
        <span>{{ t('journal.calendar.fromDay', { date: journalDayLabel(anchorDate) }) }}</span>
        <button
          type="button"
          class="btn btn--ghost btn--sm"
          @click="cambiarVista(VISTA_LISTA)"
        >
          {{ t('journal.calendar.wholeDiary') }}
        </button>
      </p>

      <MediaSkeleton
        v-if="isLoading && !hasEntries"
        variant="list-item"
        :count="6"
      />

      <EmptyState
        v-else-if="!hasEntries"
        icon="fas fa-book-open"
        :title="media ? t('journal.emptyFiltered') : t('journal.empty')"
        :message="media ? '' : t('journal.emptyHint')"
      />

      <template v-else>
        <p class="journal-view__count">
          {{ total === 1 ? t('journal.countOne') : t('journal.countMany', { n: total }) }}
        </p>

        <section
          v-for="dia in byDay"
          :id="'journal-day-' + dia.date"
          :key="dia.date"
          class="journal-view__day"
        >
          <h2 class="journal-view__day-title">
            {{ journalDayLabel(dia.date) }}
          </h2>

          <JournalEntryRow
            v-for="entrada in dia.entries"
            :key="entrada.id"
            :entry="entrada"
            @edit="abrirEdicion"
            @remove="pedirBorrado"
          />
        </section>

        <button
          v-if="hasMore"
          type="button"
          class="btn btn--secondary journal-view__more"
          :class="{ 'is-loading': isLoading }"
          :disabled="isLoading"
          @click="journalStore.loadMore()"
        >
          {{ t('journal.loadMore') }}
        </button>
      </template>
    </template>

    <JournalEntryModal
      v-model="modalAbierto"
      :entry="entradaEnEdicion"
      @saved="onGuardado"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import EmptyState from '@/components/common/EmptyState.vue'
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue'
import JournalEntryRow from '@/components/Journal/JournalEntryRow.vue'
import JournalCalendarYear from '@/components/Journal/JournalCalendarYear.vue'
import JournalCalendarMonth from '@/components/Journal/JournalCalendarMonth.vue'
import JournalEntryModal from '@/components/Journal/JournalEntryModal.vue'
import { useConfirmationModal } from '@/composables/useConfirmationModal'
import { useJournalStore } from '@/store/journal'
import { useUIStore } from '@/store/ui'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import { journalDayLabel } from '@/utils/dates'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

const route = useRoute()
const router = useRouter()

const journalStore = useJournalStore()
const uiStore = useUIStore()
const {
  entries, byDay, total, hasMore, hasEntries, isLoading, media, anchorDate
} = storeToRefs(journalStore)

/**
 * Los SEIS medios más «todos». `mediaKeys` y no `storeMediaKeys`: aquí no se
 * toca ningún store de Pinia, solo se manda el nombre del medio al backend, y el
 * diario sí distingue una serie de una película.
 */
const filtros = computed(() => [
  { media: null, icon: 'fas fa-layer-group', label: t('journal.filterAll') },
  ...mediaKeys.map((clave) => {
    const config = getMediaConfig(clave)
    return {
      media: clave,
      icon: config.list?.iconOf?.({}) ?? 'fas fa-star',
      label: config.label
    }
  })
])

const cambiarFiltro = (clave) => {
  journalStore.setMedia(media.value === clave ? null : clave)
}

const modalAbierto = ref(false)
const entradaEnEdicion = ref(null)

const abrirAlta = () => {
  entradaEnEdicion.value = null
  modalAbierto.value = true
}

const abrirEdicion = (entrada) => {
  entradaEnEdicion.value = entrada
  modalAbierto.value = true
}

const onGuardado = () => {
  entradaEnEdicion.value = null
  uiStore.showSuccess(t('journal.saved'))
}

/**
 * `showConfirmation` y no `confirmDelete`: aquel exige teclear «ELIMINAR», y esa
 * fricción es para sacar un ítem de la biblioteca. Borrar una entrada del diario
 * no toca ni el ítem ni su valoración —solo dice que ese día no lo consumiste—,
 * así que lleva un sí/no, como el borrado desde la ficha
 * (`MediaDetailView.vue:742-754`).
 */
const pedirBorrado = async (entrada) => {
  const { showConfirmation } = useConfirmationModal()

  const confirmado = await showConfirmation({
    title: t('journal.remove'),
    message: t('journal.removeConfirm'),
    type: 'danger',
    confirmText: t('common.delete')
  })

  if (confirmado && await journalStore.remove(entrada.id)) {
    uiStore.showSuccess(t('journal.removed'))
  }
}

// ═══════════════════════════════════════════════════════════════════════════
// El conmutador — M4 del [[Plan - Calendario del Diario]]
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Los tres estados viven en la URL (`/journal?view=month&date=2026-09`) y no en
 * un `ref`, y eso es lo que hace que recargar no los pierda y que el botón de
 * atrás del navegador deshaga cada paso: cada cambio es un `push`, no un estado
 * interno. Sin `view` es el listado, que sigue siendo la vista por defecto.
 *
 * `date` significa una cosa distinta en cada vista y por eso se lee con un
 * regex y no se parte a ciegas: `2026` en el año, `2026-09` en el mes y
 * `2026-09-04` en el listado. Siempre una CADENA, nunca un `Date`: un día es
 * una fecha local y `new Date('2026-09-04')` se lee como UTC.
 */
const VISTA_LISTA = 'list'
const VISTA_MES = 'month'
const VISTA_ANIO = 'year'

const vistas = computed(() => [
  { id: VISTA_LISTA, icon: 'fas fa-list', label: t('journal.calendar.viewList') },
  { id: VISTA_MES, icon: 'fas fa-calendar-days', label: t('journal.calendar.viewMonth') },
  { id: VISTA_ANIO, icon: 'fas fa-table-cells', label: t('journal.calendar.viewYear') }
])

const vista = computed(() => {
  const pedida = route.query.view

  return pedida === VISTA_MES || pedida === VISTA_ANIO ? pedida : VISTA_LISTA
})

const fecha = computed(() => (typeof route.query.date === 'string' ? route.query.date : ''))

const dosDigitos = (n) => String(n).padStart(2, '0')

/** El mes de hoy como `YYYY-MM`, que es la clave con la que viaja un mes. */
const mesDeHoy = () => {
  const hoy = new Date()

  return `${hoy.getFullYear()}-${dosDigitos(hoy.getMonth() + 1)}`
}

/** El año del `?date=`, valga `2026`, `2026-09` o `2026-09-04`. */
const anioVista = computed(() => {
  const encontrado = /^(\d{4})/.exec(fecha.value)

  return encontrado ? Number(encontrado[1]) : new Date().getFullYear()
})

const mesVista = computed(() => {
  const encontrado = /^(\d{4})-(\d{2})/.exec(fecha.value)
  const mes = encontrado ? Number(encontrado[2]) : 0

  // Un mes fuera de 1-12 es una URL escrita a mano: se cae al mes que corre en
  // vez de pintar un «mes 0», como hace el backend con un año imposible.
  if (!encontrado || mes < 1 || mes > 12) {
    const hoy = new Date()

    return { year: hoy.getFullYear(), month: hoy.getMonth() + 1 }
  }

  return { year: Number(encontrado[1]), month: mes }
})

/**
 * El mes que se estaba mirando, para que ir al año y volver no lo pierda. Se
 * recuerda desde la propia URL —no hay una segunda fuente de verdad— y sobrevive
 * a la ida y vuelta porque la vista no se desmonta.
 */
const mesRecordado = ref(null)

watch([vista, fecha], () => {
  if (vista.value === VISTA_MES) {
    mesRecordado.value = `${mesVista.value.year}-${dosDigitos(mesVista.value.month)}`
  }
}, { immediate: true })

/** Un `push` y no un `replace`: cada paso tiene que poder deshacerse con «atrás». */
const navegar = (query) => router.push({ name: 'Journal', query })

/** Pulsar un mes del heatmap abre ese mes. La clave llega como `YYYY-MM`. */
const abrirMes = (clave) => navegar({ view: VISTA_MES, date: clave })

/** Las flechas del heatmap escriben el año en la URL, para que recargar lo mantenga. */
const abrirAnio = (anio) => navegar({ view: VISTA_ANIO, date: String(anio) })

/** Pulsar un día de la rejilla abre el listado en ese día. */
const abrirDia = (date) => navegar({ date })

const cambiarVista = (destino) => {
  if (destino === VISTA_ANIO) {
    // Desde el mes se sube a SU año, no al que corre: es el año que se está
    // mirando.
    return abrirAnio(vista.value === VISTA_MES ? mesVista.value.year : anioVista.value)
  }

  if (destino === VISTA_MES) {
    return abrirMes(mesRecordado.value ?? mesDeHoy())
  }

  // El listado es la vista por defecto: sin `view` ni `date`, `/journal` es la
  // URL de siempre. Y pulsarlo estando ya en él es lo que suelta el ancla.
  return navegar({})
}

/**
 * Lleva el listado a un día concreto.
 *
 * `scrollIntoView` va con encadenamiento opcional porque jsdom no lo implementa
 * y la suite monta esta vista.
 */
const irAlDia = (date) => {
  document.getElementById(`journal-day-${date}`)?.scrollIntoView?.({ block: 'start' })
}

const DIA = /^\d{4}-\d{2}-\d{2}$/

/**
 * El listado, puesto de acuerdo con la URL.
 *
 * El `?date=` de un día no es un simple `scrollIntoView`: el listado se pagina
 * de 30 en 30 desde hoy hacia atrás, así que un día de hace medio año **no está
 * cargado** y no hay sección a la que ir. Por eso el ancla viaja al backend
 * (como un `to`) y el listado se recarga desde ese día; el `scrollIntoView` de
 * después solo lo deja arriba del todo.
 */
const sincronizarListado = async () => {
  if (vista.value !== VISTA_LISTA) {
    return
  }

  const ancla = DIA.test(fecha.value) ? fecha.value : null

  if (journalStore.anchorDate !== ancla || entries.value.length === 0) {
    await journalStore.setAnchor(ancla)
  }

  if (ancla) {
    await nextTick()
    irAlDia(ancla)
  }
}

watch([vista, fecha], sincronizarListado)

onMounted(sincronizarListado)
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/filter-pills' as *;

.journal-view {
  @include filter-pills;

  padding: spacing(lg);
  max-width: min(900px, 100%);
  margin: 0 auto;

  &__header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: spacing(md);
    margin-bottom: spacing(lg);
  }

  &__title {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    margin: 0;
    font-size: var(--font-size-2xl);
    color: var(--color-heading);
  }

  &__subtitle {
    margin: spacing(2xs) 0 0;
    color: var(--color-text-muted);
  }

  // La disposición de la fila la pone el consumidor: el mixin de píldoras solo
  // trae el aspecto de cada una.
  &__filters {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
    margin-bottom: spacing(lg);
  }

  &__filter {
    gap: spacing(2xs);

    &[aria-pressed='true'] {
      border-color: var(--color-primary);
      color: var(--color-primary);
    }
  }

  // El conmutador de vistas. Un grupo de botones con `aria-pressed`, que el
  // proyecto declara fuera de la escala de `.btn` a propósito: es un selector
  // tipo radio, no un botón de acción. `inline-flex` para que el grupo mida lo
  // que miden sus tres botones y no la fila entera.
  &__views {
    display: inline-flex;
    margin-bottom: spacing(lg);
    border: 1px solid var(--color-border);
    border-radius: radius(md);
    overflow: hidden;
  }

  &__view {
    @include button-reset;
    @include focus-ring;

    display: flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(2xs) spacing(sm);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    transition: background-color 0.2s ease, color 0.2s ease;

    & + & {
      border-left: 1px solid var(--color-border);
    }

    &:hover {
      background: var(--color-background-soft);
      color: var(--color-text);
    }

    // Pulsado va RELLENO, no solo con el borde teñido como las píldoras de
    // arriba: son dos selectores distintos uno encima del otro y tienen que
    // distinguirse. `--color-text-light` (#ffffff en los dos temas) aquí sí es
    // su sitio: va sobre `--color-primary`, que es superficie de color en
    // claro (#1D4E4A, 9.6 contra el blanco) y en oscuro (#2a5e5a, 7.4).
    &[aria-pressed='true'] {
      background: var(--color-primary);
      color: var(--color-text-light);
    }
  }

  // La franja del listado abierto por un día. Un aviso discreto con su salida.
  &__anchor {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: spacing(xs);
    margin: 0 0 spacing(md);
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__count {
    margin: 0 0 spacing(sm);
    color: var(--color-text-muted);
    font-size: var(--font-size-sm);
  }

  &__day {
    margin-bottom: spacing(lg);
  }

  &__day-title {
    margin: 0 0 spacing(2xs);
    padding-bottom: spacing(2xs);
    border-bottom: 1px solid var(--color-border);
    font-size: var(--font-size-sm);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
  }

  &__more {
    display: block;
    margin: 0 auto;
  }

  @include responsive-below(md) {
    padding: spacing(md) spacing(sm);
  }
}
</style>
