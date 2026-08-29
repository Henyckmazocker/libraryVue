<template>
  <div class="club-detail">
    <RouterLink
      class="club-detail__back"
      :to="{ name: 'Clubs' }"
    >
      <i class="pi pi-arrow-left" />
      Volver a mis clubs
    </RouterLink>

    <div
      v-if="isLoading"
      class="club-detail__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>

    <p
      v-else-if="error"
      class="club-detail__error"
      role="alert"
    >
      {{ error }}
    </p>

    <template v-else-if="current">
      <header class="club-detail__header">
        <h1 class="club-detail__title">
          {{ current.name }}
        </h1>
        <p
          v-if="current.description"
          class="club-detail__description"
        >
          {{ current.description }}
        </p>
      </header>

      <!-- El ítem activo. La sección entera desaparece cuando hay ronda: ítem y
           ronda son estados EXCLUYENTES, y dejar el título con nada debajo es
           lo que se ve en la pantalla de un club que está eligiendo. -->
      <section
        v-if="currentPick || !currentRound"
        class="club-detail__section"
      >
        <h2 class="club-detail__section-title">
          Lo que estamos viendo
        </h2>

        <div
          v-if="currentPick"
          class="club-detail__pick"
        >
          <img
            class="club-detail__pick-cover"
            :src="coverUrl"
            :alt="currentPick.entity_title || 'Portada'"
            @error="onCoverError"
          >
          <div class="club-detail__pick-info">
            <RouterLink
              v-if="pickRoute"
              class="club-detail__pick-title"
              :to="pickRoute"
            >
              {{ currentPick.entity_title || currentPick.entity_id }}
            </RouterLink>
            <span
              v-else
              class="club-detail__pick-title"
            >{{ currentPick.entity_title || currentPick.entity_id }}</span>

            <span class="club-detail__pick-meta">
              {{ finishedCount }} de {{ currentMembers.length }} lo han terminado
            </span>

            <!-- Cerrar es del dueño, y NO es la excepción: el cierre automático
                 exige que TODOS lo hayan completado, y basta un miembro que no
                 lo tenga en su biblioteca para que no llegue nunca. -->
            <button
              v-if="isCurrentOwner"
              type="button"
              class="club-detail__action"
              :disabled="isSaving"
              @click="handleFinish"
            >
              <i class="pi pi-check" />
              Darlo por terminado
            </button>
          </div>
        </div>

        <p
          v-else-if="!currentRound"
          class="club-detail__empty"
        >
          Ahora mismo el club no tiene nada activo.
          <span v-if="isCurrentOwner">Elige el siguiente desde la ficha de cualquier medio.</span>
        </p>
      </section>

      <!-- La ronda: solo hay una cuando NO hay ítem activo. Son estados
           excluyentes y el servidor manda uno u otro, nunca los dos. -->
      <section
        v-if="currentRound"
        class="club-detail__section"
      >
        <h2 class="club-detail__section-title">
          Qué leemos ahora
        </h2>

        <ClubRound
          :round="currentRound"
          :members="currentMembers"
          :is-owner="isCurrentOwner"
          :is-saving="isSaving"
          @vote="handleVote"
          @open-vote="handleOpenVote"
          @close-vote="handleCloseVote"
        />
      </section>

      <!-- El progreso de cada uno -->
      <section
        v-if="currentPick"
        class="club-detail__section"
      >
        <h2 class="club-detail__section-title">
          Por dónde va cada uno
        </h2>

        <div
          v-if="isLoadingProgress"
          class="club-detail__loading"
        >
          <i class="pi pi-spin pi-spinner" />
        </div>

        <ClubMemberProgress
          v-else
          :axis="progressAxis"
          :members="progressMembers"
        />
      </section>

      <!-- Las notas, ya marcadas por el servidor -->
      <section
        v-if="currentPick"
        class="club-detail__section"
      >
        <h2 class="club-detail__section-title">
          Lo que ha escrito la gente
        </h2>

        <!-- Se avisa de la consecuencia de reutilizar las notas públicas, y se
             avisa AQUÍ y no en un aparte: publicar una nota para el club la
             publica también en el feed de todos tus amigos. -->
        <p class="club-detail__notice">
          <i class="pi pi-info-circle" />
          Aquí salen las notas <strong>públicas</strong> de los miembros. Publicar
          una nota para el club la publica también en el feed de tus amigos.
        </p>

        <div
          v-if="isLoadingNotes"
          class="club-detail__loading"
        >
          <i class="pi pi-spin pi-spinner" />
        </div>

        <ClubNotes
          v-else
          :axis="notesAxis"
          :notes="notes"
        />
      </section>

      <!-- Los miembros -->
      <section class="club-detail__section">
        <h2 class="club-detail__section-title">
          Miembros ({{ currentMembers.length }})
        </h2>

        <ul class="club-detail__members">
          <li
            v-for="member in currentMembers"
            :key="member.user_id"
            class="club-detail__member"
          >
            <!-- `username` es NULLable en `users` y se cae al nombre, que no lo
                 es. `findByClub` devuelve los dos por esto mismo, y es el criterio
                 que ya usan `MySqlClubProgressRepository::hidratar` y el diálogo
                 de invitar: sin el fallback, un usuario sin username sale como
                 una fila en blanco. -->
            <span>{{ member.username || member.name }}</span>
            <span
              v-if="member.user_id === current.owner_id"
              class="club-detail__owner-badge"
            >Organiza</span>
          </li>
        </ul>

        <!-- Invitar es del DUEÑO y no de cualquier miembro: decide ante quién
             se expone el progreso de todos los demás. -->
        <button
          v-if="isCurrentOwner"
          type="button"
          class="club-detail__action"
          @click="showInvite = true"
        >
          <i class="pi pi-user-plus" />
          Invitar a un amigo
        </button>

        <!-- Salir es el ÚNICO control de privacidad del club: entrar es
             consentir que los miembros vean tu progreso. El dueño no puede
             salir; borra el club, que es otra acción. -->
        <button
          v-if="!isCurrentOwner"
          type="button"
          class="club-detail__action club-detail__action--danger"
          :disabled="isSaving"
          @click="handleLeave"
        >
          <i class="pi pi-sign-out" />
          Salir del club
        </button>
      </section>

      <!-- El historial -->
      <section
        v-if="currentHistory.length > 0"
        class="club-detail__section"
      >
        <h2 class="club-detail__section-title">
          Ya terminados ({{ currentHistory.length }})
        </h2>

        <ul class="club-detail__history">
          <li
            v-for="pick in currentHistory"
            :key="pick.id"
            class="club-detail__history-item"
          >
            <span>{{ pick.entity_title || pick.entity_id }}</span>
            <span class="club-detail__history-date">{{ formatDate(pick.finished_at) }}</span>
          </li>
        </ul>
      </section>

      <!-- Con `v-if` y no solo con `v-model`: usa dos stores de Pinia en su
           `setup`, así que instanciarlo siempre los levantaría sin abrirlo. -->
      <InviteToClubDialog
        v-if="showInvite"
        v-model="showInvite"
        :club-id="numericId"
        :members="currentMembers"
        @invited="load"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useClubsStore } from '@/store/clubs'
