<template>
  <div class="status-selector-container" v-if="allowedStatuses && allowedStatuses.length > 0">
    
    <!-- Multi-select mode -->
    <MultiSelect
      v-if="multiple"
      v-model="selectedStatuses"
      :options="allowedStatuses"
      :filter="true"
      :display="'chip'"
      :placeholder="placeholder"
      :style="containerStyle"
      appendTo="body"
      @change="onStatusesChange"
    />
    
    <!-- Single-select mode -->
    <Dropdown
      v-else
      v-model="selectedStatus"
      :options="allowedStatuses"
      :placeholder="placeholder"
      :style="containerStyle"
      appendTo="body"
      @change="onStatusChange"
    />
    
    <!-- Status badges display -->
    <div v-if="showBadges && currentStatuses.length > 0" class="status-badges">
      <span 
        v-for="status in currentStatuses" 
        :key="status" 
        class="status-badge"
        :class="getStatusClass(status)"
      >
        {{ getStatusLabel(status) }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, defineProps, defineEmits, watch } from 'vue';
import MultiSelect from 'primevue/multiselect';
import Dropdown from 'primevue/dropdown';

// Props
const props = defineProps({
  modelValue: {
    type: [Array, String],
    default: () => []
  },
  allowedStatuses: {
    type: Array,
    required: true,
    default: () => []
  },
  multiple: {
    type: Boolean,
    default: true
  },
  label: {
    type: String,
    default: 'Status'
  },
  subtitle: {
    type: String,
    default: '(selecciona uno o más)'
  },
  placeholder: {
    type: String,
    default: 'Selecciona estados'
  },
  showBadges: {
    type: Boolean,
    default: false
  },
  width: {
    type: String,
    default: '100%'
  },
  maxWidth: {
    type: String,
    default: '20rem'
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'change', 'status-changed']);

// Reactive data
const selectedStatuses = ref(Array.isArray(props.modelValue) ? [...props.modelValue] : []);
const selectedStatus = ref(typeof props.modelValue === 'string' ? props.modelValue : '');

// Computed
const containerStyle = computed(() => ({
  width: props.width,
  maxWidth: props.maxWidth
}));

const currentStatuses = computed(() => {
  return props.multiple ? selectedStatuses.value : (selectedStatus.value ? [selectedStatus.value] : []);
});

// Methods
const onStatusesChange = () => {
  const value = props.multiple ? selectedStatuses.value : selectedStatus.value;
  emit('update:modelValue', value);
  emit('change', value);
  emit('status-changed', value);
};

const onStatusChange = () => {
  onStatusesChange();
};

const getStatusLabel = (status) => {
  // Mapeo de estados a etiquetas legibles
  const statusLabels = {
    'owned': 'En biblioteca',
    'in watchlist': 'En lista de deseos',
    'viewed': 'Visto',
    'watched': 'Visto',
    'reading': 'Leyendo',
    'read': 'Leído',
    'to-read': 'Por leer',
    'currently-reading': 'Leyendo actualmente',
    'want-to-read': 'Quiero leer',
    'dropped': 'Abandonado',
    'completed': 'Completado',
    'on-hold': 'En pausa'
  };
  
  return statusLabels[status] || status;
};

const getStatusClass = (status) => {
  // Clases CSS según el tipo de estado
  const statusClasses = {
    'owned': 'status-owned',
    'in watchlist': 'status-watchlist',
    'viewed': 'status-viewed',
    'watched': 'status-viewed',
    'reading': 'status-reading',
    'read': 'status-completed',
    'to-read': 'status-watchlist',
    'currently-reading': 'status-reading',
    'want-to-read': 'status-watchlist',
    'dropped': 'status-dropped',
    'completed': 'status-completed',
    'on-hold': 'status-paused'
  };
  
  return statusClasses[status] || 'status-default';
};

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (props.multiple) {
    selectedStatuses.value = Array.isArray(newValue) ? [...newValue] : [];
  } else {
    selectedStatus.value = typeof newValue === 'string' ? newValue : '';
  }
});
</script>

<style scoped>
.status-selector-container {
  margin: 15px 0;
  overflow: visible;
}

.status-selector-title {
  margin: 0 0 8px 0;
  font-weight: 500;
  color: #e0e0e0;
}

.status-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

.status-badge {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Status badge colors */
.status-owned {
  background: rgba(40, 167, 69, 0.2);
  color: #28a745;
  border: 1px solid rgba(40, 167, 69, 0.3);
}

.status-watchlist {
  background: rgba(0, 123, 255, 0.2);
  color: #007bff;
  border: 1px solid rgba(0, 123, 255, 0.3);
}

.status-viewed,
.status-completed {
  background: rgba(108, 117, 125, 0.2);
  color: #6c757d;
  border: 1px solid rgba(108, 117, 125, 0.3);
}

.status-reading {
  background: rgba(255, 193, 7, 0.2);
  color: #ffc107;
  border: 1px solid rgba(255, 193, 7, 0.3);
}

.status-dropped {
  background: rgba(220, 53, 69, 0.2);
  color: #dc3545;
  border: 1px solid rgba(220, 53, 69, 0.3);
}

.status-paused {
  background: rgba(255, 133, 27, 0.2);
  color: #fd7e14;
  border: 1px solid rgba(255, 133, 27, 0.3);
}

.status-default {
  background: rgba(108, 117, 125, 0.2);
  color: #6c757d;
  border: 1px solid rgba(108, 117, 125, 0.3);
}
</style>
