<template>
  <Teleport to="body">
    <!-- El overlay cierra al pulsar fuera, pero no es un control: envuelve al
         propio diálogo. El cierre por teclado es Escape, en `useFocusTrap`. -->
    <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
    <div
      v-if="modelValue"
      :class="['base-modal', $attrs.class]"
      @click="onOverlayClick"
    >
      <div
        ref="dialogRef"
        class="base-modal__dialog"
        :class="`base-modal__dialog--${size}`"
        :style="accent ? { borderTopColor: accent, borderTopWidth: '3px', borderTopStyle: 'solid' } : null"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        @click.stop
      >
        <header class="base-modal__header">
          <h2
            :id="titleId"
            class="base-modal__title"
          >
            <i
              v-if="icon"
              :class="[icon, `base-modal__icon--${iconTone}`]"
              aria-hidden="true"
            />
            {{ title }}
          </h2>
          <button
            v-if="dismissible"
            type="button"
            class="base-modal__close"
            @click="cerrar"
          >
            <i
              class="fas fa-times"
              aria-hidden="true"
            />
            <span class="u-sr-only">{{ t('common.close') }}</span>
          </button>
        </header>

        <div class="base-modal__body">
          <slot />
        </div>

        <!-- Sin contenido de pie no se pinta el pie: un hueco con borde y sin
             nada dentro se lee como un fallo de carga. -->
        <footer
          v-if="$slots.footer"
          class="base-modal__footer"
        >
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, useId } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

// La raíz de este componente es un `<Teleport>`, y Vue no hereda atributos en un
// Teleport: sin esto, la `class` que le pase quien lo use se PIERDE en silencio.
// Lo destapó el icono de `ConfirmationModal`, que salía teal en vez de rojo
// porque su `confirmation-modal--danger` nunca llegaba al DOM.
defineOptions({ inheritAttrs: false })

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  // 380 / 560 / 760 px, y `full` para lo que necesite el ancho de la pantalla.
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg', 'full'].includes(v)
  },
  icon: { type: String, default: '' },
  // La tinta del icono viaja como prop y no como clase del consumidor: la raíz
  // de este componente es un `<Teleport>`, así que ni los atributos heredados ni
  // el `data-v-` del padre llegan, y un `:deep()` desde fuera no engancha nada.
  iconTone: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'warning', 'danger', 'info', 'success'].includes(v)
  },
  // Color del filete superior, si el modal quiere señalar de qué habla. Se pasa
  // resuelto (`var(--color-card-book-accent)`) y no como nombre de entidad, para
  // que `BaseModal` no tenga que saber qué medios existen.
  accent: { type: String, default: '' },
  closeOnOverlay: { type: Boolean, default: true },
  // `false` para un proceso en curso: ni X ni Escape, para no dejarlo a medias.
  dismissible: { type: Boolean, default: true }
})

const emit = defineEmits(['update:modelValue', 'close'])

const dialogRef = ref(null)
const titleId = `base-modal-title-${useId()}`

const cerrar = () => {
  if (!props.dismissible) return
  emit('update:modelValue', false)
  emit('close')
}

const onOverlayClick = () => {
  if (props.closeOnOverlay) cerrar()
}

// El propio composable ignora el Escape mientras haya un desplegable de PrimeVue
// abierto, así que aquí no hay que comprobarlo otra vez.
useFocusTrap(dialogRef, {
  isOpen: () => props.modelValue,
  onEscape: cerrar
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/modal' as *;

// `scoped` alcanza el marcado que este componente renderiza —cabecera, cuerpo y
// pie son suyos—; lo que entra por los slots lo estiliza quien lo pasa, que es
// justo lo que se quiere. La trampa de `scoped` es el marcado que pinta un HIJO,
// y aquí no hay ninguno.
.base-modal {
  @include modal-overlay-base(modal);
  @include modal-overlay-blur;

  padding: spacing(md);
}

.base-modal__dialog {
  @include modal-content-base(560px);

  display: flex;
  flex-direction: column;
  padding: 0;
  animation: modalSlideIn transition(medium) ease-out;

  // Los cuatro anchos, acotados al viewport: un ancho fijo desborda en móvil.
  &--sm   { width: min(380px, 90vw); }
  &--md   { width: min(560px, 90vw); }
  &--lg   { width: min(760px, 90vw); }
  &--full { width: 95vw; }
}

.base-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: spacing(sm);
  padding: spacing(md) spacing(lg);
  border-bottom: 1px solid var(--color-border-light);
}

.base-modal__title {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  margin: 0;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
  color: var(--color-text);
}

// El color del icono lo decide `icon-tone` y nada más: un `i { color: … }` en
// `.base-modal__title` le ganaría por especificidad (0,1,1 contra 0,1,0) y dejaría
// la prop sin efecto, que es lo que pasaba al medirlo.
.base-modal__icon {
  &--primary { color: var(--color-primary); }
  &--warning { color: var(--color-warning); }
  &--danger  { color: var(--color-error); }
  &--info    { color: var(--color-info); }
  &--success { color: var(--color-success); }
}

.base-modal__close {
  @include button-reset;

  display: flex;
  align-items: center;
  justify-content: center;
  padding: spacing(2xs);
  border-radius: radius(sm);
  color: var(--color-text-muted);
  font-size: var(--font-size-md);
  transition: transition(fast);

  &:hover { color: var(--color-error); }
}

// El cuerpo scrollea por su cuenta: así la cabecera y el pie no se van de la
// pantalla en un modal largo.
.base-modal__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: spacing(lg);
}

// Ordenación fija: secundario a la izquierda, primario a la derecha. Y nunca
// botones solo icono aquí — el pie es donde se decide, y un icono sin etiqueta
// obliga a adivinar.
.base-modal__footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: spacing(sm);
  padding: spacing(md) spacing(lg);
  border-top: 1px solid var(--color-border-light);
}
</style>