import ClubMemberProgress from '@/components/Clubs/ClubMemberProgress.vue'
import ClubRound from '@/components/Clubs/ClubRound.vue'
import InviteToClubDialog from '@/components/Clubs/InviteToClubDialog.vue'
import ClubNotes from '@/components/Clubs/ClubNotes.vue'
import { detailRouteFor } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'

const props = defineProps({
  clubId: { type: [String, Number], required: true }
})

const clubsStore = useClubsStore()
const {
  current, currentMembers, currentPick, currentRound, currentHistory,
  progressAxis, progressMembers,
  notes, notesAxis,
  isLoading, isLoadingProgress, isLoadingNotes, isSaving, error
} = storeToRefs(clubsStore)

const isCurrentOwner = computed(() => clubsStore.isCurrentOwner)
const finishedCount = computed(() => clubsStore.finishedCount)

const router = useRouter()
const notifications = inject('notifications', null)

const numericId = computed(() => Number(props.clubId))

const showInvite = ref(false)

const load = async () => {
  const result = await clubsStore.fetchClub(numericId.value)
  // El progreso solo se pide si el club se pudo abrir: con un 403 sería una
  // segunda llamada condenada al mismo 403.
  if (!result.success) return

  // Las dos en paralelo: son independientes y las dos son de refresco.
  await Promise.all([
    clubsStore.fetchProgress(numericId.value),
    clubsStore.fetchNotes(numericId.value)
  ])
}

onMounted(load)
watch(numericId, load)

// ---------------------------------------------------------------------------
// La portada, con el escalón doble local → remota → placeholder
// ---------------------------------------------------------------------------

const localFailed = ref(false)

// El reset se ancla a la PORTADA y no al pick entero: anclarlo al objeto
// anularía un fallback recién decidido en cuanto el store reescriba el pick.
watch(() => currentPick.value?.entity_id, () => { localFailed.value = false })

const coverUrl = computed(() => {
  const pick = currentPick.value
  if (!pick) return ''

  // La clave de medio es `entity_type`, NO el medio del registry: una serie se
  // guarda con AddMovieUseCase y su fila lleva `media_type = 'movie'`.
  if (!localFailed.value) return CoverService.localCoverUrl(pick.entity_type, pick.entity_id)

  return pick.entity_cover || ''
})

