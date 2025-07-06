<template>
  <StreamBarcodeReader
    @decode="onDecodeInternal"
    @loaded="onLoadedInternal"
    class="barcode-reader-small"
  ></StreamBarcodeReader>
</template>

<script setup>
import { StreamBarcodeReader } from "vue-barcode-reader";
import { defineEmits } from 'vue';

const emit = defineEmits(['isbn-scanned', 'scanner-loaded']);

const onDecodeInternal = (text) => {
  if (text) {
    emit('isbn-scanned', text);
  }
};

const onLoadedInternal = () => {
  console.log("Barcode scanner loaded and ready.");
  emit('scanner-loaded');
};
</script>

<style scoped>
.barcode-reader-small {
  width: 1px;
  height: 1px;
  opacity: 0;
  overflow: hidden;
  position: absolute;
  z-index: -1;
}

.barcode-reader-small video {
  width: 100%;
  height: auto;
}
</style> 