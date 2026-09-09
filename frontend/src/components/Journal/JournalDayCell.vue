<template>
  <button
    type="button"
    class="journal-day"
    :class="[
      entries.length ? 'journal-day--llena' : 'journal-day--vacia',
      mediaDominante ? 'journal-day--' + mediaDominante : ''
    ]"
    :disabled="!entries.length"
    @click="$emit('select', date)"
  >
    <!-- Quien nombra la celda es esto, no el `alt` de las portadas: la celda es
         un solo control y su nombre accesible es su fecha y cuántas cosas hubo.
         Las portadas van con `alt=""` a propósito, como ornamento. -->
    <span class="u-sr-only">{{ label }}</span>

    <span
      class="journal-day__number"
      aria-hidden="true"
    >{{ numero }}</span>

    <span
      v-if="visibles.length"
      class="journal-day__covers"
      :class="{ 'journal-day__covers--multi': visibles.length > 1 }"
      aria-hidden="true"
    >
      <span
        v-for="vista in visibles"
        :key="vista.id"
        class="journal-day__cover"
      >
        <img
          v-if="vista.src"
          :src="vista.src"
          alt=""
          loading="lazy"
          decoding="async"
          @error="fallarPortada(vista)"
        >
        <i
          v-else
          :class="vista.icono"
        />
      </span>
    </span>

    <span
      v-if="restantes"
      class="journal-day__more"
      aria-hidden="true"
    >{{ '+' + restantes }}</span>

    <!-- PLAN B (sección 🔴 del plan, medido y aprobado en el M0): por debajo de
         `md` la celda no pinta portadas, sino este contador con el acento del
         medio dominante. Lo oculta el CSS a partir de `md`, no un `v-if` con
         `useBreakpoint`: así el cambio ocurre en el mismo sitio que el resto de
         la rejilla y no depende de un listener de `resize`. -->
    <span
      v-if="entries.length"
      class="journal-day__count"
      aria-hidden="true"
    >{{ '×' + entries.length }}</span>
  </button>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import CoverService from '@/services/CoverService'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'

/**
 * Una celda de la rejilla del mes — M3 del [[Plan - Calendario del Diario]].
 *
 * Sale del spike del M0, que la tenía dentro de `JournalCalendarMonth.vue`. La
 * maquetación, las medidas y el plan B se heredan tal cual: están medidos con
 * capturas y con `npm run test:responsive`, y rehacerlos sería tirar esa
 * validación. Lo que cambia es que ahora recibe entradas de verdad, resuelve la
 * portada como el listado y **emite** al pulsarla.
 *
 * **La fecha es una cadena `YYYY-MM-DD`, nunca un `Date`.** El número del día
 * sale de la propia cadena por eso: `new Date('2026-09-04')` se interpreta como
 * UTC y al oeste de Greenwich devolvería el 3.
 *
 * La raíz es un `<button>` con `button-reset`, nunca un `<div @click>`: las 20
 * reglas de `eslint-plugin-vuejs-accessibility` están en `error` y un div clicable
 * nuevo rompe el lint con exit 1. Y el comentario que explicaba esto vivía en la
 * plantilla hasta que se vio que un comentario **delante** del elemento raíz
 * convierte el componente en un fragmento: `wrapper.classes()` sale vacío y el
 * `@click` no llega. Por eso está aquí.
 *
 * Un día VACÍO va `disabled`: pulsarlo llevaría al listado posicionado en un día
 * donde no hay nada, que es un callejón sin salida disfrazado de destino. Y de
 * paso es la tercera señal —junto al fondo y al borde de acento— de que ese día
 * está vacío, sin leer ningún número.
 */