const onCoverError = () => { localFailed.value = true }

// `detailRouteFor` devuelve `null` con un medio desconocido o un id vacío, en
// vez de lanzar como `getMediaConfig`: por eso se puede llamar sin guardas.
const pickRoute = computed(
  () => currentPick.value ? detailRouteFor(currentPick.value.entity_type, currentPick.value.entity_id) : null
)

const formatDate = (value) => {
  if (!value) return ''

  return new Date(value.replace(' ', 'T')).toLocaleDateString('es-ES', {
    day: 'numeric', month: 'short', year: 'numeric'
  })
}

// ---------------------------------------------------------------------------
// Acciones
// ---------------------------------------------------------------------------

const handleFinish = async () => {
  const result = await clubsStore.finishPick(numericId.value)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo cerrar el ítem')
    return
  }

  notifications?.showSuccess?.('Ítem terminado')
}

/**
 * Votar, y las dos válvulas del dueño. Las tres releen el club desde el store,
 * porque **el estado siguiente lo decide el servidor al leer**: votar el último
 * que faltaba cierra la ronda y crea el ítem, y proponer el último abre el
 * voto. Aquí no se adivina nada.
 */
const handleVote = async (proposalId) => {
  const result = await clubsStore.voteProposal(numericId.value, proposalId)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo votar')
    return
  }

  notifications?.showSuccess?.('Voto registrado')
}

const handleOpenVote = async () => {
  const result = await clubsStore.openVote(numericId.value)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo abrir el voto')
    return
  }

  notifications?.showSuccess?.('Voto abierto')
}

const handleCloseVote = async () => {
  const result = await clubsStore.closeVote(numericId.value)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo cerrar la votación')
    return
  }

  // Cerrar NO garantiza ítem: si los votos empataban en el primer recuento, la
  // ronda pasa al desempate y sigue votándose. La válvula destraba la espera,
  // no la regla.
  notifications?.showSuccess?.(
    result.pickId ? 'Ya tenéis siguiente ítem' : 'Empate: toca desempatar'
  )
}

const handleLeave = async () => {
  const result = await clubsStore.leaveClub(numericId.value)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo salir del club')
    return
  }

  notifications?.showSuccess?.('Has salido del club')
  router.push({ name: 'Clubs' })
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.club-detail {
  max-width: 860px;
  margin: 0 auto;
  padding: spacing(lg);

  &__back {
    display: inline-flex;
    align-items: center;
    gap: spacing(3xs);
    margin-bottom: spacing(md);
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    text-decoration: none;

    &:hover { color: var(--color-primary); }
  }

  &__header {
    margin-bottom: spacing(lg);
  }

  &__title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text);
  }

  &__description {
    margin-top: spacing(3xs);
    font-size: 0.875rem;
    color: var(--color-text-secondary);
  }

  &__section {
    margin-bottom: spacing(xl);
  }

  &__section-title {
    margin-bottom: spacing(sm);
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-text);
  }

  &__pick {
    display: flex;
    gap: spacing(md);
    padding: spacing(md);
    border-radius: radius(md);
    background: var(--color-background-mute);
    border: 1px solid var(--color-border-light);
  }

  &__pick-cover {
    width: 80px;
    height: 120px;
    object-fit: cover;
    border-radius: radius(sm);
    background: var(--color-background);
  }

  &__pick-info {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: spacing(2xs);
  }

  &__pick-title {
    font-weight: 600;
    color: var(--color-text);
    text-decoration: none;

    &:hover { color: var(--color-primary); }
  }

  &__pick-meta {
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
  }

  &__action {
    @include button-reset;

    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-primary);
    color: var(--color-primary);
    font-size: 0.875rem;

    &:hover { background: var(--color-background); }
    &:disabled { opacity: 0.5; cursor: not-allowed; }

    &--danger {
      margin-top: spacing(md);
      border-color: var(--color-error);
      color: var(--color-error);
    }
  }

  &__members,
  &__history {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    list-style: none;
    padding: 0;
    margin: 0;
  }

  &__member,
  &__history-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: spacing(sm);
    font-size: 0.875rem;
    color: var(--color-text);
  }

  &__owner-badge,
  &__history-date {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
  }

  &__notice {
    display: flex;
    align-items: flex-start;
    gap: spacing(2xs);
    margin-bottom: spacing(sm);
    padding: spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);

    i { color: var(--color-primary); }
  }

  &__loading,
  &__empty {
    padding: spacing(lg);
    text-align: center;
    color: var(--color-text-secondary);
    font-size: 0.875rem;
  }

  &__error {
    padding: spacing(lg);
    text-align: center;
    color: var(--color-error);
  }
}
</style>
