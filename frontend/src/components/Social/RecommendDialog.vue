<template>
  <!-- El `<Dialog>` de PrimeVue trae su propio atrapador de foco: no se envuelve
       en `useFocusTrap`, que es para los cuatro modales propios del proyecto. -->
  <Dialog
    v-model:visible="visible"
    header="Recomendar a un amigo"
    :modal="true"
    :dismissable-mask="true"
    class="recommend-dialog"
    :pt="{ mask: { style: 'z-index: 2500' } }"
  >
    <div class="recommend-dialog__body">
      <p class="recommend-dialog__item">
        Vas a recomendar <strong>{{ entityTitle }}</strong>
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
        Aún no tienes amigos a quien recomendar.
      </p>

      <template v-else>
        <fieldset class="recommend-dialog__friends">
          <legend class="recommend-dialog__legend">
            Elige a quién
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
          <label for="recommend-comment">Comentario (opcional)</label>
          <Textarea
            id="recommend-comment"
            v-model="comment"
            rows="3"
            maxlength="500"
            placeholder="¿Por qué se lo recomiendas?"
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
        class="recommend-dialog__action"
        :disabled="isSending"
        @click="visible = false"
      >
        Cancelar
      </button>
      <button
        type="button"
        class="recommend-dialog__action recommend-dialog__action--primary"
        :disabled="!recipientId || isSending"
        @click="send"
      >
        <i
          v-if="isSending"
          class="pi pi-spin pi-spinner"
        />
        Enviar
      </button>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'
import { useInboxStore } from '@/store/inbox'

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
    error.value = 'Todavía no se puede recomendar este ítem'
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
    notifications?.showSuccess?.('Recomendación enviada')
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
  const porCodigo = {
    400: 'Solo puedes recomendar ítems a tus amigos.',
    403: 'Esta recomendación no es tuya.',
    409: 'Ya le recomendaste esto a esta persona.'
  }

  return porCodigo[result.code] ?? result.message
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

  &__action {
    @include button-reset;

    padding: spacing(2xs) spacing(md);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);

    &:disabled {
      opacity: 0.6;
    }

    &--primary {
      color: var(--color-on-status);
      background: var(--color-primary);
      border-color: var(--color-primary);
    }
  }
}
</style>
