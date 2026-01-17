<template>
  <div class="stats-grid">
    <StatCard 
      v-for="(stat, index) in stats"
      :key="index"
      :icon="stat.icon"
      :number="stat.number"
      :label="stat.label"
      :color="stat.color || 'default'"
    />
  </div>
</template>

<script setup>
import StatCard from './shared/StatCard.vue';

// eslint-disable-next-line no-undef
defineProps({
  stats: {
    type: Array,
    required: true,
    validator: (value) => {
      return value.every(stat => 
        stat.icon && 
        (stat.number !== undefined) && 
        stat.label
      );
    }
  }
});
</script>

<style scoped>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
