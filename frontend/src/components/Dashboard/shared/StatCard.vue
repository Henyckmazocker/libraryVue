<template>
  <div
    class="stat-card"
    :class="colorClass"
  >
    <div class="stat-icon">
      <i :class="icon" />
    </div>
    <div class="stat-content">
      <h3 class="stat-number">
        {{ displayNumber }}
      </h3>
      <p class="stat-label">
        {{ label }}
      </p>
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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/dashboard' as *;

.stat-card {
  @include dashboard-card;
  display: flex;
  align-items: center;
  gap: spacing(md);
  padding: spacing(lg);
  border: 1px solid var(--color-border-light);

  &:hover {
    transform: translateY(-2px);
    border-color: var(--color-border-hover);
  }
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: radius(lg);
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-primary);
  color: var(--color-text-light);
  font-size: 1.5rem;
}

// Variantes funcionales (no por entidad — son indicadores semánticos)
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
  margin: 0 0 spacing(3xs) 0;
  color: var(--color-text);
}

.stat-label {
  font-size: 0.875rem;
  color: var(--color-text-secondary);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
</style>
