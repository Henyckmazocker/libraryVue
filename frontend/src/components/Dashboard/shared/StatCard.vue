<template>
  <div class="stat-card" :class="colorClass">
    <div class="stat-icon">
      <i :class="icon"></i>
    </div>
    <div class="stat-content">
      <h3 class="stat-number">{{ displayNumber }}</h3>
      <p class="stat-label">{{ label }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

// eslint-disable-next-line no-undef
const props = defineProps({
  icon: {
    type: String,
    required: true
  },
  number: {
    type: [Number, String],
    required: true
  },
  label: {
    type: String,
    required: true
  },
  color: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'primary', 'success', 'warning', 'info'].includes(value)
  }
});

const colorClass = computed(() => {
  return props.color !== 'default' ? `stat-card--${props.color}` : '';
});

const displayNumber = computed(() => {
  return typeof props.number === 'number' ? props.number.toLocaleString() : props.number;
});
</script>

<style scoped>
.stat-card {
  background: var(--color-background-card);
  border-radius: 8px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid var(--color-border-light);
  box-shadow: var(--shadow-light);
  transition: var(--transition-fast);
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
  border-color: var(--color-border-hover);
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary);
  color: var(--color-text-light);
  font-size: 1.5rem;
}

.stat-card--primary .stat-icon {
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
}

.stat-card--success .stat-icon {
  background: linear-gradient(135deg, var(--color-success) 0%, var(--color-secondary-dark) 100%);
}

.stat-card--warning .stat-icon {
  background: linear-gradient(135deg, var(--color-warning) 0%, var(--color-highlight) 100%);
}

.stat-card--info .stat-icon {
  background: linear-gradient(135deg, var(--color-secondary) 0%, var(--color-secondary-light) 100%);
}

.stat-content {
  flex: 1;
}

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  margin: 0 0 0.25rem 0;
  color: var(--color-text-dark);
}

.stat-label {
  font-size: 0.875rem;
  color: var(--color-text-muted);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Dark mode */
:global(.app-dark) .stat-card {
  background: var(--color-background-card);
  border-color: var(--color-border);
}

:global(.app-dark) .stat-number {
  color: var(--color-text);
}

:global(.app-dark) .stat-label {
  color: var(--color-text-secondary);
}
</style>
