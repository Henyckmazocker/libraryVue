<template>
  <div class="tag-selector">
    <!-- Etiqueta opcional. Sin la prop, el componente no cambia: los sitios que hoy
         lo usan sin título siguen igual. -->
    <span
      v-if="label"
      class="field-label"
    >{{ label }}</span>
    <div class="tag-list">
      <!-- En modo readonly la pastilla no es un control: se queda como <span>.
           El v-if envuelve al v-for porque Vue 3 no admite los dos en el mismo nodo. -->
      <template v-if="readonly">
        <span
          v-for="tag in tags"
          :key="tag.id || tag.name"
          class="tag-pill readonly"
        >
          {{ tag.name }}
        </span>
      </template>
      <template v-else>
        <button
          v-for="tag in tags"
          :key="tag.id || tag.name"
          type="button"
          class="tag-pill"
          :class="{ selected: selectedTags.includes(tag.id || tag.name) }"
          :aria-pressed="selectedTags.includes(tag.id || tag.name)"
          @click="toggleTag(tag)"
        >
          {{ tag.name }}
        </button>
      </template>
    </div>
    <div
      v-if="!readonly"
      class="tag-input-row"
    >
      <input
        v-model="newTag"
        type="text"
        :aria-label="t('tags.addNew')"
        :placeholder="t('tags.addNewPlaceholder')"
        class="tag-input"
        @keyup.enter="addTag"
      >
      <button
        class="btn btn--primary btn--sm add-tag-btn"
        @click="addTag"
      >
        {{ t('common.add') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
  // Etiqueta visible, opcional. Se pinta como la de un campo de formulario para que
  // el control no quede huérfano en un diálogo lleno de campos con título.
  label: {
    type: String,
    default: ''
  },
  tags: {
    type: Array,
    default: () => [] // [{ id, name }]
  },
  modelValue: {
    type: Array,
    default: () => [] // array de ids o nombres seleccionados
  },
  readonly: {
    type: Boolean,
    default: false
  }
});
const emit = defineEmits(['add-tag', 'update:modelValue']);

const selectedTags = ref([...props.modelValue]);
const newTag = ref('');

watch(() => props.modelValue, (val) => {
  selectedTags.value = [...val];
});

function toggleTag(tag) {
  const tagId = tag.id || tag.name;
  if (selectedTags.value.includes(tagId)) {
    selectedTags.value = selectedTags.value.filter(t => t !== tagId);
  } else {
    selectedTags.value.push(tagId);
  }
  // Emitir cambios al padre para v-model
  emit('update:modelValue', [...selectedTags.value]);
}

function addTag() {
  const name = newTag.value.trim();
  if (!name) return;
  // Emitir evento para que el padre lo añada a la lista de tags
  emit('add-tag', name);
  newTag.value = '';
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.tag-selector {
  display: flex;
  flex-direction: column;
  gap: spacing(xs);
}
.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: spacing(xs);
}
.tag-pill {
  @include button-reset;
  background: var(--color-border-light);
  color: var(--color-text-secondary);
  border-radius: radius(lg);
  padding: spacing(2xs) spacing(sm);
  font-size: var(--font-size-base);
  cursor: pointer;
  transition: background 0.2s;
  user-select: none;
}
// Toggle, no acción: `<button aria-pressed>` que marca selección. Por eso NO usa
// la escala `.btn` y por eso su fondo sigue siendo `--color-info` — aquí el color
// de estado está en su sitio, porque lo que comunica ES un estado.
.tag-pill.selected {
  background: var(--color-info);
  color: var(--color-on-status);
}
.tag-pill.readonly {
  cursor: default;
  opacity: 0.7;
  background: var(--color-border-light);
  color: var(--color-text-muted);
}
.tag-input-row {
  display: flex;
  gap: spacing(xs);
}
.tag-input {
  flex: 1;
  padding: spacing(2xs) spacing(xs);
  border-radius: radius(md);
  border: 1px solid var(--color-border);
  font-size: var(--font-size-base);
}

.field-label {
  display: block;
  margin-bottom: spacing(2xs);
  font-weight: var(--font-weight-medium);
  font-size: var(--font-size-sm);
  color: var(--color-text);
}
</style>
