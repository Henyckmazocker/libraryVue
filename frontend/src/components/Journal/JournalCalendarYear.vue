<template>
  <section class="journal-year">
    <header class="journal-year__header">
      <button
        type="button"
        class="btn btn--ghost btn--icon"
        :disabled="anioAnterior === null"
        @click="irA(anioAnterior)"
      >
        <i
          class="fas fa-chevron-left"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ t('journal.calendar.prevYear') }}</span>
      </button>

      <h2 class="journal-year__title">
        {{ anio }}
      </h2>

      <button
        type="button"
        class="btn btn--ghost btn--icon"
        :disabled="anioSiguiente === null"
        @click="irA(anioSiguiente)"
      >
        <i
          class="fas fa-chevron-right"
          aria-hidden="true"
        />
        <span class="u-sr-only">{{ t('journal.calendar.nextYear') }}</span>
      </button>

      <p class="journal-year__total">
        {{ calendarTotal === 1 ? t('journal.countOne') : t('journal.countMany', { n: calendarTotal }) }}
      </p>
    </header>

    <!-- El scroll horizontal vive AQUÍ, no en el `body`. Va sin condición de
         ancho a propósito: a 1400 px el año cabe entero, pero con el sidebar
         desplegado y una ventana estrecha el ancho útil baja de los ~808 px que
         mide la rejilla, y entonces desborda también en escritorio. Y es lo que
         exime a las celdas de la barrera: `tests/visual/overflow.mjs:231` no
         cuenta lo que cuelga de un ancestro con `overflow-x: auto`, porque se
         puede llegar a ello. Con `hidden` sí contaría, y con razón. -->
    <div
      ref="scrollEl"
      class="journal-year__scroll"
    >
      <div class="journal-year__canvas">
        <!-- Los rótulos de mes son BOTONES desde el M4: pulsar un mes del año
             abre ese mes. Y por eso el contenedor ya no va `aria-hidden` —lo
             estaba en el M2, cuando solo eran rótulos—: un control enfocable
             escondido del lector de pantalla es peor que no tenerlo. Quien
             nombra sigue siendo el `.u-sr-only`, porque la abreviatura visible
             («sept») no dice a dónde lleva. -->
        <div class="journal-year__months">
          <button
            v-for="mes in meses"
            :key="mes.indice"
            type="button"
            class="journal-year__month"
            :style="{ gridColumn: `span ${mes.semanas}` }"
            @click="$emit('select-month', mes.clave)"
          >
            <span aria-hidden="true">{{ mes.rotulo }}</span>
            <span class="u-sr-only">{{ mes.etiqueta }}</span>
          </button>
        </div>

        <div
          class="journal-year__weekdays"
          aria-hidden="true"
        >
          <span
            v-for="(rotulo, i) in rotulosDia"
            :key="i"
          >{{ rotulo }}</span>
        </div>

        <div class="journal-year__grid">
          <!-- Los huecos delante del 1 de enero. La semana empieza en LUNES y
               `getDay()` devuelve 0 el domingo: `(getDay() + 6) % 7`. Sin la
               rotación el año entero se desplaza una fila y ningún test lo nota. -->
          <div
            v-for="hueco in huecosIniciales"
            :key="'hueco-' + hueco"
            class="journal-year__pad"
          />

          <button
            v-for="(dia, i) in dias"
            :key="dia.date"
            type="button"
            class="journal-year__day"
            :class="'journal-year__day--n' + dia.nivel"
            :tabindex="dia.date === focoEfectivo ? 0 : -1"
            @keydown="moverFoco($event, i)"
            @click="abrirDia(dia)"
          >
            <span class="u-sr-only">{{ dia.etiqueta }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- La leyenda va FUERA del contenedor con scroll: dentro se iba a la derecha
         del lienzo y en móvil había que recorrer el año entero para encontrarla. -->
    <p class="journal-year__legend">
      <span>{{ t('journal.calendar.less') }}</span>
      <span
        v-for="nivel in [0, 1, 2, 3, 4]"
        :key="nivel"
        class="journal-year__key"
        :class="'journal-year__day--n' + nivel"
        aria-hidden="true"
      />
      <span>{{ t('journal.calendar.more') }}</span>
    </p>

    <p
      v-if="!isLoadingCalendar && calendarTotal === 0"
      class="journal-year__empty"
    >
      {{ t('journal.calendar.emptyYear') }}
    </p>
  </section>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useJournalStore } from '@/store/journal'
