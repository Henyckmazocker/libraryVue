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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.charts-section {
  margin-bottom: spacing(xl);
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: spacing(lg);

  @include responsive-below(md) {
    grid-template-columns: 1fr;
  }
}
</style>
