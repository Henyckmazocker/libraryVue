<template>
  <section class="journal-month">
    <header class="journal-month__header">
      <button
        type="button"
        class="btn btn--ghost btn--icon"
        @click="$emit('select-month', mesAnterior)"
      >
        <i
          class="fas fa-chevron-left"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ t('journal.calendar.prevMonth') }}</span>
      </button>

      <h2 class="journal-month__title">
        {{ tituloMes }}
      </h2>

      <button
        type="button"
        class="btn btn--ghost btn--icon"
        @click="$emit('select-month', mesSiguiente)"
      >
        <i
          class="fas fa-chevron-right"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ t('journal.calendar.nextMonth') }}</span>
      </button>
    </header>

    <!-- Los rótulos van `aria-hidden`: cada celda ya dice su fecha entera en su
         `.u-sr-only`, que es como este proyecto nombra lo que solo es un glifo. -->
    <div
      class="journal-month__weekdays"
      aria-hidden="true"
    >
      <span
        v-for="(rotulo, i) in diasSemana"
        :key="i"
      >{{ rotulo }}</span>
    </div>

    <div class="journal-month__grid">
      <!-- Los huecos delante del día 1. El mes empieza en LUNES: `getDay()`
           devuelve 0 para domingo, así que se rota con `(getDay() + 6) % 7`. -->
      <div
        v-for="hueco in huecosIniciales"
        :key="'hueco-' + hueco"
        class="journal-month__pad"
      />

      <JournalDayCell
        v-for="dia in dias"
        :key="dia.date"
        :date="dia.date"
        :entries="dia.entries"
        :label="dia.etiqueta"
        @select="$emit('select-day', $event)"
      />
    </div>

    <p
      v-if="!isLoadingMonth && !hayEntradas"
      class="journal-month__empty"
    >
      {{ t('journal.calendar.emptyMonth') }}
    </p>
  </section>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue'
import { storeToRefs } from 'pinia'
import JournalDayCell from '@/components/Journal/JournalDayCell.vue'
import { useJournalStore } from '@/store/journal'
import { useI18n } from '@/composables/useI18n'
import { intlLocale } from '@/config/i18n'

/**
 * La rejilla del mes con portadas — M3 del [[Plan - Calendario del Diario]].
 *
 * El M4 le añade el filtro por medio —las píldoras de `/journal` mandan también
 * sobre la rejilla— y le mueve el mes desde la URL sin desmontarlo.
 *
 * Hereda del spike del M0 la geometría entera —siete columnas con
 * `minmax(0, 1fr)`, la rotación a lunes, las medidas de la celda y el plan B por
 * debajo de `md`—, que está medida con capturas y con `npm run test:responsive` y
 * no se rehace. Lo que cambia es de dónde salen los datos: el mes inventado que
 * vivía dentro del componente se sustituye por `store/journal.js → fetchMonth()`,
 * que pide `get_journal` con `from`/`to`, y la celda sale a `JournalDayCell.vue`.
 *
 * Una fecha aquí es una CADENA `YYYY-MM-DD`, nunca un `Date`: `new Date('2026-09-01')`
 * se interpreta como UTC y al oeste de Greenwich pinta el día anterior. Solo se
 * construye un `Date` con componentes numéricos (que sí es local) para saber en
 * qué día de la semana cae el 1, y con la hora del mediodía para formatear.
 */
const props = defineProps({
  /** Año por el que abrir. El M4 lo traerá de la URL (`?view=month&date=2026-09`). */
  year: {
    type: Number,
    default: () => new Date().getFullYear()
  },

  /** Mes 1-12. Por defecto el que corre. */
  month: {
    type: Number,
    default: () => new Date().getMonth() + 1
  }
})

/**
 * - `select-day` con la cadena `YYYY-MM-DD` del día pulsado.
 * - `select-month` con la cadena `YYYY-MM` a la que llevan las flechas, que es el
 *   mismo evento que emite `JournalCalendarYear` al pulsar un mes: los dos
 *   calendarios usan la puerta de `JournalView.abrirMes`, que es quien escribe la
 *   URL. Aquí no se enruta ni se toca el router.
 *
 * Las flechas **no se deshabilitan en ningún extremo**, a diferencia de las del
 * año: allí se recorre `years`, la lista de años que tienen entradas, y salirse de
 * ella no lleva a ninguna parte. Un mes vacío sí es un destino legítimo —lo dice
 * `journal.calendar.emptyMonth`— y un calendario en el que no se puede pasar de
 * página deja de ser un calendario.
 */
defineEmits(['select-day', 'select-month'])

const { t } = useI18n()

const journalStore = useJournalStore()
const { monthByDate, isLoadingMonth, media } = storeToRefs(journalStore)

const dosDigitos = (n) => String(n).padStart(2, '0')

const clave = computed(() => `${props.year}-${dosDigitos(props.month)}`)

// ── los rótulos ─────────────────────────────────────────────────────────────

/**
 * Los nombres de mes y las iniciales de día NO son claves del catálogo: salen de
 * `Intl` con `intlLocale()`, que es la etiqueta BCP-47 del idioma activo. Se lee
 * dentro del `computed`, así que cambiar de idioma los repinta sin recargar.
 */
