<template>
  <div 
    v-if="isVisible" 
    class="confirmation-modal-overlay"
    @click="handleOverlayClick"
  >
    <div 
      class="confirmation-modal" 
      @click.stop
      :class="modalClasses"
    >
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-icon" :class="iconClasses">
          <i :class="iconName"></i>
        </div>
        <h3 class="modal-title">{{ title }}</h3>
      </div>

      <!-- Content -->
      <div class="modal-content">
        <p class="modal-message" v-html="message"></p>
        
        <!-- Lista de detalles adicionales si se proporcionan -->
        <div v-if="details && details.length > 0" class="modal-details">
          <ul>
            <li v-for="(detail, index) in details" :key="index">
              {{ detail }}
            </li>
          </ul>
        </div>

        <!-- Campo de entrada si se requiere confirmación por texto -->
        <div v-if="requiresTextConfirmation" class="confirmation-input">
          <label :for="inputId">{{ textConfirmationLabel }}</label>
          <input 
            :id="inputId"
            v-model="confirmationText"
            type="text"
            :placeholder="textConfirmationPlaceholder"
            class="form-control"
            @keyup.enter="handleConfirm"
          />
          <small class="text-muted">{{ textConfirmationHint }}</small>
        </div>
      </div>

      <!-- Actions -->
      <div class="modal-actions">
        <button 
          type="button" 
          class="btn btn-secondary" 
          @click="handleCancel"
          :disabled="isProcessing"
        >
          {{ cancelText }}
        </button>
        <button 
          type="button" 
          :class="confirmButtonClasses"
          @click="handleConfirm"
          :disabled="isConfirmDisabled"
        >
          <i v-if="isProcessing" class="fas fa-spinner fa-spin"></i>
          {{ isProcessing ? processingText : confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, computed, nextTick, watch } from 'vue'

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

<style scoped>
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

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-header {
  display: flex;
  align-items: center;
  padding: 24px 24px 16px;
  border-bottom: 1px solid #e9ecef;
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
  background-color: #fff3cd;
  color: #856404;
}

.icon-danger {
  background-color: #f8d7da;
  color: #721c24;
}

.icon-info {
  background-color: #d1ecf1;
  color: #0c5460;
}

.icon-success {
  background-color: #d4edda;
  color: #155724;
}

.modal-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #212529;
}

.modal-content {
  padding: 16px 24px;
}

.modal-message {
  margin: 0 0 16px;
  color: #495057;
  line-height: 1.5;
}

.modal-details {
  margin-top: 16px;
  padding: 12px;
  background-color: #f8f9fa;
  border-radius: 6px;
  border-left: 4px solid #6c757d;
}

.modal-details ul {
  margin: 0;
  padding-left: 20px;
}

.modal-details li {
  margin-bottom: 4px;
  color: #495057;
}

.confirmation-input {
  margin-top: 20px;
  padding: 16px;
  background-color: #f8f9fa;
  border-radius: 8px;
  border: 2px dashed #dee2e6;
}

.confirmation-input label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #495057;
}

.confirmation-input .form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.confirmation-input .form-control:focus {
  outline: none;
  border-color: #80bdff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.confirmation-input .text-muted {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  color: #6c757d;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px 24px;
  border-top: 1px solid #e9ecef;
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
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #5a6268;
}

.btn-warning {
  background-color: #ffc107;
  color: #212529;
}

.btn-warning:hover:not(:disabled) {
  background-color: #e0a800;
}

.btn-danger {
  background-color: #dc3545;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background-color: #c82333;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background-color: #0056b3;
}

.btn-success {
  background-color: #28a745;
  color: white;
}

.btn-success:hover:not(:disabled) {
  background-color: #1e7e34;
}

/* Variantes del modal por tipo */
.modal-warning {
  border-top: 4px solid #ffc107;
}

.modal-danger {
  border-top: 4px solid #dc3545;
}

.modal-info {
  border-top: 4px solid #007bff;
}

.modal-success {
  border-top: 4px solid #28a745;
}

/* Responsive */
@media (max-width: 576px) {
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