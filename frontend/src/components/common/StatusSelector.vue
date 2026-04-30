<template>
  <div class="status-selector-container" v-if="allowedStatuses && allowedStatuses.length > 0">
    
    <!-- Readonly mode - always show badges -->
    <div v-if="readonly" class="status-badges">
      <span v-if="currentStatuses.length === 0" class="no-status-text">
        Sin estados asignados
      </span>
      <span 
        v-for="status in currentStatuses" 
        :key="status" 
        class="status-badge"
        :class="[getStatusClass(status), { 'readonly': readonly }]"
      >
        {{ getStatusLabel(status) }}
      </span>
    </div>
    
    <!-- Multi-select mode -->
    <MultiSelect
      v-else-if="multiple && !readonly"
      v-model="selectedStatuses"
      :options="allowedStatuses"
      :filter="true"
      :display="'chip'"
      :placeholder="placeholder"
      :style="containerStyle"
      appendTo="body"
      @change="onStatusesChange"
    >
      <template #option="slotProps">
        <div class="status-option" :class="{ 'status-session-trigger': isSessionTrigger(slotProps.option) }">
          <span class="status-label">{{ getStatusLabel(slotProps.option) }}</span>
          <span v-if="isSessionTrigger(slotProps.option)" 
                class="session-indicator" 
                :title="getSessionTooltip(slotProps.option)">
            <i :class="getSessionIcon(slotProps.option)"></i>
          </span>
        </div>
      </template>
    </MultiSelect>
    
    <!-- Single-select mode -->
    <Dropdown
      v-else-if="!multiple && !readonly"
      v-model="selectedStatus"
      :options="allowedStatuses"
      :placeholder="placeholder"
      :style="containerStyle"
      appendTo="body"
      @change="onStatusChange"
    />
    
    <!-- Status badges display for non-readonly -->
    <div v-else-if="showBadges && currentStatuses.length > 0" class="status-badges">
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
import { ref, computed, defineProps, defineEmits, watch, onMounted } from 'vue';
import MultiSelect from 'primevue/multiselect';
import Dropdown from 'primevue/dropdown';
import Logger from '@/utils/logger';

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
  },
  readonly: {
    type: Boolean,
    default: false
  }
});

// Emits
const emit = defineEmits(['update:modelValue', 'change', 'status-changed']);

// Reactive data
const selectedStatuses = ref(Array.isArray(props.modelValue) ? [...props.modelValue] : []);
const selectedStatus = ref(typeof props.modelValue === 'string' ? props.modelValue : '');

// Log on mount
onMounted(() => {
  Logger.debug('[StatusSelector] Component mounted with:', {
    modelValue: props.modelValue,
    allowedStatuses: props.allowedStatuses,
    multiple: props.multiple,
    readonly: props.readonly,
    selectedStatuses: selectedStatuses.value,
    selectedStatus: selectedStatus.value
  });
});

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
    'in-watchlist': 'En lista de deseos',
    'viewed': 'Visto',
    'watched': 'Visto',
    'want-to-buy': 'Quiero comprarlo',
    'abandoned': 'Abandonado',
    'reading': 'Leyendo',
    'read': 'Leído',
    'to-read': 'Por leer',
    'currently-reading': 'Leyendo actualmente',
    'want-to-read': 'Quiero leer',
    'dropped': 'Abandonada',
    'completed': 'Completado',
    'on-hold': 'En pausa',
    'watching': 'Viendo ahora'
  };
  
  return statusLabels[status] || status;
};

const getStatusClass = (status) => {
  // Clases CSS según el tipo de estado
  const statusClasses = {
    'owned': 'status-owned',
    'in watchlist': 'status-watchlist',
    'in-watchlist': 'status-watchlist',
    'viewed': 'status-viewed',
    'watched': 'status-viewed',
    'want-to-buy': 'status-watchlist',
    'abandoned': 'status-dropped',
    'reading': 'status-reading',
    'read': 'status-completed',
    'to-read': 'status-watchlist',
    'currently-reading': 'status-reading',
    'want-to-read': 'status-watchlist',
    'dropped': 'status-dropped',
    'completed': 'status-completed',
    'on-hold': 'status-paused',
    'paused': 'status-paused',
    'watching': 'status-watching'
  };
  
  return statusClasses[status] || 'status-default';
};

const isSessionTrigger = (status) => {
  return ['reading', 'read', 'paused', 'abandoned'].includes(status);
};

const getSessionIcon = (status) => {
  const icons = {
    'reading': 'fas fa-play-circle',
    'read': 'fas fa-check-circle',
    'paused': 'fas fa-pause-circle',
    'abandoned': 'fas fa-times-circle'
  };
  return icons[status] || '';
};

const getSessionTooltip = (status) => {
  const tooltips = {
    'reading': 'Iniciará una sesión de lectura automáticamente',
    'read': 'Completará la sesión activa automáticamente',
    'paused': 'Pausará la sesión actual automáticamente',
    'abandoned': 'Cerrará la sesión activa automáticamente'
  };
  return tooltips[status] || '';
};

// Watch for external changes
watch(() => props.modelValue, (newValue, oldValue) => {
  Logger.debug('[StatusSelector] modelValue changed:', { 
    old: oldValue, 
    new: newValue,
    isArray: Array.isArray(newValue)
  });
  
  if (props.multiple) {
    selectedStatuses.value = Array.isArray(newValue) ? [...newValue] : [];
  } else {
    selectedStatus.value = typeof newValue === 'string' ? newValue : '';
  }
});

watch(() => props.allowedStatuses, (newValue) => {
  Logger.debug('[StatusSelector] allowedStatuses changed:', newValue);
}, { deep: true });
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

.status-watching {
  background: rgba(139, 92, 246, 0.2);
  color: #a78bfa;
  border: 1px solid rgba(139, 92, 246, 0.3);
}

.status-default {
  background: rgba(108, 117, 125, 0.2);
  color: #6c757d;
  border: 1px solid rgba(108, 117, 125, 0.3);
}

/* Readonly styles */
.readonly {
  opacity: 0.8;
  cursor: default;
}

.readonly .status-badge {
  border: 1px solid #e0e0e0;
  cursor: default;
  background-color: #f8f9fa;
}

.readonly .status-badge:hover {
  transform: none;
  box-shadow: none;
}

.no-status-text {
  color: #999;
  font-style: italic;
  font-size: 0.875rem;
}

/* Status option with session indicator */
.status-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 4px 0;
  width: 100%;
}

.status-option.status-session-trigger {
  font-weight: 500;
}

.status-label {
  flex: 1;
}

.session-indicator {
  margin-left: 8px;
  font-size: 0.9rem;
  opacity: 0.7;
}

.session-indicator i {
  transition: opacity 0.2s;
}

.status-option:hover .session-indicator i {
  opacity: 1;
}

.session-indicator .fa-play-circle {
  color: #28a745;
}

.session-indicator .fa-check-circle {
  color: #007bff;
}

.session-indicator .fa-pause-circle {
  color: #ffc107;
}

.session-indicator .fa-times-circle {
  color: #dc3545;
}
</style>
