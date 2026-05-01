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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/dashboard' as *;

.chart-card {
  @include dashboard-card;
  border: 1px solid var(--color-border-light);
  padding: spacing(lg);

  &:hover {
    border-color: var(--color-border-hover);
  }
}

.chart-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 spacing(lg) 0;
  display: flex;
  align-items: center;
  gap: spacing(xs);

  i { color: var(--color-primary); }
}

.chart-container {
  position: relative;
  height: 300px;
}
</style>
