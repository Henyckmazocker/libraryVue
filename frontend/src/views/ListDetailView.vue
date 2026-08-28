<template>
  <div class="list-detail">
    <div class="list-detail__nav">
      <button
        type="button"
        class="list-detail__back"
        @click="router.push({ name: 'Lists' })"
      >
        <i class="fas fa-arrow-left" />
        <span>Mis listas</span>
      </button>
    </div>

    <div
      v-if="isLoading"
      class="list-detail__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>

    <!-- Un 403 o un 404 no se pintan como una lista vacía: se dicen. -->
    <div
      v-else-if="!current"
      class="list-detail__empty"
      role="alert"
    >
      <i class="pi pi-lock" />
      <p>{{ error || 'No se pudo abrir esta lista' }}</p>
    </div>

    <template v-else>
      <header class="list-detail__header">
        <div class="list-detail__heading">
          <!-- Con `{{ }}`, nunca `v-html`: el nombre lo escribe una persona. -->
          <h1 class="list-detail__title">
            {{ current.name }}
          </h1>
          <p
            v-if="current.description"
            class="list-detail__description"
          >
            {{ current.description }}
          </p>
          <p class="list-detail__meta">
            <span class="list-detail__badge">
              <i :class="VISIBILITY[current.visibility].icon" />
              {{ VISIBILITY[current.visibility].label }}
            </span>
            <span>{{ items.length }} {{ items.length === 1 ? 'ítem' : 'ítems' }}</span>
          </p>
        </div>

        <!-- Renombrar y borrar son del DUEÑO; `can_edit` solo abre el contenido.
             Las dos condiciones vienen resueltas del servidor. -->
        <div
          v-if="isOwner"
          class="list-detail__actions"
        >
          <button
            type="button"
            class="list-detail__action"
            @click="showEdit = true"
          >
            <i class="pi pi-pencil" />
            <span class="u-sr-only">Editar la lista</span>
          </button>
          <button
            type="button"
            class="list-detail__action list-detail__action--danger"
            @click="confirmDelete"
          >
            <i class="pi pi-trash" />
            <span class="u-sr-only">Borrar la lista</span>
          </button>
        </div>
      </header>

      <div
        v-if="items.length === 0"
        class="list-detail__empty"
      >
        <i class="pi pi-inbox" />
        <p>Esta lista todavía está vacía</p>
        <p class="list-detail__empty-hint">
          Añade ítems desde la ficha de un libro, película, juego, álbum o vídeo.
        </p>
      </div>

      <div
        v-else
        class="list-detail__items"
      >
        <ListItemCard
          v-for="item in items"
          :key="item.id"
          :item="item"
          :can-edit="canEdit"
          :busy="removingId === item.id"
          @remove="handleRemove"
        />
      </div>

      <!-- Colaboradores. La sección aparece si hay alguno o si eres el dueño y
           puedes invitar: a quien solo mira no le sirve de nada un hueco vacío. -->
      <section
        v-if="collaborators.length > 0 || isOwner"
        class="list-detail__collaborators"
      >
        <h2 class="list-detail__collaborators-title">
          <i class="pi pi-users" />
          Colaboran
          <button
            v-if="isOwner"
            type="button"
            class="list-detail__invite"
            @click="showInvite = true"
          >
            <i class="pi pi-user-plus" />
            Invitar
          </button>
        </h2>

        <p
          v-if="collaborators.length === 0"
          class="list-detail__collaborators-empty"
        >
          Todavía no colabora nadie.
        </p>

        <ul
          v-else
          class="list-detail__collaborator-list"
        >
          <li
            v-for="person in collaborators"
            :key="person.user_id"
            class="list-detail__collaborator"
          >
            <img
              v-if="person.picture"
              :src="person.picture"
              alt=""
              class="list-detail__collaborator-avatar"
              loading="lazy"
              decoding="async"
            >
            <i
              v-else
              class="pi pi-user"
              aria-hidden="true"
            />
            <span class="list-detail__collaborator-name">{{ person.username }}</span>

            <!-- El dueño saca a cualquiera; un colaborador solo puede salirse
                 él. Las dos son la misma acción, y el backend lo comprueba. -->
            <button
              v-if="isOwner || person.user_id === myUserId"
              type="button"
              class="list-detail__collaborator-remove"
              @click="handleRemoveCollaborator(person)"
            >
              <i class="pi pi-times" />
              <span class="u-sr-only">
                {{ person.user_id === myUserId ? 'Salir de la lista' : `Quitar a ${person.username}` }}
              </span>
            </button>
          </li>
        </ul>
      </section>
    </template>

    <ListFormDialog
      v-if="showEdit && current"
      v-model="showEdit"
      :list="current"
      @submit="handleEdit"
    />

    <!-- Con `v-if` como los demás: usa dos stores en su `setup` y pide los
         amigos al montar. -->
    <InviteCollaboratorDialog
      v-if="showInvite"
      v-model="showInvite"
      :list-id="numericId"
      :collaborators="collaborators"
    />
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useListsStore } from '@/store/lists'
import ListItemCard from '@/components/Lists/ListItemCard.vue'
import ListFormDialog from '@/components/Lists/ListFormDialog.vue'
import InviteCollaboratorDialog from '@/components/Lists/InviteCollaboratorDialog.vue'
import { useAuthStore } from '@/store/auth'
import { VISIBILITY } from '@/components/Lists/visibility'

