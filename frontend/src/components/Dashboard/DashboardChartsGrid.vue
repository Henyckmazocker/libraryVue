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
  // `min(400px, 100%)` en vez de `400px` a secas: una pista fija desborda en cuanto
  // el contenedor mide menos que ella, que es lo que pasaba a 360px.
  grid-template-columns: repeat(auto-fit, minmax(min(400px, 100%), 1fr));
  gap: spacing(lg);

  @include responsive-below(md) {
    // `minmax(0, 1fr)` y no `1fr`: `1fr` es `minmax(auto, 1fr)`, y ese `auto` respeta
    // el min-content de la tarjeta —350px por el canvas de la gráfica—, así que la
    // pista se estiraba a 350px dentro de un contenedor de 218px.
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
