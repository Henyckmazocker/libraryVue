<template>
  <div class="form-group">
    <label for="file-input">Archivo de respaldo:</label>
    <input 
      id="file-input"
      ref="fileInput"
      type="file" 
      accept=".csv,.json,.xml,.txt"
      class="file-input"
      @change="handleFileSelect"
    >
    <div
      v-if="selectedFile"
      class="file-info"
    >
      <i class="fas fa-file" /> 
      {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, defineExpose } from 'vue';
import FileProcessorService from '@/services/FileProcessorService';

// Props
const props = defineProps({
  modelValue: {
    type: File,
    default: null
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'file-selected']);

// Reactive data
const selectedFile = ref(props.modelValue);
const fileInput = ref(null);

// Methods
const handleFileSelect = (event) => {
  const file = event.target.files[0];
  selectedFile.value = file || null;
  
  emit('update:modelValue', selectedFile.value);
  emit('file-selected', selectedFile.value);
};

const formatFileSize = (bytes) => {
  return FileProcessorService.formatFileSize(bytes);
};

const resetFile = () => {
  selectedFile.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
  emit('update:modelValue', null);
};

// Watch for external changes
import { watch } from 'vue';
watch(() => props.modelValue, (newValue) => {
  if (!newValue) {
    resetFile();
  } else {
    selectedFile.value = newValue;
  }
});

// Expose reset method
defineExpose({
  resetFile
});
</script>

<style scoped lang="scss">
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  color: var(--color-text);
  font-weight: 500;
  margin-bottom: 8px;
  font-size: 1rem;
}

.file-input {
  width: 100%;
  padding: 12px 15px;
  font-size: 1rem;
  border: 2px dashed var(--color-background-mute);
  border-radius: 12px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
}

.file-input:hover {
  border-color: var(--color-info);
  background-color: var(--color-background-mute);
}

.file-input:focus {
  outline: none;
  border-color: var(--color-info);
  border-style: solid;
}

.file-info {
  margin-top: 10px;
  padding: 10px 15px;
  background: rgba(0, 123, 255, 0.1);
  border: 1px solid rgba(0, 123, 255, 0.3);
  border-radius: 8px;
  color: var(--color-info);
  font-size: 0.9rem;
}
</style>