import { useI18n } from '@/composables/useI18n'
import { intlLocale } from '@/config/i18n'

/**
 * El heatmap del año del diario — M2 del [[Plan - Calendario del Diario]].
 *
 * Rejilla de 7 filas (lunes→domingo) y una columna por semana, con
 * `grid-auto-flow: column`: 53 columnas casi siempre, 54 cuando el año arranca
 * de tal forma que la primera y la última semana quedan partidas. El número de
 * columnas es **emergente** —columnas implícitas— y no está escrito en el CSS:
 * fijarlo en 53 recortaría el último día de esos años.
 *
 * Una fecha aquí es una CADENA `YYYY-MM-DD`, nunca un `Date`: `new Date('2026-01-01')`
 * se interpreta como UTC y al oeste de Greenwich pinta el día anterior. Solo se
 * construye un `Date` con componentes numéricos (que sí es local) para saber en
 * qué día de la semana cae algo, y con la hora del mediodía para formatear.
 */

const props = defineProps({
  /**
   * Año por el que abrir. Por defecto el que corre; el M4 lo traerá de la URL
   * (`?view=year&date=2025`). Las flechas mueven un estado interno a partir de
   * aquí: la prop dice por dónde se empieza, no manda sobre la navegación.
   */
  year: {
    type: Number,
    default: () => new Date().getFullYear()
  }
})

/**
 * Los dos avisos que sube al conmutador (M4). El estado de la URL lo escribe
 * `JournalView`, no este componente: aquí no se enruta.
 *
 * - `select-day` con la cadena `YYYY-MM-DD` del día pulsado, igual que
 *   `JournalCalendarMonth`: los dos calendarios llevan al mismo sitio.
 * - `select-month` con la cadena `YYYY-MM` del mes pulsado.
 * - `select-year` con el año al que llevan las flechas, para que `?date=` lo
 *   siga y recargar no lo pierda. El año se mueve **también** por dentro, así
 *   que el componente sigue funcionando montado a pelo, como en sus tests.
 */
const emit = defineEmits(['select-day', 'select-month', 'select-year'])

/** Azúcar para leerlo en `irA`, que ya hace tres cosas. */
const emitirAnio = (destino) => emit('select-year', destino)

// Un día vacío no emite: llevaría el listado a una fecha sin nada. La celda NO se
// deshabilita, a diferencia de la del mes (`JournalDayCell`), porque un `disabled`
// la saca del orden de foco y rompería el recorrido con flechas del año entero,
// que es lo que hace navegable el heatmap con teclado.
const abrirDia = (dia) => {
  if (dia.nivel > 0) emit('select-day', dia.date)
}

const { t } = useI18n()

const journalStore = useJournalStore()
const {
  calendarDays,
  calendarTotal,
  calendarYears,
  calendarCounts,
  isLoadingCalendar,
  media
} = storeToRefs(journalStore)

/** Los cuatro escalones de intensidad. El 0 —día sin nada— no cuenta como escalón. */
const NIVELES = 4

const anio = ref(props.year)

// ── el reparto por cuantiles ────────────────────────────────────────────────

