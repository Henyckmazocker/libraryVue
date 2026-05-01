<template>
  <div class="horizontal-carousel">
    <button 
      v-if="showNavigation && canScrollLeft" 
      @click="scrollLeft" 
      class="carousel-nav carousel-nav-left"
      aria-label="Scroll left"
    >
      <i class="fas fa-chevron-left"></i>
    </button>
    
    <div 
      ref="carouselContainer" 
      class="carousel-container"
      @scroll="updateScrollButtons"
    >
      <div class="carousel-track">
        <slot></slot>
      </div>
    </div>
    
    <button 
      v-if="showNavigation && canScrollRight" 
      @click="scrollRight" 
      class="carousel-nav carousel-nav-right"
      aria-label="Scroll right"
    >
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

// eslint-disable-next-line no-undef
const props = defineProps({
  showNavigation: {
    type: Boolean,
    default: true
  },
  scrollAmount: {
    type: Number,
    default: 300
  }
});

const carouselContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const updateScrollButtons = () => {
  if (!carouselContainer.value) return;
  
  const container = carouselContainer.value;
  canScrollLeft.value = container.scrollLeft > 0;
  canScrollRight.value = container.scrollLeft < (container.scrollWidth - container.clientWidth - 1);
};

const scrollLeft = () => {
  if (!carouselContainer.value) return;
  carouselContainer.value.scrollBy({
    left: -props.scrollAmount,
    behavior: 'smooth'
  });
};

const scrollRight = () => {
  if (!carouselContainer.value) return;
  carouselContainer.value.scrollBy({
    left: props.scrollAmount,
    behavior: 'smooth'
  });
};

onMounted(() => {
  updateScrollButtons();
  if (carouselContainer.value) {
    carouselContainer.value.addEventListener('scroll', updateScrollButtons);
    window.addEventListener('resize', updateScrollButtons);
  }
});

onUnmounted(() => {
  if (carouselContainer.value) {
    carouselContainer.value.removeEventListener('scroll', updateScrollButtons);
  }
  window.removeEventListener('resize', updateScrollButtons);
});
</script>

<style scoped lang="scss">
.horizontal-carousel {
  position: relative;
  width: 100%;
  padding: 0 20px;
}

.carousel-container {
  overflow-x: auto;
  overflow-y: hidden;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
  scrollbar-color: var(--color-border) transparent;
}

.carousel-container::-webkit-scrollbar {
  height: 8px;
}

.carousel-container::-webkit-scrollbar-track {
  background: transparent;
}

.carousel-container::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
  border-radius: 4px;
}

.carousel-container::-webkit-scrollbar-thumb:hover {
  background-color: var(--color-border-hover);
}

.carousel-track {
  display: flex;
  gap: 15px;
  padding: 20px 5px;
  min-width: min-content;
}

.carousel-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  color: var(--color-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.carousel-nav:hover {
  background: var(--color-primary);
  color: var(--color-text-light);
  border-color: var(--color-primary);
  transform: translateY(-50%) scale(1.1);
}

.carousel-nav:active {
  transform: translateY(-50%) scale(0.95);
}

.carousel-nav-left {
  left: -5px;
}

.carousel-nav-right {
  right: -5px;
}

/* Responsive */
@media (max-width: 768px) {
  .horizontal-carousel {
    padding: 0 10px;
  }
  
  .carousel-nav {
    width: 35px;
    height: 35px;
    font-size: 0.9rem;
  }
  
  .carousel-track {
    gap: 10px;
    padding: 15px 5px;
  }
}

@media (max-width: 480px) {
  .carousel-nav {
    display: none;
  }
  
  .horizontal-carousel {
    padding: 0 5px;
  }
}
</style>
