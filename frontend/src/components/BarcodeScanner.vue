
<template>
  <StreamBarcodeReader
    @decode="onDecodeInternal"
    @loaded="onLoadedInternal"
    @error="onCameraError"
    class="barcode-reader-small"
  ></StreamBarcodeReader>
</template>

<script setup>

import { StreamBarcodeReader } from "vue-barcode-reader";
import { defineEmits } from 'vue';
import Logger from '@/utils/logger';


const emit = defineEmits(['isbn-scanned', 'scanner-loaded']);


const onDecodeInternal = (text) => {
  if (text) {
    emit('isbn-scanned', text);
  }
};

// Silenciar error de cámara: no mostrar nada, no emitir error
const onCameraError = (err) => {
  // No hacer nada, ni mostrar mensaje
  // Si quieres debug, puedes descomentar:
  Logger.warn('No se detectó cámara, escaneo deshabilitado:', err);
};


const onLoadedInternal = () => {
  Logger.debug("Barcode scanner loaded and ready.");
  emit('scanner-loaded');
};
</script>

<style>
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