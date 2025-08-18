<template>
  <div class="form-group">
    <label for="service-select">Selecciona el servicio:</label>
    <select 
      id="service-select" 
      v-model="selectedService" 
      @change="handleServiceChange"
      class="service-dropdown"
    >
      <option value="">-- Selecciona un servicio --</option>
      <option 
        v-for="service in services" 
        :key="service.value" 
        :value="service.value"
      >
        {{ service.label }}
      </option>
    </select>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue';

// Props
const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'service-changed']);

// Reactive data
const selectedService = ref(props.modelValue);

// Servicios disponibles
const services = [
  { value: 'palomitacas', label: 'Palomitacas' },
  { value: 'letterboxd', label: 'Letterboxd' },
  { value: 'goodreads', label: 'Goodreads' },
  { value: 'serialized', label: 'Serialized' }
];

// Methods
const handleServiceChange = () => {
  emit('update:modelValue', selectedService.value);
  emit('service-changed', selectedService.value);
};

// Watch for external changes
import { watch } from 'vue';
watch(() => props.modelValue, (newValue) => {
  selectedService.value = newValue;
});
</script>

<style scoped>
.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  color: #e0e0e0;
  font-weight: 500;
  margin-bottom: 8px;
  font-size: 1rem;
}

.service-dropdown {
  width: 100%;
  padding: 12px 15px;
  font-size: 1rem;
  border: 1px solid #555;
  border-radius: 12px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  cursor: pointer;
  transition: border-color 0.2s ease;
}

.service-dropdown:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}
</style>
