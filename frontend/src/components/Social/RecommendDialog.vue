<template>
  <!-- El chasis lo pone `BaseModal`: overlay, trampa de foco, Escape, cabecera
       y pie ordenado, igual que en los cuatro modales propios. -->
  <BaseModal
    v-model="visible"
    :title="t('recommend.title')"
    class="recommend-dialog"
  >
    <div class="recommend-dialog__body">
      <p class="recommend-dialog__item">
        {{ t('recommend.aboutToBefore') }}<strong>{{ entityTitle }}</strong>
      </p>

      <div
        v-if="isLoadingFriends"
        class="recommend-dialog__state"
      >
        <i class="pi pi-spin pi-spinner" />
      </div>

      <p
        v-else-if="friends.length === 0"
        class="recommend-dialog__state"
      >
        {{ t('recommend.noFriends') }}
      </p>

      <template v-else>
        <fieldset class="recommend-dialog__friends">
          <legend class="recommend-dialog__legend">
            {{ t('recommend.chooseWho') }}
          </legend>
          <!-- Botones con `aria-pressed`, no `<div @click>`: las 20 reglas de
               accesibilidad están en `error` y esto es un selector de verdad. -->
          <button
            v-for="friend in friends"
            :key="friend.id"
            type="button"
            class="recommend-dialog__friend"
            :class="{ 'recommend-dialog__friend--selected': recipientId === friend.id }"
            :aria-pressed="recipientId === friend.id"
            @click="recipientId = friend.id"
          >
            <img
              v-if="friend.picture"
              :src="friend.picture"
              alt=""
              class="recommend-dialog__avatar"
              loading="lazy"
              decoding="async"
            >
            <i
              v-else
              class="pi pi-user"
              aria-hidden="true"
            />
            <span>{{ friend.username || friend.name }}</span>
          </button>
        </fieldset>

        <div class="recommend-dialog__field">
          <label for="recommend-comment">{{ t('recommend.comment') }}</label>
          <Textarea
            id="recommend-comment"
            v-model="comment"
            rows="3"
            maxlength="500"
            :placeholder="t('recommend.commentPlaceholder')"
          />
        </div>
      </template>

      <p
        v-if="error"
        class="recommend-dialog__error"
        role="alert"
      >
        {{ error }}
      </p>
    </div>

    <template #footer>
      <button
        type="button"
        class="btn btn--ghost"
        :disabled="isSending"
        @click="visible = false"
      >
        {{ t('common.cancel') }}
      </button>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="!recipientId || isSending"
        @click="send"
      >
        <i
          v-if="isSending"
          class="pi pi-spin pi-spinner"
        />
        {{ t('recommend.send') }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import Textarea from 'primevue/textarea'
import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'
import { useInboxStore } from '@/store/inbox'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /**
   * El medio con el que el BACKEND guarda el ítem, no el del registry: una serie
   * viaja como `movie`. Quien llama lo resuelve con `coverMedia`.
   */
  entityType: { type: String, required: true },
  entityId: { type: [String, Number], default: null },
  entityTitle: { type: String, default: '' },
  entityCover: { type: String, default: null }
})

const emit = defineEmits(['update:modelValue'])

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const socialStore = useSocialStore()
const { friends } = storeToRefs(socialStore)
const inboxStore = useInboxStore()
const notifications = inject('notifications', null)

const recipientId = ref(null)
const comment = ref('')
const isSending = ref(false)
const isLoadingFriends = ref(false)
const error = ref(null)

/**
 * Los amigos se piden al montar, y montar equivale a abrir: la ficha pinta este
 * componente con `v-if`, no solo con `v-model`. Si se instanciara siempre, cada
 * ficha visitada pediría la lista de amigos para un diálogo que casi nunca se
 * abre — y arrastraría sus dos stores a componentes que no los necesitan.
 */
onMounted(async () => {
  if (friends.value.length > 0) return

  isLoadingFriends.value = true
  try {
    await socialStore.fetchFriends()
  } finally {
    isLoadingFriends.value = false
  }
})

const send = async () => {
  // `entityId` ya no es `required` —la ficha puede montar esto antes de tener la
  // ruta resuelta—, así que se comprueba aquí en vez de mandar la cadena 'null'.
  if (!props.entityId) {
    error.value = t('recommendDialog.notYet')
    return
  }

  isSending.value = true
  error.value = null

  const result = await inboxStore.sendRecommendation({
    recipientId: recipientId.value,
    entityType: props.entityType,
    entityId: String(props.entityId),
    entityTitle: props.entityTitle,
    entityCover: props.entityCover,
    comment: comment.value
  })

  isSending.value = false

  if (result.success) {
    notifications?.showSuccess?.(t('recommendDialog.sent'))
    visible.value = false
    return
  }

  // El mensaje se pinta DENTRO del diálogo además de en el toast: el 409 de «ya
  // se la mandaste» necesita quedarse a la vista mientras se elige otro amigo.
  error.value = mensajeDeError(result)
}

/**
 * El backend responde en inglés, como todo el repo, y esto lo lee una persona en
 * una interfaz en español.
 *
 * Se traduce por **código HTTP y no por texto**: el código es parte del contrato
 * —400 «no sois amigos», 409 «ya se la mandaste», fijados por los tests de
 * integración— mientras que la cadena puede reescribirse cualquier día sin que
 * nadie lo note aquí. Lo que no se reconoce se enseña tal cual: peor que un
 * mensaje en inglés es uno inventado que no describe lo que pasó.
 */
function mensajeDeError (result) {
  // Claves del catálogo, como los mapas de `store/lists.js`: el idioma se
  // resuelve al pintar y no al declarar.
  const porCodigo = {
    400: 'recommendDialog.onlyFriends',
    403: 'recommendDialog.notYours',
    409: 'recommendDialog.already'
  }

  return porCodigo[result.code] ? t(porCodigo[result.code]) : result.message
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.recommend-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
    min-width: min(80vw, 22rem);
  }

  &__item {
    color: var(--color-text-secondary);
  }

  &__state {
    text-align: center;
    padding: spacing(lg);
    color: var(--color-text-secondary);
  }

  &__friends {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    border: 0;
    padding: 0;
    margin: 0;
    max-height: 15rem;
    overflow-y: auto;
  }

  &__legend {
    padding: 0;
    margin-bottom: spacing(xs);
    font-weight: 600;
    color: var(--color-text);
  }

  &__friend {
    @include button-reset;

    display: flex;
    align-items: center;
    gap: spacing(sm);
    width: 100%;
    padding: spacing(xs) spacing(sm);
    border: 1px solid var(--color-border-light);
    border-radius: radius(sm);
    color: var(--color-text);
    text-align: left;

    &:hover {
      border-color: var(--color-border-hover);
    }

    &--selected {
      border-color: var(--color-primary);
      background: var(--color-background-mute);
    }
  }

  &__avatar {
    width: 28px;
    height: 28px;
    border-radius: radius(full);
    object-fit: cover;
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);

    label {
      font-weight: 600;
      color: var(--color-text);
    }
  }

  &__error {
    color: var(--color-error);
  }

}
</style>
