<template>
  <div class="edit-popup" @click="onBackgroundClick">
    <div class="popup-content">
      <button class="close-btn" @click="$emit('close')" aria-label="Cerrar">&times;</button>
      <h2>{{ item?.title || 'Sin título' }}</h2>
      <div class="edit-fields">
        <RatingComponent
          v-model:rating="localRating"
          :editable="true"
        />
        <StatusSelector
          v-model="localStatuses"
          :allowed-statuses="allowedStatuses"
          :multiple="true"
          label="Estado"
          subtitle="(selecciona uno o más)"
        />
      </div>
      <div class="save-btn-container">
        <button class="save-btn" @click="handleSave">Guardar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue';
import { useBooks } from '@/composables/useBooks';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  }
});
const emit = defineEmits(['close', 'save']);

const localRating = ref(props.item?.user_rating ?? null);
const localStatuses = ref(props.item?.userStatuses ? [...props.item.userStatuses] : []);

const { editUserBook } = useBooks();

const handleSave = async () => {
  // Enviar los estados seleccionados en el array 'statuses'
  const result = await editUserBook(
    props.item.isbn,
    props.item.userId || props.item.user_id,
    {
      personalRating: localRating.value,
      statuses: [...localStatuses.value]
    },
    [], // tags
    []  // notes
  );
  if (result.success) {
    // Emitir el objeto actualizado para que la librería lo reciba y actualice el estado local
    emit('close', {
      ...props.item,
      user_rating: localRating.value,
      userStatuses: [...localStatuses.value]
    });
  } else {
    // Mostrar feedback de error
    alert(result.message || 'Error al guardar los cambios');
  }
};

function onBackgroundClick(e) {
  if (e.target.classList.contains('edit-popup')) {
    emit('close');
  }
}
</script>

<style scoped>
.edit-popup {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}
.popup-content {
  background: var(--background, #23272f);
  color: var(--text, #f5f5f5);
  padding: 2rem 2.5rem 2rem 2rem;
  border-radius: 16px;
  min-width: 320px;
  min-height: 180px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.35);
  position: relative;
  font-family: inherit;
}
.close-btn {
  position: absolute;
  top: 18px;
  right: 18px;
  background: transparent;
  border: none;
  font-size: 2rem;
  color: #f5f5f5;
  cursor: pointer;
  transition: color 0.2s;
  z-index: 10;
}
.close-btn:hover {
  color: #ff5252;
}
/* Centrado y estilo consistente para el botón de guardar */
.save-btn-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 2rem;
}
.save-btn {
  padding: 0.5rem 2rem;
  background: #1976d2;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1.1rem;
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.08);
  transition: background 0.2s;
}
.save-btn:hover {
  background: #1565c0;
}
.edit-fields {
  margin-top: 1rem;
  margin-bottom: 1rem;
}
</style>
