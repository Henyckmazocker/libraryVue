<template>
  <!-- El overlay, la trampa de foco, el Escape y el pie los pone `BaseModal`.
       `dismissible` se apaga mientras se importa: cerrar a medias dejaría la
       importación en marcha sin nada que la enseñe. -->
  <BaseModal
    :model-value="show"
    title="Importar datos"
    icon="fas fa-upload"
    size="lg"
    :dismissible="!isImporting"
    :close-on-overlay="!isImporting"
    @close="handleClose"
  >
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

    <template #footer>
      <button
        class="btn btn--ghost btn--icon"
        @click="handleClose"
      >
        <i
          class="fas fa-times"
          aria-hidden="true"
        />
        <!-- `.btn--icon` exige nombre accesible, y este pie no tenía ninguno.
             La etiqueta VISIBLE («Cancelar» / «Importar») la pone el M4 del
             plan de componentes, junto con el resto del pie. -->
        <span class="u-sr-only">Cancelar</span>
      </button>
      <button 
        :disabled="!canImport" 
        class="btn btn--primary btn--icon"
        @click="handleImport"
      >
        <i
          v-if="isImporting"
          class="fas fa-spinner fa-spin"
          aria-hidden="true"
        />
        <i
          v-else
          class="fas fa-upload"
          aria-hidden="true"
        />
        <span class="u-sr-only">Importar</span>
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import BaseModal from './common/BaseModal.vue';
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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

/* Modal styles */
.modal-body {
  padding: spacing(lg) spacing(xl);
}

</style>
