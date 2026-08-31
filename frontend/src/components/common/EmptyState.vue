<template>
  <!-- Sin `role="alert"` a propósito: un estado vacío NO es un error y no debe
       interrumpir a quien navega con lector de pantalla. Buscar algo que no
       existe es una respuesta, no un fallo. -->
  <div
    class="empty-state"
    :class="`empty-state--${tone}`"
  >
    <i
      v-if="icon"
      class="empty-state__icon"
      :class="icon"
      aria-hidden="true"
    />
    <p class="empty-state__title">
      {{ title }}
    </p>
    <p
      v-if="message"
      class="empty-state__message"
    >
      {{ message }}
    </p>
    <!-- Para lo que acompaña al vacío: un botón de «añadir el primero», un
         enlace a otra pantalla. Si no se pasa, no se pinta nada. -->
    <div
      v-if="$slots.default"
      class="empty-state__action"
    >
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: '' },
  title: { type: String, required: true },
  message: { type: String, default: '' },
  // No hay `error`, y es la razón de ser de este componente: mezclarlos es lo
  // que hacía `GenericSearch`, que pintaba «No se encontraron resultados» en
  // rojo de error. El error tiene su propio tratamiento y su propio color.
  tone: {
    type: String,
    default: 'neutral',
    validator: (v) => ['neutral', 'info'].includes(v)
  }
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: spacing(xs);
  padding: spacing(xl) spacing(md);
  border-radius: radius(lg);
  text-align: center;
  color: var(--color-text-muted);

  &--neutral {
    background: var(--color-background-mute);
  }

  // `info` es para el vacío que además informa de algo —«todavía no hay
  // nada, pero esto es lo que pasará»—. Aquí `--color-info` sí está en su
  // sitio: tiñe un aviso, no el fondo de una acción.
  &--info {
    background: var(--color-info-bg);
    color: var(--color-text);
  }
}

.empty-state__icon {
  font-size: var(--font-size-2xl);
  opacity: 0.5;
}

.empty-state__title {
  margin: 0;
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}

.empty-state__message {
  margin: 0;
  font-size: var(--font-size-sm);
  max-width: min(46ch, 100%);
}

.empty-state__action {
  margin-top: spacing(xs);
}
</style>
