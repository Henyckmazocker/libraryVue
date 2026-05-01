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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: spacing(lg);
  margin-bottom: spacing(xl);

  @include responsive-below(md) {
    grid-template-columns: 1fr;
  }
}
</style>