const props = defineProps({
  /** `YYYY-MM-DD`. Es también lo que viaja en el evento. */
  date: {
    type: String,
    required: true
  },

  /** Las entradas del diario de ese día, en el orden que trajo el backend. */
  entries: {
    type: Array,
    default: () => []
  },

  /**
   * El nombre accesible de la celda, ya compuesto. Lo pone el mes y no la celda
   * porque formatear la fecha es un `Intl.DateTimeFormat`, y uno por celda son
   * treinta y uno por render de lo más caro que hay aquí.
   */
  label: {
    type: String,
    required: true
  },

  /**
   * Cuántas portadas caben antes del `+N`.
   *
   * **Cuatro, la N que eligió el M0**, y se mantiene: las portadas se reparten en
   * dos columnas, así que 4 es la última cantidad que llena la caja sin dejar un
   * hueco impar, y con 5 cada portada bajaría de los ~34 px en los que todavía se
   * reconoce algo. Es prop y no constante porque el M0 lo dejó dicho: quien monte
   * la rejilla en otro sitio (una barra lateral, una ficha) tendrá otra caja.
   */
  maxCovers: {
    type: Number,
    default: 4
  }
})

defineEmits(['select'])

// De la cadena, no de un `Date`. `Number('04')` es 4: no hay ceros a la izquierda
// que interpretar como octal, eso solo pasa con `parseInt` de otra época.
const numero = computed(() => Number(props.date.slice(8, 10)))

/**
 * El acento de la celda es el del medio dominante del día.
 *
 * Una serie se guarda con `AddMovieUseCase` y no tiene acento propio: usa el de
 * película, igual que su portada se pide como `movie`.
 */
const mediaDominante = computed(() => {
  if (!props.entries.length) {
    return null
  }

  const cuenta = {}

  for (const entrada of props.entries) {
    cuenta[entrada.media] = (cuenta[entrada.media] ?? 0) + 1
  }

  const ganador = Object.entries(cuenta).sort((a, b) => b[1] - a[1])[0][0]

  return ganador === 'series' ? 'movie' : ganador
})

/**
 * El escalón de portada del listado (`JournalEntryRow.vue`): copia local primero
 * y `entity_cover` de respaldo. Y con los **dos** indicadores, no uno: sin saber
 * cuál se está pintando, una entrada sin copia local gastaría su primer `@error`
 * marcando un fallo local que no ha ocurrido y, como el `src` no cambia, el
 * navegador no reintenta y el hueco no llega nunca.
 */
const fallosLocales = reactive({})
const fallosRemotos = reactive({})

// `media` viene de la base, así que puede ser un medio que el registry no
// conozca. `getMediaConfig` **lanza** con uno desconocido: se comprueba antes.
const iconoDe = (media) => {
  const clave = mediaKeys.includes(media) ? media : null

  return clave ? (getMediaConfig(clave).list?.iconOf?.({}) ?? 'fas fa-star') : 'fas fa-star'
}

const visibles = computed(() =>
  props.entries.slice(0, props.maxCovers).map((entrada) => {
    // Una serie pide su portada como `movie`: se guardan con `AddMovieUseCase`,
    // así que su fila de `cover_file` lleva `media_type = 'movie'` y no hay ni
    // una con `'series'`. La clave sí es la suya.
    const media = entrada.media === 'series' ? 'movie' : entrada.media
    const local = CoverService.localCoverUrl(media, entrada.entity_id)
    const usandoLocal = Boolean(local) && !fallosLocales[entrada.id]
    const remota = fallosRemotos[entrada.id] ? null : (entrada.entity_cover || null)

    return {
      id: entrada.id,
      usandoLocal,
      src: usandoLocal ? local : remota,
      icono: iconoDe(entrada.media)
    }
  })
)

const restantes = computed(() => Math.max(0, props.entries.length - visibles.value.length))

const fallarPortada = (vista) => {
  if (vista.usandoLocal) {
    fallosLocales[vista.id] = true
    return
  }

  fallosRemotos[vista.id] = true
}