/**
 * Los umbrales que separan los cuatro escalones, en cuentas de entradas.
 *
 * Por **cuantiles del año** y no por umbrales fijos: con `1/2/3/4+` quien apunta
 * cuarenta cosas al día y quien apunta dos verían el mismo mapa plano.
 *
 * Las dos series degeneradas que rompen un cuantil ingenuo se resuelven antes de
 * calcularlo: si hay **menos valores distintos que escalones**, la escalera son
 * los valores mismos. Un año entero de días con una entrada no se puede repartir
 * en cuatro —forzarlo pintaría diferencias que no existen— y sale entero en el
 * escalón 1, que es el que está medido para destacar sobre la celda vacía; y un
 * único día con cuarenta entradas tampoco tiene con qué compararse. En cuanto
 * hay dos valores distintos, se ven dos escalones: eso es lo que no puede
 * perderse.
 *
 * @param {number[]} cuentas Las cuentas de los días CON entradas.
 * @returns {number[]} umbrales crecientes, de 0 a 3 elementos.
 */
function umbralesDe (cuentas) {
  const ordenadas = [...cuentas].sort((a, b) => a - b)
  const distintos = [...new Set(ordenadas)]

  if (distintos.length <= NIVELES) {
    // Sin el mayor: el último valor no separa nada, ya está por encima de todo.
    return distintos.slice(0, -1)
  }

  const cortes = []

  for (let i = 1; i < NIVELES; i++) {
    // Rango más cercano. `ordenadas.length` es ≥ 5 aquí (hay más de cuatro
    // valores distintos), así que el índice nunca se sale ni hay división por cero.
    const posicion = Math.ceil((i / NIVELES) * ordenadas.length) - 1
    cortes.push(ordenadas[Math.max(0, posicion)])
  }

  // Sin repetidos y sin el máximo del año: un corte igual al mayor valor dejaría
  // el escalón de arriba sin un solo día, que es el mapa plano de nuevo.
  const maximo = distintos[distintos.length - 1]

  return [...new Set(cortes)].filter((corte) => corte < maximo)
}

const umbrales = computed(() => umbralesDe(calendarCounts.value))

/**
 * El escalón de un día: 0 si no hay nada, y de 1 a 4 según cuántos umbrales
 * supera su cuenta.
 */
function nivelDe (cuenta) {
  if (!cuenta) {
    return 0
  }

  let nivel = 1

  for (const umbral of umbrales.value) {
    if (cuenta > umbral) {
      nivel++
    }
  }

  return Math.min(nivel, NIVELES)
}

// ── la rejilla ──────────────────────────────────────────────────────────────

const dosDigitos = (n) => String(n).padStart(2, '0')

/**
 * Un formateador por render y no una llamada a `toLocaleDateString` por día: son
 * 365 celdas y `Intl.DateTimeFormat` es lo caro. Se lee `intlLocale()` dentro del
 * `computed`, así que cambiar de idioma repinta las etiquetas sin recargar.
 */
const formatoLargo = computed(() => new Intl.DateTimeFormat(intlLocale(), {
  day: 'numeric',
  month: 'long',
  year: 'numeric'
}))

const formatoMes = computed(() => new Intl.DateTimeFormat(intlLocale(), { month: 'short' }))

/** El nombre entero del mes, que es lo que nombra al botón para un lector. */
const formatoMesLargo = computed(() => new Intl.DateTimeFormat(intlLocale(), {
  month: 'long',
  year: 'numeric'
}))

const rotulosDia = computed(() => {
  const estrecho = new Intl.DateTimeFormat(intlLocale(), { weekday: 'narrow' })

  // El 5 de enero de 2026 es lunes: siete días desde ahí son la semana entera en
  // el orden en que se pinta. Se construye con componentes numéricos, que el
  // constructor interpreta en hora local.
  return Array.from({ length: 7 }, (_, i) => estrecho.format(new Date(2026, 0, 5 + i)))
})

const huecosIniciales = computed(() => (new Date(anio.value, 0, 1).getDay() + 6) % 7)

