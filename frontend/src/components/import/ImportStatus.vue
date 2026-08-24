<template>
  <div 
    v-if="status.message" 
    :class="['import-status', status.type]"
  >
    <i 
      v-if="status.loading" 
      class="fas fa-spinner fa-spin status-icon"
    />
    <i 
      v-else-if="status.type === 'success'" 
      class="fas fa-check-circle status-icon"
    />
    <i 
      v-else-if="status.type === 'error'" 
      class="fas fa-exclamation-circle status-icon"
    />
    <i 
      v-else-if="status.type === 'info'" 
      class="fas fa-info-circle status-icon"
    />
    
    <span class="status-text">{{ status.message }}</span>
    
    <!-- Progress bar for loading states -->
    <div
      v-if="status.loading && progress > 0"
      class="progress-bar"
    >
      <div 
        class="progress-fill" 
        :style="{ width: `${progress}%` }"
      />
    </div>
  </div>
</template>

<script setup>
import { defineProps } from 'vue';

// Props
defineProps({
  status: {
    type: Object,
    default: () => ({
      message: '',
      type: '', // 'success', 'error', 'info'
      loading: false
    })
  },
  progress: {
    type: Number,
    default: 0,
    validator: (value) => value >= 0 && value <= 100
  }
});
</script>

<style scoped lang="scss">
.import-status {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 15px;
  border-radius: 8px;
  font-size: 0.9rem;
  margin-top: 15px;
  position: relative;
}

.status-icon {
  flex-shrink: 0;
  font-size: 1rem;
}

.status-text {
  flex: 1;
}

.import-status.success {
  background: rgba(40, 167, 69, 0.15);
  color: var(--color-success);
  border: 1px solid rgba(40, 167, 69, 0.3);
}

.import-status.error {
  background: rgba(220, 53, 69, 0.15);
  color: var(--color-error);
  border: 1px solid rgba(220, 53, 69, 0.3);
}

.import-status.info {
  background: rgba(0, 123, 255, 0.15);
  color: var(--color-info);
  border: 1px solid rgba(0, 123, 255, 0.3);
}

.progress-bar {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0 0 8px 8px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: currentColor;
  transition: width 0.3s ease;
  opacity: 0.6;
}

.fa-spin {
  animation: fa-spin 1s infinite linear;
}

@keyframes fa-spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
