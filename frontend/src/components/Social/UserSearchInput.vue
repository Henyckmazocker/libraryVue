<template>
  <div class="user-search-input">
    <IconField>
      <InputIcon class="pi pi-search" />
      <InputText
        v-model="query"
        :placeholder="t('social.searchUsersPlaceholder')"
        class="user-search-input__field"
        :loading="isSearching"
        @keydown.esc="clear"
      />
      <InputIcon
        v-if="query"
        class="pi pi-times user-search-input__clear"
        @click="clear"
      />
    </IconField>
  </div>
</template>

<script setup>
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { useUserSearch } from '@/composables/useUserSearch'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const { query, isSearching, clear } = useUserSearch()
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.user-search-input {
  position: relative;

  &__field {
    width: 100%;
  }

  &__clear {
    cursor: pointer;
    &:hover { color: var(--color-text); }
  }
}
</style>
