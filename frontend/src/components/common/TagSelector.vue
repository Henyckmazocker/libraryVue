<template>
  <div class="tag-selector">
    <div class="tag-list">
      <span
        v-for="tag in tags"
        :key="tag.id || tag.name"
        :class="['tag-pill', { selected: selectedTags.includes(tag.id || tag.name), readonly } ]"
        @click="!readonly && toggleTag(tag)"
      >
        {{ tag.name }}
      </span>
    </div>
    <div v-if="!readonly" class="tag-input-row">
      <input
        v-model="newTag"
        @keyup.enter="addTag"
        type="text"
        placeholder="Añadir nuevo tag..."
        class="tag-input"
      />
      <button @click="addTag" class="add-tag-btn">Añadir</button>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue';

const props = defineProps({
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
.tag-selector {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.tag-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.tag-pill {
  background: #e0e0e0;
  color: #333;
  border-radius: 12px;
  padding: 4px 12px;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background 0.2s;
  user-select: none;
}
.tag-pill.selected {
  background: #1976d2;
  color: #fff;
}
.tag-pill.readonly {
  cursor: default;
  opacity: 0.7;
  background: #e0e0e0;
  color: #888;
}
.tag-input-row {
  display: flex;
  gap: 0.5rem;
}
.tag-input {
  flex: 1;
  padding: 4px 8px;
  border-radius: 8px;
  border: 1px solid #ccc;
  font-size: 0.95rem;
}
.add-tag-btn {
  background: #1976d2;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 4px 12px;
  cursor: pointer;
  font-size: 0.95rem;
  transition: background 0.2s;
}
.add-tag-btn:hover {
  background: #1565c0;
}
</style>