const props = defineProps({
  // Llega por `props: true` de la ruta, así que es una cadena.
  listId: { type: [String, Number], required: true }
})

const lists = useListsStore()
const { current, currentItems: items, currentCollaborators: collaborators, isLoading, error } = storeToRefs(lists)
const canEdit = computed(() => lists.canEditCurrent)
const isOwner = computed(() => lists.isCurrentOwner)

const router = useRouter()
const notifications = inject('notifications', null)

const showEdit = ref(false)
const showInvite = ref(false)
const myUserId = computed(() => useAuthStore().user?.id ?? null)
const removingId = ref(null)

const numericId = computed(() => Number(props.listId))

onMounted(() => lists.fetchList(numericId.value))
// Navegar de una lista a otra sin desmontar la vista tiene que recargar.
watch(numericId, (id) => lists.fetchList(id))

const handleRemove = async (item) => {
  removingId.value = item.id
  const result = await lists.removeItem(numericId.value, item.id)
  removingId.value = null

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo quitar el ítem')
  }
}

const handleRemoveCollaborator = async (person) => {
  const meVoy = person.user_id === myUserId.value
  const pregunta = meVoy
    ? '¿Salir de esta lista? Dejarás de poder editarla.'
    : `¿Quitar a ${person.username} de esta lista?`

  if (!window.confirm(pregunta)) return

  const result = await lists.removeCollaborator(numericId.value, person.user_id)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo completar la operación')
    return
  }

  // Salirse significa perder el acceso: quedarse en la vista daría un 403 al
  // primer refresco.
  if (meVoy) {
    router.push({ name: 'Lists' })
  }
}

const handleEdit = async (form) => {
  const result = await lists.updateList(numericId.value, form)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo guardar')
    return
  }

  showEdit.value = false
  notifications?.showSuccess?.('Lista actualizada')
}

const confirmDelete = async () => {
  if (!window.confirm(`¿Borrar la lista «${current.value.name}»? Los ítems seguirán en tu biblioteca.`)) {
    return
  }

  const result = await lists.deleteList(numericId.value)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo borrar la lista')
    return
  }

  notifications?.showSuccess?.('Lista borrada')
  router.push({ name: 'Lists' })
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.list-detail {
  max-width: 860px;
  margin: 0 auto;
  padding: spacing(lg);

  &__nav {
    margin-bottom: spacing(md);
  }

  &__back {
    @include button-reset;

    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    color: var(--color-text-secondary);

    &:hover { color: var(--color-text); }
  }

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: spacing(md);
    margin-bottom: spacing(lg);
  }

  &__heading {
    min-width: 0;
  }

  &__title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text);
  }

  &__description {
    margin-top: spacing(2xs);
    color: var(--color-text-secondary);
  }

  &__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: spacing(sm);
    margin-top: spacing(2xs);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
  }

  &__badge {
    display: inline-flex;
    align-items: center;
    gap: spacing(3xs);
  }

  &__actions {
    display: flex;
    flex-shrink: 0;
    gap: spacing(2xs);
  }

  &__action {
    @include button-reset;

    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);

    &:hover { color: var(--color-text); }

    &--danger:hover { color: var(--color-error); }
  }

  &__items {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  &__collaborators {
    margin-top: spacing(xl);
  }

  &__collaborators-title {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    margin-bottom: spacing(md);
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-text);

    i { color: var(--color-primary); }
  }

  &__invite {
    @include button-reset;

    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    margin-left: auto;
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-primary);
    color: var(--color-primary);
    font-size: 0.875rem;
    font-weight: 400;

    &:hover { background: var(--color-background-mute); }
  }

  &__collaborators-empty {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
  }

  &__collaborator-list {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    list-style: none;
    padding: 0;
  }

  &__collaborator {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    padding: spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
    color: var(--color-text-secondary);
  }

  &__collaborator-avatar {
    width: 28px;
    height: 28px;
    border-radius: radius(full);
    object-fit: cover;
  }

  &__collaborator-name {
    flex: 1;
    min-width: 0;
    color: var(--color-text);
  }

  &__collaborator-remove {
    @include button-reset;

    padding: spacing(2xs);
    border-radius: radius(sm);
    color: var(--color-text-secondary);

    &:hover { color: var(--color-error); }
  }

  &__loading,
  &__empty {
    text-align: center;
    padding: spacing(2xl);
    color: var(--color-text-secondary);

    i { font-size: 3rem; display: block; margin-bottom: spacing(md); }
  }

  &__empty-hint {
    font-size: 0.875rem;
  }
}
</style>