/**
 * El mes de al lado, como cadena `YYYY-MM`. `new Date(año, mes, 1)` normaliza el
 * desbordamiento por su cuenta —mes 12 es enero del año siguiente, y mes -1 es
 * diciembre del anterior—, así que el salto de año sale gratis y no hay que
 * escribir la regla a mano. Se construye con componentes numéricos, que son hora
 * local: `new Date('2026-09-01')` sería UTC y en zonas al oeste caería en agosto.
 */
const mesVecino = (salto) => {
  const d = new Date(props.year, props.month - 1 + salto, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

const mesAnterior = computed(() => mesVecino(-1))
const mesSiguiente = computed(() => mesVecino(1))

const tituloMes = computed(() => {
  const formato = new Intl.DateTimeFormat(intlLocale(), { month: 'long', year: 'numeric' })
  const texto = formato.format(new Date(props.year, props.month - 1, 1))

  return texto.charAt(0).toUpperCase() + texto.slice(1)
})

const diasSemana = computed(() => {
  const estrecho = new Intl.DateTimeFormat(intlLocale(), { weekday: 'narrow' })

  // El 5 de enero de 2026 es lunes: siete días desde ahí son la semana entera en
  // el orden en que se pinta.
  return Array.from({ length: 7 }, (_, i) => estrecho.format(new Date(2026, 0, 5 + i)))
})

/**
 * Un formateador por render y no una llamada a `toLocaleDateString` por día:
 * `Intl.DateTimeFormat` es lo caro de aquí.
 */
const formatoLargo = computed(() => new Intl.DateTimeFormat(intlLocale(), {
  day: 'numeric',
  month: 'long',
  year: 'numeric'
}))

// ── la rejilla ──────────────────────────────────────────────────────────────

const huecosIniciales = computed(() => (new Date(props.year, props.month - 1, 1).getDay() + 6) % 7)

// Día 0 del mes siguiente es el último del actual: cubre febrero de un año
// bisiesto sin escribir a mano la regla de los bisiestos.
const diasDelMes = computed(() => new Date(props.year, props.month, 0).getDate())

const dias = computed(() => {
  const formato = formatoLargo.value
  const porFecha = monthByDate.value

  return Array.from({ length: diasDelMes.value }, (_, i) => {
    const date = `${clave.value}-${dosDigitos(i + 1)}`
    const entries = porFecha[date] ?? []
    // Mediodía: así ningún desplazamiento de zona mueve la fecha de día al
    // formatearla. Es lo que hace `utils/dates.js:journalDayLabel`.
    const legible = formato.format(new Date(`${date}T12:00:00`))

    return {
      date,
      entries,
      etiqueta: entries.length
        ? t('journal.calendar.dayEntries', { n: entries.length, date: legible })
        : t('journal.calendar.dayEmpty', { date: legible })
    }
  })
})

const hayEntradas = computed(() => dias.value.some((dia) => dia.entries.length > 0))

// ── la carga ────────────────────────────────────────────────────────────────

/**
 * `monthKey` es la caché: si ya está cargado ese mes no se vuelve a pedir.
 * `setMedia` la pone a `null` justo para que el `watch` de abajo la refresque.
 */
const cargar = () => {
  if (journalStore.monthKey !== clave.value) {
    journalStore.fetchMonth(props.year, props.month)
  }
}

// El mes se mueve sin desmontar el componente: lo cambia el `?date=` del
// conmutador (M4) al ir y volver, y también al volver atrás en el navegador.
watch(clave, cargar)

/**
 * **El filtro por medio manda también sobre la rejilla** (enmienda del M4).
 * Vive aquí y no en el store porque solo la vista abierta está montada: la que
 * no lo esté lo pedirá al montarse, sin gastar una petición que nadie mira.
 */
watch(media, cargar)

onMounted(cargar)
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.journal-month {
  &__header {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    margin-bottom: spacing(sm);
  }

  &__title {
    margin: 0;
    font-size: var(--font-size-lg);
    // `--color-text` y no `--color-heading`: ese token lo usan ocho sitios del repo
    // y NO está declarado en ninguno de los dos ficheros de color, así que la
    // declaración se descarta y el título hereda. Aquí se dice lo que se quiere.
    color: var(--color-text);
  }

  // Siete columnas fijas, `minmax(0, 1fr)` y no `1fr`: con `1fr` el mínimo
  // automático es el ancho del contenido y una portada empujaría la fila fuera
  // del viewport, que es el riesgo que manda el plan.
  &__weekdays,
  &__grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: spacing(3xs);
  }

  &__weekdays {
    margin-bottom: spacing(3xs);

    span {
      text-align: center;
      font-size: var(--font-size-xs);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      // `--color-text-light` es #ffffff en los DOS temas y el propio
      // `tokens/_colors.scss:35` lo acota a superficies oscuras o de color: aquí
      // el rótulo va sobre el fondo de página, así que el token es `muted` (4.59).
      color: var(--color-text-muted);
    }
  }

  &__empty {
    margin: spacing(sm) 0 0;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
