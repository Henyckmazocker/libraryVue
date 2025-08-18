<template>
  <!-- Import Modal -->
  <div v-if="show" class="modal-overlay" @click="handleClose">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <h2><i class="fas fa-upload"></i> Importar datos</h2>
        <button @click="handleClose" class="close-button">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div class="modal-body">
        <!-- Service Selector Component -->
        <ServiceSelector 
          v-model="selectedService"
          @service-changed="handleServiceChange"
        />

        <!-- File Uploader Component -->
        <FileUploader 
          ref="fileUploader"
          v-model="selectedFile"
          @file-selected="handleFileSelect"
        />

        <!-- Import Status Component -->
        <ImportStatus 
          :status="importStatus"
          :progress="importProgress"
        />
      </div>

      <div class="modal-footer">
        <button @click="handleClose" class="cancel-button">
          <i class="fas fa-times"></i>
        </button>
        <button 
          @click="handleImport" 
          :disabled="!canImport"
          class="import-submit-button"
        >
          <i v-if="isImporting" class="fas fa-spinner fa-spin"></i>
          <i v-else class="fas fa-upload"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import ServiceSelector from './import/ServiceSelector.vue';
import FileUploader from './import/FileUploader.vue';
import ImportStatus from './import/ImportStatus.vue';
import { useFileImport } from '@/composables/useFileImport';

// Props
const props = defineProps({
  show: {
    type: Boolean,
    default: false
  }
});

// Emits
const emit = defineEmits(['close', 'import-success']);

// Usar composable de importación
const {
  selectedService,
  selectedFile,
  importStatus,
  importProgress,
  canImport,
  isImporting,
  resetImport,
  startImport,
  setService,
  setFile
} = useFileImport();

// Refs
const fileUploader = ref(null);

// Methods
const handleClose = () => {
  resetImport();
  if (fileUploader.value) {
    fileUploader.value.resetFile();
  }
  emit('close');
};

const handleServiceChange = (service) => {
  setService(service);
};

const handleFileSelect = (file) => {
  setFile(file);
};

const handleImport = async () => {
  const result = await startImport();
  
  if (result.success) {
    // Emit success event to parent component
    emit('import-success', result.data);
    
    // Close modal after successful import
    setTimeout(() => {
      handleClose();
    }, 2000);
  }
};

// Watch for show prop changes to reset form when modal opens
watch(() => props.show, (newValue) => {
  if (newValue) {
    resetImport();
    if (fileUploader.value) {
      fileUploader.value.resetFile();
    }
  }
});
</script>

<style scoped>
/* Modal styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: #2c2c2c;
  border-radius: 20px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 25px 30px 15px;
  border-bottom: 1px solid #444;
}

.modal-header h2 {
  color: #e0e0e0;
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
}

.close-button {
  background: none;
  border: none;
  color: #888;
  font-size: 2rem;
  cursor: pointer;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.close-button:hover {
  color: #e0e0e0;
  background: rgba(255, 255, 255, 0.1);
}

.modal-body {
  padding: 25px 30px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 15px;
  padding: 20px 30px 25px;
  border-top: 1px solid #444;
}

.cancel-button {
  padding: 10px 20px;
  font-size: 1rem;
  background: transparent;
  color: #888;
  border: 1px solid #555;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cancel-button:hover {
  color: #e0e0e0;
  border-color: #888;
}

.import-submit-button {
  padding: 10px 20px;
  font-size: 1rem;
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
}

.import-submit-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #0056b3, #004085);
  transform: translateY(-1px);
}

.import-submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
</style>