const dias = computed(() => {
  const salida = []
  const formato = formatoLargo.value

  for (let mes = 1; mes <= 12; mes++) {
    // Día 0 del mes siguiente es el último del actual: cubre febrero bisiesto sin
    // escribir la regla de los años bisiestos a mano.
    const largo = new Date(anio.value, mes, 0).getDate()

    for (let dia = 1; dia <= largo; dia++) {
      const date = `${anio.value}-${dosDigitos(mes)}-${dosDigitos(dia)}`
      const cuenta = calendarDays.value[date]?.count ?? 0
      // Mediodía: así ningún desplazamiento de zona mueve la fecha de día al
      // formatearla. Es lo que hace `utils/dates.js:journalDayLabel`.
      const legible = formato.format(new Date(`${date}T12:00:00`))

      salida.push({
        date,
        nivel: nivelDe(cuenta),
        etiqueta: cuenta
          ? t('journal.calendar.dayEntries', { n: cuenta, date: legible })
          : t('journal.calendar.dayEmpty', { date: legible })
      })
    }
  }

  return salida
})

/**
 * Los doce rótulos de mes con cuántas columnas ocupa cada uno. La columna de un
 * día es `floor((huecos + índice) / 7)`, así que el ancho de un mes es la
 * distancia entre su primera columna y la del siguiente.
 */
const meses = computed(() => {
  const huecos = huecosIniciales.value
  const total = dias.value.length
  const columnas = Math.ceil((huecos + total) / 7)
  const formato = formatoMes.value

  const inicios = []
  let acumulado = 0

  for (let mes = 1; mes <= 12; mes++) {
    inicios.push(Math.floor((huecos + acumulado) / 7))
    acumulado += new Date(anio.value, mes, 0).getDate()
  }

  const largo = formatoMesLargo.value

  return inicios.map((inicio, i) => ({
    indice: i,
    // La CADENA `YYYY-MM`, que es lo que espera el conmutador: un mes no se
    // manda como dos números por lo mismo que un día no se manda como `Date`.
    clave: `${anio.value}-${dosDigitos(i + 1)}`,
    rotulo: formato.format(new Date(anio.value, i, 1)),
    etiqueta: t('journal.calendar.openMonth', { month: largo.format(new Date(anio.value, i, 1)) }),
    semanas: (i === 11 ? columnas : inicios[i + 1]) - inicio
  }))
})

// ── recorrido con teclado ───────────────────────────────────────────────────

/**
 * Un año son 365 celdas, y 365 paradas de tabulador delante del resto de la
 * página no es «se recorre con teclado», es un muro. La rejilla es **una** parada
 * —tabindex rodante— y dentro se anda con las flechas: arriba y abajo cambian de
 * día de la semana, izquierda y derecha de semana, `Inicio` y `Fin` van a los
 * extremos del año. Es el patrón de rejilla de siempre, y deja cada día siendo un
 * `<button>` de verdad, como pide el plan.
 */
const foco = ref(null)

const focoEfectivo = computed(() => {
  if (foco.value) {
    return foco.value
  }

  // La primera parada útil es el primer día con algo: en un año casi vacío,
  // aterrizar en el 1 de enero obliga a cruzarlo entero a ciegas.
  return dias.value.find((dia) => dia.nivel > 0)?.date ?? dias.value[0]?.date ?? null
})

const SALTOS = {
  ArrowUp: -1,
  ArrowDown: 1,
  ArrowLeft: -7,
  ArrowRight: 7
}

function moverFoco (evento, indice) {
  let destino = null

  if (evento.key in SALTOS) {
    destino = indice + SALTOS[evento.key]
  } else if (evento.key === 'Home') {
    destino = 0
  } else if (evento.key === 'End') {
    destino = dias.value.length - 1
  } else {
    return
  }

  evento.preventDefault()
  destino = Math.max(0, Math.min(dias.value.length - 1, destino))
  foco.value = dias.value[destino].date

  // Se busca en el DOM y no con un `ref` por celda: 365 refs por render para
  // mover el foco una vez es mucho andamio. La lista es estable —solo cambia el
  // `tabindex`—, así que no hace falta esperar al siguiente ciclo.
  const celdas = evento.currentTarget.parentElement.querySelectorAll('.journal-year__day')

  celdas[destino]?.focus()
}

// ── navegación entre años ───────────────────────────────────────────────────

/**
 * Los años que devuelve la acción, ordenados aquí y no confiando en el backend:
 * el navegador se apoya en el orden y una lista al revés dejaría las dos flechas
 * apuntando al mismo sitio.
 */
