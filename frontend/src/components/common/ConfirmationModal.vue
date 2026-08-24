<template>
  <!-- El overlay cierra al pulsar fuera, pero no es un control: envuelve al propio
       diálogo. El cierre por teclado es Escape, en useFocusTrap. -->
  <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
  <div 
    v-if="isVisible" 
    class="confirmation-modal-overlay"
    @click="handleOverlayClick"
  >
    <div 
      ref="dialogRef"
      class="confirmation-modal" 
      role="dialog"
      aria-modal="true"
      :class="modalClasses"
      @click.stop
    >
      <!-- Header -->
      <div class="modal-header">
        <div
          class="modal-icon"
          :class="iconClasses"
        >
          <i :class="iconName" />
        </div>
        <h3 class="modal-title">
          {{ title }}
        </h3>
      </div>

      <!-- Content -->
      <div class="modal-content">
        <p
          class="modal-message"
          v-html="message"
        />
        
        <!-- Lista de detalles adicionales si se proporcionan -->
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

        <!-- Campo de entrada si se requiere confirmación por texto -->
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
      </div>

      <!-- Actions -->
      <div class="modal-actions">
        <button 
          type="button" 
          class="btn btn-secondary" 
          :disabled="isProcessing"
          @click="handleCancel"
        >
          {{ cancelText }}
        </button>
        <button 
          type="button" 
          :class="confirmButtonClasses"
          :disabled="isConfirmDisabled"
          @click="handleConfirm"
        >
          <i
            v-if="isProcessing"
            class="fas fa-spinner fa-spin"
          />
          {{ isProcessing ? processingText : confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, nextTick, watch } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'

export default {
  name: 'ConfirmationModal',
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
    const modalClasses = computed(() => ({
      [`modal-${props.type}`]: true,
      [`modal-${props.size}`]: true
    }))
    
    const iconClasses = computed(() => ({
      [`icon-${props.type}`]: true
    }))
    
    const iconName = computed(() => {
      const icons = {
        warning: 'fas fa-exclamation-triangle',
        danger: 'fas fa-exclamation-circle',
        info: 'fas fa-info-circle',
        success: 'fas fa-check-circle'
      }
      return icons[props.type] || icons.warning
    })
    
    const confirmButtonClasses = computed(() => {
      const baseClasses = ['btn']
      const typeClasses = {
        warning: 'btn-warning',
        danger: 'btn-danger',
        info: 'btn-primary',
        success: 'btn-success'
      }
      baseClasses.push(typeClasses[props.type] || typeClasses.warning)
      return baseClasses.join(' ')
    })
    
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
    
    // El trap se salta el foco inicial cuando el modal pide texto de confirmación:
    // de eso ya se encarga el watcher de abajo, que enfoca ese input concreto.
    const dialogRef = ref(null)
    useFocusTrap(dialogRef, {
      isOpen: () => props.isVisible,
      onEscape: () => { if (!props.isProcessing) handleCancel() }
    })

    const handleOverlayClick = () => {
      if (props.closeOnOverlay && !props.isProcessing) {
        handleCancel()
      }
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
      dialogRef,
      confirmationText,
      inputId,
      modalClasses,
      iconClasses,
      iconName,
      confirmButtonClasses,
      isConfirmDisabled,
      handleConfirm,
      handleCancel,
      handleOverlayClick
    }
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.confirmation-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(2px);
}

.confirmation-modal {
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  animation: modalSlideIn 0.3s ease-out;
}

.modal-small { width: 400px; }
.modal-medium { width: 500px; }
.modal-large { width: 600px; }

// @keyframes modalSlideIn → definida globalmente en
// assets/styles/components/_modal.scss

.modal-header {
  display: flex;
  align-items: center;
  padding: 24px 24px 16px;
  border-bottom: 1px solid var(--color-border-light);
}

.modal-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16px;
  font-size: 20px;
}

.icon-warning {
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
}

.icon-danger {
  background-color: var(--color-error-bg);
  color: var(--color-error);
}

.icon-info {
  background-color: var(--color-info-bg);
  color: var(--color-info);
}

.icon-success {
  background-color: var(--color-success-bg);
  color: var(--color-success);
}

.modal-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text);
}

.modal-content {
  padding: 16px 24px;
}

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

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px 24px;
  border-top: 1px solid var(--color-border-light);
}

.btn {
  padding: 8px 20px;
  border-radius: 6px;
  border: none;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background-color: var(--color-border);
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background-color: var(--color-border);
}

.btn-warning {
  background-color: var(--color-warning);
  color: var(--color-text);
}

.btn-warning:hover:not(:disabled) {
  background-color: var(--color-warning);
}

.btn-danger {
  background-color: var(--color-error);
  color: var(--color-on-status);
}

.btn-danger:hover:not(:disabled) {
  background-color: var(--color-error);
}

.btn-primary {
  background-color: var(--color-info);
  color: var(--color-on-status);
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--color-info);
}

.btn-success {
  background-color: var(--color-success);
  color: var(--color-on-status);
}

.btn-success:hover:not(:disabled) {
  background-color: var(--color-success);
}

/* Variantes del modal por tipo */
.modal-warning {
  border-top: 4px solid var(--color-warning);
}

.modal-danger {
  border-top: 4px solid var(--color-error);
}

.modal-info {
  border-top: 4px solid var(--color-info);
}

.modal-success {
  border-top: 4px solid var(--color-success);
}

/* Responsive */
@include responsive-below(sm) {
  .confirmation-modal {
    margin: 10px;
    width: calc(100vw - 20px) !important;
  }
  
  .modal-header,
  .modal-content,
  .modal-actions {
    padding-left: 16px;
    padding-right: 16px;
  }
  
  .modal-actions {
    flex-direction: column-reverse;
  }
  
  .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>