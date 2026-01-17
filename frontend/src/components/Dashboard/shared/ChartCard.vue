<template>
  <div class="chart-card">
    <h3 class="chart-title">
      <i v-if="icon" :class="icon"></i>
      {{ title }}
    </h3>
    <div class="chart-container">
      <component 
        :is="chartComponent" 
        :data="data" 
        :options="options"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Doughnut, Bar, Pie, Line } from 'vue-chartjs';

// eslint-disable-next-line no-undef
const props = defineProps({
  title: {
    type: String,
    required: true
  },
  type: {
    type: String,
    required: true,
    validator: (value) => ['doughnut', 'bar', 'pie', 'line'].includes(value)
  },
  data: {
    type: Object,
    required: true
  },
  options: {
    type: Object,
    default: () => ({})
  },
  icon: {
    type: String,
    default: ''
  }
});

const chartComponent = computed(() => {
  const components = {
    doughnut: Doughnut,
    bar: Bar,
    pie: Pie,
    line: Line
  };
  return components[props.type];
});
</script>

<style scoped>
.chart-card {
  background: var(--color-background-card);
  border-radius: 8px;
  padding: 1.5rem;
  border: 1px solid var(--color-border-light);
  box-shadow: var(--shadow-light);
  transition: var(--transition-fast);
}

.chart-card:hover {
  box-shadow: var(--shadow-medium);
  border-color: var(--color-border-hover);
}

.chart-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text-dark);
  margin: 0 0 1.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.chart-title i {
  color: var(--color-primary);
}

.chart-container {
  position: relative;
  height: 300px;
}

/* Dark mode */
:global(.app-dark) .chart-card {
  background: var(--color-background-card);
  border-color: var(--color-border);
}

:global(.app-dark) .chart-title {
  color: var(--color-text);
}

:global(.app-dark) .chart-title i {
  color: var(--color-secondary);
}
</style>