const aniosOrdenados = computed(() => [...calendarYears.value].sort((a, b) => a - b))

const anioAnterior = computed(() =>
  aniosOrdenados.value.filter((y) => y < anio.value).pop() ?? null
)

const anioSiguiente = computed(() =>
  aniosOrdenados.value.find((y) => y > anio.value) ?? null
)

async function irA (destino) {
  if (destino === null) {
    return
  }

  anio.value = destino
  foco.value = null
  emitirAnio(destino)
  await journalStore.fetchCalendar(destino)
  await colocar()
}

// ── la posición inicial del scroll ──────────────────────────────────────────

/**
 * **El año abre por el mes actual, no por enero** (enmienda del M4).
 *
 * A 390 px solo caben cinco meses dentro del contenedor con scroll, así que
 * abrir por enero le enseña una pantalla vacía a quien tiene sus entradas en
 * septiembre. La regla es: el mes de hoy si el año que se mira es el actual, y
 * el ÚLTIMO mes con entradas si es otro —un año pasado se recuerda por donde se
 * acabó, no por donde empezó—. Un año sin nada abre por enero, que es lo único
 * que queda.
 *
 * @returns {number} índice de mes, 0-11
 */
const mesInicial = computed(() => {
  const hoy = new Date()

  if (anio.value === hoy.getFullYear()) {
    return hoy.getMonth()
  }

  let ultimo = 0

  // Por la CADENA de la fecha, sin construir un `Date`: es la misma regla que
  // el resto del componente.
  for (const fecha of Object.keys(calendarDays.value)) {
    if (fecha.startsWith(`${anio.value}-`)) {
      ultimo = Math.max(ultimo, Number(fecha.slice(5, 7)) - 1)
    }
  }

  return ultimo
})

const scrollEl = ref(null)

/**
 * Deja el mes inicial pegado al borde izquierdo del contenedor con scroll.
 *
 * Se escribe `scrollLeft` y **no** `scrollIntoView`: aquel movería también la
 * página vertical para traer el heatmap a la vista, y esto es la posición de
 * partida de un componente, no una petición de ir a ningún sitio. Y sin
 * `scroll-behavior: smooth` por lo mismo: nadie ha pedido un desplazamiento,
 * así que no hay nada que animar.
 *
 * Se mide con `getBoundingClientRect` y no con `offsetLeft` porque el
 * contenedor no está posicionado y `offsetParent` sería cualquier ancestro.
 */
async function colocar () {
  await nextTick()

  const caja = scrollEl.value

  if (!caja) {
    return
  }

  const objetivo = caja.querySelectorAll('.journal-year__month')[mesInicial.value]

  if (!objetivo) {
    return
  }

  caja.scrollLeft += objetivo.getBoundingClientRect().left - caja.getBoundingClientRect().left
}

// ── la carga ────────────────────────────────────────────────────────────────

/**
 * `calendarYear` es la caché: si ya está cargado ese año no se vuelve a pedir.
 * `setMedia` la pone a `null` justo para que el `watch` de abajo la refresque.
 */
async function cargar () {
  if (journalStore.calendarYear !== anio.value) {
    await journalStore.fetchCalendar(anio.value)
  }

  await colocar()
}

/**
 * **El filtro por medio manda también sobre el heatmap** (enmienda del M4).
 * Vive aquí y no en el store porque solo la vista abierta está montada: la que
 * no lo esté lo pedirá al montarse, sin gastar una petición que nadie mira.
 */
watch(media, cargar)

// El año puede venir de fuera —`?date=2025` en la URL— sin que el componente se
// desmonte. Sin esto, volver atrás en el navegador cambiaría la URL y dejaría el
// mapa donde estaba.
watch(() => props.year, (nuevo) => {
  if (nuevo !== anio.value) {
    anio.value = nuevo
    foco.value = null
    cargar()
  }
})

