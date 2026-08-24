<template>
  <div
    v-if="allowedStatuses && allowedStatuses.length > 0"
    class="status-selector-container"
  >
    <!-- Readonly mode - always show badges -->
    <div
      v-if="readonly"
      class="status-badges"
    >
      <span
        v-if="currentStatuses.length === 0"
        class="no-status-text"
      >
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
      append-to="body"
      @change="onStatusesChange"
    >
      <template #option="slotProps">
        <div
          class="status-option"
          :class="{ 'status-session-trigger': isSessionTrigger(slotProps.option) }"
        >
          <span class="status-label">{{ getStatusLabel(slotProps.option) }}</span>
          <span
            v-if="isSessionTrigger(slotProps.option)" 
            class="session-indicator" 
            :title="getSessionTooltip(slotProps.option)"
          >
            <i
              :class="getSessionIcon(slotProps.option)"
              aria-hidden="true"
            />
            <span class="u-sr-only">{{ getSessionTooltip(slotProps.option) }}</span>
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
      append-to="body"
      @change="onStatusChange"
    />
    
    <!-- Status badges display for non-readonly -->
    <div
      v-else-if="showBadges && currentStatuses.length > 0"
      class="status-badges"
    >
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

<style scoped lang="scss">
.status-selector-container {
  margin: 15px 0;
  overflow: visible;
}

.status-selector-title {
  margin: 0 0 8px 0;
  font-weight: 500;
  color: var(--color-text);
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
  background: var(--color-success-bg);
  color: var(--color-success);
  border: 1px solid var(--color-success);
}

.status-watchlist {
  background: var(--color-info-bg);
  color: var(--color-info);
  border: 1px solid var(--color-info);
}

.status-viewed,
.status-completed {
  background: var(--color-background-mute);
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
}

.status-reading {
  background: var(--color-warning-bg);
  color: var(--color-warning);
  border: 1px solid var(--color-warning);
}

.status-dropped {
  background: var(--color-error-bg);
  color: var(--color-error);
  border: 1px solid var(--color-error);
}

.status-paused {
  background: var(--color-warning-bg);
  color: var(--color-warning);
  border: 1px solid var(--color-warning);
}

.status-watching {
  background: var(--color-card-movie-bg-hover);
  color: var(--color-card-movie-accent);
  border: 1px solid var(--color-card-movie-accent);
}

.status-default {
  background: var(--color-background-mute);
  color: var(--color-text-muted);
  border: 1px solid var(--color-border);
}

/* Readonly styles */
.readonly {
  opacity: 0.8;
  cursor: default;
}

.readonly .status-badge {
  border: 1px solid var(--color-border-light);
  cursor: default;
  background-color: var(--color-background-soft);
}

.readonly .status-badge:hover {
  transform: none;
  box-shadow: none;
}

.no-status-text {
  color: var(--color-text-muted);
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
  color: var(--color-success);
}

.session-indicator .fa-check-circle {
  color: var(--color-info);
}

.session-indicator .fa-pause-circle {
  color: var(--color-warning);
}

.session-indicator .fa-times-circle {
  color: var(--color-error);
}
</style>