// El reset se ancla a las ENTRADAS, no al `src` resuelto: aquel depende de los
// dos indicadores, así que un fallo cambiaría el `src`, el watch limpiaría el
// indicador y volveríamos a la local — un bucle en el que el escalón a la remota
// no llega jamás. Se vio en el listado con las series.
watch(() => props.entries, () => {
  for (const clave of Object.keys(fallosLocales)) {
    delete fallosLocales[clave]
  }

  for (const clave of Object.keys(fallosRemotos)) {
    delete fallosRemotos[clave]
  }
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

// El CSS `scoped` del mes NO llega aquí dentro: alcanza la raíz del hijo —este
// `<button>`— y el contenido de sus slots, nada más. Por eso todo lo de dentro de
// la celda vive en este fichero y la rejilla que la coloca vive en el del mes.
.journal-day {
  @include button-reset;
  @include focus-ring;

  width: 100%;
  min-height: 56px; // MEDIDA de la celda vacía, no espacio: es el alto de una cosa
  display: flex;
  flex-direction: column;
  gap: spacing(3xs);
  padding: spacing(3xs);
  border: 1px solid var(--color-border);
  border-radius: radius(sm);
  background: var(--color-background-soft);
  transition: border-color var(--transition-fast), background-color var(--transition-fast);

  &:hover:not(:disabled) {
    border-color: var(--color-primary);
  }

  // Un día vacío no es un destino: `disabled` no se pinta gris ni se atenúa más
  // de lo que ya está, solo deja de responder y de recibir el foco.
  &:disabled {
    cursor: default;
  }

  &--vacia {
    background: transparent;
  }

  &--llena {
    border-left: 2px solid var(--celda-acento, var(--color-primary));
  }

  &--book { --celda-acento: var(--color-card-book-accent); }
  &--movie { --celda-acento: var(--color-card-movie-accent); }
  &--game { --celda-acento: var(--color-card-game-accent); }
  &--album { --celda-acento: var(--color-card-album-accent); }
  &--video { --celda-acento: var(--color-card-video-accent); }

  &__number {
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--color-text);
    line-height: 1;
  }

  &__covers {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: spacing(3xs);

    &--multi {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  &__cover {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: 2 / 3;
    overflow: hidden;
    border-radius: radius(sm);
    background: var(--color-background-mute);
    // El hueco sin portada lleva el icono del medio, y `--color-text-muted` es el
    // token medido para tinta atenuada sobre superficie clara (4.59).
    // `--color-text-light` es #ffffff en los DOS temas y desaparecería aquí.
    color: var(--color-text-muted);
    font-size: var(--font-size-xs);

    img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__more {
    font-size: var(--font-size-xs);
    font-weight: 600;
    // Respaldo `--color-text`, no `--color-text-light`: el blanco solo vale sobre
    // superficie oscura o de color, y esta celda es clara en tema claro.
    color: var(--celda-acento, var(--color-text));
    line-height: 1;
  }

  // El contador solo existe por debajo de `md`: en escritorio la portada ya dice
  // qué hubo ese día, y un número al lado sería ruido.
  &__count {
    display: none;
  }

  /* PLAN B — medido en el M0 y aplicado: a 360 px la celda mide ~42 px y a
     390 px ~46 px, así que un día con dos o más entradas repartiría portadas de
     ~18 px de ancho, en las que no se reconoce nada. Por debajo de `md` la celda
     pinta el número del día y el contador con el acento del medio dominante; el
     contenido sigue a un toque, en el listado del día. */
  @include responsive-below(md) {
    min-height: 40px; // MEDIDA de la celda sin portadas
    align-items: center;
    justify-content: center;
    padding: spacing(2xs) spacing(3xs);

    &__number {
      font-size: var(--font-size-sm);
    }

    &--vacia &__number {
      // Un día vacío se apaga con `muted`, que sí está medido sobre la superficie
      // clara. Fue la mina del M0: aquí ponía `--color-text-light`.
      color: var(--color-text-muted);
    }

    &__covers,
    &__more {
      display: none;
    }

    // El acento se usa como TINTA, no como relleno: los cinco
    // `--color-card-<medio>-accent` llevan anotado su ratio contra la superficie
    // y están validados para eso. Un relleno con tinta blanca encima sería un par
    // que nadie ha medido.
    &__count {
      display: block;
      color: var(--celda-acento, var(--color-text));
      font-size: var(--font-size-xs);
      font-weight: 700;
      line-height: 1;
    }
  }
}
</style>
