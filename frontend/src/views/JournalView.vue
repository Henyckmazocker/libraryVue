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
         un selector tipo radio queda fuera de la escala de `.btn` a propósito. -->
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

    <JournalEntryModal
      v-model="modalAbierto"
      :entry="entradaEnEdicion"
      @saved="onGuardado"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import EmptyState from '@/components/common/EmptyState.vue'
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue'
import JournalEntryRow from '@/components/Journal/JournalEntryRow.vue'
import JournalEntryModal from '@/components/Journal/JournalEntryModal.vue'
import { useConfirmationModal } from '@/composables/useConfirmationModal'
import { useJournalStore } from '@/store/journal'
import { useUIStore } from '@/store/ui'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import { journalDayLabel } from '@/utils/dates'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

const journalStore = useJournalStore()
const uiStore = useUIStore()
const { entries, byDay, total, hasMore, hasEntries, isLoading, media } = storeToRefs(journalStore)

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

onMounted(() => {
  if (entries.value.length === 0) {
    journalStore.fetch()
  }
})
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
    color: var(--color-text-light);
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

  &__count {
    margin: 0 0 spacing(sm);
    color: var(--color-text-light);
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
    color: var(--color-text-light);
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
