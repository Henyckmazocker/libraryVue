<template>
  <!-- El overlay, la trampa de foco, el Escape y la ordenación del pie los pone
       `BaseModal`. Aquí queda lo propio de una confirmación: el icono por tipo,
       la lista de detalles y el campo de confirmación por texto. -->
  <BaseModal
    :model-value="isVisible"
    :title="title"
    :icon="iconName"
    :icon-tone="type"
    :size="baseSize"
    :close-on-overlay="closeOnOverlay && !isProcessing"
    :dismissible="!isProcessing"
    :class="`confirmation-modal confirmation-modal--${type}`"
    @close="handleCancel"
  >
    <!-- eslint-disable vue/no-v-html -- saneado con utils/sanitize.js -->
    <p
      class="modal-message"
      v-html="sanitizePlain(message)"
    />
    <!-- eslint-enable vue/no-v-html -->

    <div
      v-if="details && details.length > 0"
      class="modal-details"
    >
      <ul>
        <li
          v-for="(detail, index) in details"
          :key="index"
        >
          {{ detail }}
        </li>
      </ul>
    </div>

    <div
      v-if="requiresTextConfirmation"
      class="confirmation-input"
    >
      <label :for="inputId">{{ textConfirmationLabel }}</label>
      <input
        :id="inputId"
        v-model="confirmationText"
        type="text"
        :placeholder="textConfirmationPlaceholder"
        class="form-control"
        @keyup.enter="handleConfirm"
      >
      <small class="text-muted">{{ textConfirmationHint }}</small>
    </div>

    <template #footer>
      <button
        type="button"
        class="btn btn--ghost"
        :disabled="isProcessing"
        @click="handleCancel"
      >
        {{ cancelText }}
      </button>
      <button
        type="button"
        :class="confirmButtonClasses"
        :disabled="isConfirmDisabled"
        :aria-busy="isProcessing"
        @click="handleConfirm"
      >
        <i
          v-if="isProcessing"
          class="fas fa-spinner fa-spin"
          aria-hidden="true"
        />
        {{ isProcessing ? processingText : confirmText }}
      </button>
    </template>
  </BaseModal>
</template>

<script>
import { ref, computed, nextTick, watch } from 'vue'
import BaseModal from './BaseModal.vue'
import { sanitizePlain } from '@/utils/sanitize'

export default {
  name: 'ConfirmationModal',
  components: { BaseModal },
  props: {
    // Control de visibilidad
    isVisible: {
      type: Boolean,
      default: false
    },
    
    // Contenido del modal
    title: {
      type: String,
      default: 'Confirmar acción'
    },
    message: {
      type: String,
      default: ''
    },
    details: {
      type: Array,
      default: () => []
    },
    
    // Tipo de modal (define estilos y iconos)
    type: {
      type: String,
      default: 'warning', // warning, danger, info, success
      validator: (value) => ['warning', 'danger', 'info', 'success'].includes(value)
    },
    
    // Textos de botones
    confirmText: {
      type: String,
      default: 'Confirmar'
    },
    cancelText: {
      type: String,
      default: 'Cancelar'
    },
    processingText: {
      type: String,
      default: 'Procesando...'
    },
    
    // Confirmación por texto
    requiresTextConfirmation: {
      type: Boolean,
      default: false
    },
    textConfirmationValue: {
      type: String,
      default: ''
    },
    textConfirmationLabel: {
      type: String,
      default: 'Para confirmar, escribe el texto exacto:'
    },
    textConfirmationPlaceholder: {
      type: String,
      default: 'Escribe aquí...'
    },
    textConfirmationHint: {
      type: String,
      default: ''
    },
    
    // Estado de procesamiento
    isProcessing: {
      type: Boolean,
      default: false
    },
    
    // Configuración
    closeOnOverlay: {
      type: Boolean,
      default: true
    },
    size: {
      type: String,
      default: 'medium', // small, medium, large
      validator: (value) => ['small', 'medium', 'large'].includes(value)
    }
  },
  
  emits: ['confirm', 'cancel', 'close'],
  
  setup(props, { emit }) {
    const confirmationText = ref('')
    const inputId = `confirmation-input-${Math.random().toString(36).substr(2, 9)}`
    
    // Computed properties
    // `small|medium|large` es la API pública de este componente desde antes de
    // que existiera `BaseModal`; se traduce aquí para no tocar a sus llamantes.
    const baseSize = computed(() => (
      { small: 'sm', medium: 'md', large: 'lg' }[props.size] || 'md'
    ))
    
    const iconName = computed(() => {
      const icons = {
        warning: 'fas fa-exclamation-triangle',
        danger: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle',
        success: 'fas fa-check-circle'
      }
      return icons[props.type] || icons.warning
    })
    
    // El tipo del diálogo lo comunica el icono de la cabecera, no el color del
    // botón: en la escala de `_buttons.scss` un botón solo distingue si destruye
    // datos o no. Antes, `info` lo pintaba con `--color-info`, que es la pareja
    // de `--color-error` y `--color-warning` y no un color de acción.
    const confirmButtonClasses = computed(() =>
      props.type === 'danger' ? 'btn btn--danger' : 'btn btn--primary'
    )
    
    const isConfirmDisabled = computed(() => {
      if (props.isProcessing) return true
      if (props.requiresTextConfirmation) {
        return confirmationText.value !== props.textConfirmationValue
      }
      return false
    })
    
    // Methods
    const handleConfirm = () => {
      if (isConfirmDisabled.value) return
      emit('confirm')
    }
    
    const handleCancel = () => {
      if (props.isProcessing) return
      resetForm()
      emit('cancel')
    }
    
    
    const resetForm = () => {
      confirmationText.value = ''
    }
    
    // Watchers
    watch(() => props.isVisible, async (newValue) => {
      if (newValue) {
        resetForm()
        // Enfocar el input si se requiere confirmación por texto
        if (props.requiresTextConfirmation) {
          await nextTick()
          const input = document.getElementById(inputId)
          if (input) input.focus()
        }
      }
    })
    
    return {
      sanitizePlain,
      confirmationText,
      inputId,
      baseSize,
      iconName,
      confirmButtonClasses,
      isConfirmDisabled,
      handleConfirm,
      handleCancel
    }
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

// El overlay, la caja, la cabecera, el pie y los tamaños los pone `BaseModal`;
// la tinta del icono va por su prop `icon-tone`. Aquí queda solo lo que este
// componente pinta DENTRO del slot.

.modal-message {
  margin: 0 0 16px;
  color: var(--color-text-secondary);
  line-height: 1.5;
}

.modal-details {
  margin-top: 16px;
  padding: 12px;
  background-color: var(--color-background-soft);
  border-radius: 6px;
  border-left: 4px solid var(--color-border);
}

.modal-details ul {
  margin: 0;
  padding-left: 20px;
}

.modal-details li {
  margin-bottom: 4px;
  color: var(--color-text-secondary);
}

.confirmation-input {
  margin-top: 20px;
  padding: 16px;
  background-color: var(--color-background-soft);
  border-radius: 8px;
  border: 2px dashed var(--color-border);
}

.confirmation-input label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--color-text-secondary);
}

.confirmation-input .form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  font-size: 14px;
}

.confirmation-input .form-control:focus {
  outline: none;
  border-color: var(--color-info);
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.confirmation-input .text-muted {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  color: var(--color-text-muted);
}
</style>