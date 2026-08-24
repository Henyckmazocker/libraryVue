<template>
  <div
    class="media-skeleton"
    :class="`media-skeleton--${variant}`"
    role="status"
    aria-live="polite"
    aria-busy="true"
  >
    <span class="u-sr-only">{{ label }}</span>

    <div
      v-for="n in count"
      :key="n"
      class="media-skeleton__unit"
      aria-hidden="true"
    >
      <Skeleton
        class="media-skeleton__cover"
        :width="`${cover.width}px`"
        :height="`${cover.height}px`"
        border-radius="8px"
      />
      <div class="media-skeleton__lines">
        <Skeleton
          v-for="(w, i) in lines"
          :key="i"
          :width="w"
          height="0.85rem"
          border-radius="4px"
        />
      </div>
    </div>
  </div>
</template>

<script>
/**
 * Esqueleto de carga único para las cuatro familias de tarjeta del proyecto.
 *
 * Cada variante copia las medidas del mixin SCSS de su familia
 * (`_list-item.scss`, `_library-item.scss`, `_carousel-item.scss`,
 * `_detail-view.scss`), de modo que al llegar los datos la maqueta no salte.
 * Si alguien cambia esos mixins, `VARIANTS` hay que tocarlo con ellos.
 *
 * El bloque entero es un `role="status"`: se anuncia una vez («Cargando…») y
 * las piezas van `aria-hidden`, para no dictarle al lector de pantalla una
 * lista de cajas vacías.
 */
const VARIANTS = {
  // Fila de `/library` — `list-item('book', '2/3', 75px)`.
  'list-item': {
    cover: { width: 50, height: 75 },
    lines: ['60%', '40%', '30%'],
  },
  // Tarjeta de la rejilla de biblioteca — `library-item('book', '2/3', 80px)`.
  'library-item': {
    cover: { width: 80, height: 120 },
    lines: ['70%', '45%'],
  },
  // Ítem de carrusel — `carousel-item-base(150px)` + portada 2/3.
  carousel: {
    cover: { width: 150, height: 225 },
    lines: ['80%', '50%'],
  },
  // Cabecera de ficha — la portada de `MediaDetailView` mide 220×330.
  detail: {
    cover: { width: 220, height: 330 },
    lines: ['75%', '55%', '40%', '65%'],
  },
}

export const skeletonVariants = Object.keys(VARIANTS)
</script>

<script setup>
import { computed } from 'vue'
import Skeleton from 'primevue/skeleton'

const props = defineProps({
  variant: {
    type: String,
    default: 'list-item',
    validator: (value) => skeletonVariants.includes(value),
  },
  /** Cuántas siluetas se pintan. Una sola en las fichas de detalle. */
  count: {
    type: Number,
    default: 1,
  },
  /** Lo que oye quien usa lector de pantalla mientras se espera. */
  label: {
    type: String,
    default: 'Cargando contenido…',
  },
})

const cover = computed(() => VARIANTS[props.variant].cover)
const lines = computed(() => VARIANTS[props.variant].lines)
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as list-item;

.media-skeleton {
  display: flex;
  gap: spacing(md);
}

.media-skeleton__unit {
  display: flex;
  gap: spacing(md);
  align-items: center;
}

.media-skeleton__lines {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: spacing(2xs);
}

// ── Fila: una debajo de otra, ocupando el ancho ──────────────────────────────
.media-skeleton--list-item {
  flex-direction: column;

  .media-skeleton__unit {
    width: 100%;
    // El alto sale del mixin de la familia: una fila real mide eso, así que la
    // lista no crece al llegar los datos.
    min-height: list-item.$list-item-height;
    padding: spacing(sm) spacing(md);
    border: 1px solid var(--color-border);
    border-radius: radius(md);
  }
}

// ── Rejilla: mismas columnas que `.library-list` ─────────────────────────────
.media-skeleton--library-item {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));

  .media-skeleton__unit {
    align-items: flex-start;
    padding: spacing(sm);
    border: 1px solid var(--color-border);
    border-radius: radius(lg);
  }
}

// ── Carrusel: en fila, sin envolver, como el carril real ─────────────────────
.media-skeleton--carousel {
  overflow: hidden;

  .media-skeleton__unit {
    flex-direction: column;
    align-items: stretch;
    width: 150px;
    flex-shrink: 0;
  }

  .media-skeleton__lines {
    margin-top: spacing(2xs);
  }
}

// ── Ficha: portada grande a la izquierda, texto a la derecha ─────────────────
// Copia la tarjeta de `.#{$selector}-header` (`_detail-view.scss:99-107`); sin
// ella el recuadro entero aparecía de golpe al llegar los datos.
.media-skeleton--detail {
  .media-skeleton__unit {
    align-items: flex-start;
    gap: spacing(xl);
    width: 100%;
    padding: spacing(lg);
    margin-bottom: spacing(xl);
    background: var(--color-background-mute);
    border-radius: radius(lg);
    box-shadow: shadow(medium);
  }

  .media-skeleton__lines {
    gap: spacing(sm);
  }

  @include responsive-below(md) {
    .media-skeleton__unit {
      flex-direction: column;
      align-items: center;
    }
  }
}
</style>
