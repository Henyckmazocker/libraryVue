<template>
  <div class="charts-section">
    <div class="charts-grid">
      <ChartCard 
        v-for="(chart, index) in charts"
        :key="index"
        :title="chart.title"
        :type="chart.type"
        :data="chart.data"
        :options="chart.options"
        :icon="chart.icon"
      />
    </div>
  </div>
</template>

<script setup>
import ChartCard from './shared/ChartCard.vue';

// eslint-disable-next-line no-undef
defineProps({
  charts: {
    type: Array,
    required: true,
    validator: (value) => {
      return value.every(chart => 
        chart.title && 
        chart.type && 
        chart.data
      );
    }
  }
});
</script>

<style scoped>
.charts-section {
  margin-bottom: 2rem;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