onMounted(cargar)
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.journal-year {
  // MEDIDA, no espacio: es el lado de una celda concreta, y cambiarla al cambiar
  // la densidad de la interfaz encogería el año hasta no verse. 53 columnas de
  // 13 px con 2 px de hueco más la columna de rótulos son ~808 px, y el ancho útil
  // de `/journal` a 1400 px de viewport es 851: el año cabe entero. A 14 px se
  // pasaba diez píxeles y aparecía la barra de scroll en escritorio, medido.
  --celda-anio: 13px;

  max-width: 100%;

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
    font-variant-numeric: tabular-nums;
  }

  &__total {
    margin: 0 0 0 auto;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }

  &__scroll {
    max-width: 100%;
    overflow-x: auto;
    padding-bottom: spacing(2xs);

    @include scrollbar-thin;
  }

  &__canvas {
    display: grid;
    grid-template-columns: auto max-content;
    gap: spacing(3xs);
    width: max-content;
  }

  &__months {
    grid-row: 1;
    grid-column: 2;
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: var(--celda-anio);
    gap: spacing(3xs);
  }

  // El rótulo de mes es un botón desde el M4: lleva al mes. Fuera de la escala
  // de `.btn` a propósito, como el conmutador y las píldoras — es un rótulo que
  // además se puede pulsar, no un botón de la interfaz.
  &__month {
    @include button-reset;
    @include focus-ring;

    // Un mes ocupa ~4,3 columnas: el rótulo abreviado cabe, y lo que se pase se
    // recorta antes que empujar la columna y descuadrar la rejilla de debajo.
    overflow: hidden;
    padding: 0;
    text-align: left;
    font-size: var(--font-size-xs);
    line-height: 1.2;
    color: var(--color-text-muted);
    white-space: nowrap;

    &:hover {
      color: var(--color-primary);
    }
  }

  &__weekdays {
    grid-row: 2;
    grid-column: 1;
    display: grid;
    grid-template-rows: repeat(7, var(--celda-anio));
    gap: spacing(3xs);

    span {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding-right: spacing(3xs);
      font-size: var(--font-size-xs);
      line-height: 1;
      // `--color-text-light` es #ffffff en los DOS temas y `tokens/_colors.scss:35`
      // lo acota a superficies oscuras o de color: sobre el fondo de página el token
      // atenuado es `muted` (4.59).
      color: var(--color-text-muted);
    }
  }

  // 7 filas fijas y las columnas que hagan falta: 53 casi siempre, 54 cuando el
  // año parte la primera y la última semana. `grid-auto-flow: column` es lo que
  // hace que el DOM se recorra por semanas y no por filas.
  &__grid {
    grid-row: 2;
    grid-column: 2;
    display: grid;
    grid-auto-flow: column;
    grid-template-rows: repeat(7, var(--celda-anio));
    grid-auto-columns: var(--celda-anio);
    gap: spacing(3xs);
  }

  &__pad {
    width: var(--celda-anio);
    height: var(--celda-anio);
  }

  &__day,
  &__key {
    width: var(--celda-anio);
    height: var(--celda-anio);
    border-radius: radius(sm);
    border: 1px solid transparent;
  }

  &__day {
    @include button-reset;
    @include focus-ring;

    padding: 0;

    &:hover {
      border-color: var(--color-border-hover);
    }
  }

  // Los cinco escalones. El 0 no tiene token propio a propósito: un día sin nada
  // es la superficie, no un color del mapa. Lleva el hairline para que la celda
  // vacía siga leyéndose como celda sobre el fondo de página.
  &__day--n0 {
    background: var(--color-background-soft);
    border-color: var(--color-border-light);
  }

  &__day--n1 { background: var(--color-journal-heat-1); }
  &__day--n2 { background: var(--color-journal-heat-2); }
  &__day--n3 { background: var(--color-journal-heat-3); }
  &__day--n4 { background: var(--color-journal-heat-4); }

  &__legend {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: spacing(3xs);
    margin: spacing(2xs) 0 0;
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }

  &__empty {
    margin: spacing(sm) 0 0;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
  }
}
</style>
