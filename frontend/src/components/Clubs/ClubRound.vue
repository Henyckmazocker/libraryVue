<template>
  <div class="club-round">
    <!-- La fase, y cuánta gente falta. Se dice siempre: una ronda avanza cuando
         han participado TODOS, así que saber quién falta es la información que
         de verdad mueve la pantalla. -->
    <p class="club-round__phase">
      <template v-if="isProposing">
        <i class="pi pi-pencil" />
        Estáis proponiendo. Van <strong>{{ round.proposals.length }}</strong>
        {{ round.proposals.length === 1 ? 'propuesta' : 'propuestas' }}.
      </template>
      <template v-else>
        <i class="pi pi-chart-bar" />
        Estáis votando{{ round.ballot > 1 ? ' el desempate' : '' }}.
        <strong v-if="round.pendingVoters > 0">
          Faltan {{ round.pendingVoters }} por votar.
        </strong>
        <strong v-else>Han votado todos.</strong>
      </template>
    </p>

    <!-- Por qué no puedes proponer, cuando no puedes. Lo manda RESUELTO el
         servidor: la rotación es una regla de dominio y recalcularla aquí para
         decidir si pintar el botón sería una segunda copia. -->
    <p
      v-if="blockedNotice"
      class="club-round__notice"
    >
      <i class="pi pi-info-circle" />
      {{ blockedNotice }}
    </p>

    <p
      v-if="round.proposals.length === 0"
      class="club-round__empty"
    >
      Nadie ha propuesto nada todavía.
    </p>

    <ul
      v-else
      class="club-round__proposals"
    >
      <li
        v-for="proposal in round.proposals"
        :key="proposal.id"
        class="club-round__proposal"
        :class="{ 'club-round__proposal--eliminated': proposal.eliminated }"
      >
        <img
          class="club-round__cover"
          :src="coverFor(proposal)"
          :alt="proposal.entity_title || 'Portada'"
          @error="onCoverError(proposal.id)"
        >

        <div class="club-round__info">
          <span class="club-round__title">
            {{ proposal.entity_title || proposal.entity_id }}
          </span>
          <span class="club-round__by">
            Lo propone {{ nameOf(proposal.user_id) }}
          </span>
          <span
            v-if="proposal.eliminated"
            class="club-round__by"
          >
            Eliminada en el desempate
          </span>
        </div>

        <div class="club-round__tally">
          <!-- Solo el RECUENTO. Quién votó a quién no llega hasta aquí, y no es
               una omisión de la plantilla: el servidor no lo manda. -->
          <span
            v-if="isVoting"
            class="club-round__votes"
          >
            {{ proposal.votes }}
            <span class="u-sr-only">
              {{ proposal.votes === 1 ? 'voto' : 'votos' }}
            </span>
          </span>

          <button
            v-if="isVoting && !proposal.eliminated"
            type="button"
            class="club-round__vote"
            :class="{ 'club-round__vote--mine': round.myVote === proposal.id }"
            :aria-pressed="round.myVote === proposal.id"
            :disabled="isSaving"
            @click="$emit('vote', proposal.id)"
          >
            <i :class="round.myVote === proposal.id ? 'pi pi-check-circle' : 'pi pi-circle'" />
            {{ round.myVote === proposal.id ? 'Tu voto' : 'Votar' }}
          </button>
        </div>
      </li>
    </ul>

    <!-- Proponer se hace desde la ficha del medio, que es por donde ya se metía
         un ítem en un club: no hay un buscador montable dentro de esta pantalla
         —los cinco `*Search.vue` son vistas de RUTA configuradas por
         `GenericSearch`—, y duplicarlo aquí sería otra copia de los cinco. Se
         va a la home, que es el hub de búsqueda; no hay una ruta `Search`. -->
    <RouterLink
      v-if="isProposing && round.canPropose"
      class="club-round__action"
      :to="{ name: 'Home' }"
    >
      <i class="pi pi-search" />
      Buscar algo que proponer
    </RouterLink>

    <!-- Las dos válvulas del dueño, separadas del resto: no son otra acción
         más, son lo que impide que una ronda se muera. Sin cron, si alguien no
         propone o no vota nunca, la fase no avanzaría jamás por sí sola. -->
    <div
      v-if="isOwner"
      class="club-round__valves"
    >
      <p class="club-round__valves-title">
        Si alguien no participa, puedes seguir tú
      </p>

      <button
        v-if="isProposing"
        type="button"
        class="club-round__action club-round__action--valve"
        :disabled="isSaving || round.proposals.length === 0"
        @click="$emit('open-vote')"
      >
        <i class="pi pi-play" />
        Abrir el voto con lo que hay
      </button>

      <button
        v-else
        type="button"
        class="club-round__action club-round__action--valve"
        :disabled="isSaving || castVotes === 0"
        @click="$emit('close-vote')"
      >
        <i class="pi pi-flag" />
        Cerrar la votación
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CoverService from '@/services/CoverService'

const props = defineProps({
  /** El bloque `round` de `get_club`, tal cual llega. */
  round: { type: Object, required: true },
  /** Para poner nombre al autor de cada propuesta. */
  members: { type: Array, default: () => [] },
  isOwner: { type: Boolean, default: false },
  isSaving: { type: Boolean, default: false }
})

defineEmits(['vote', 'open-vote', 'close-vote'])

const isProposing = computed(() => props.round.phase === 'proposing')
const isVoting = computed(() => props.round.phase === 'voting')

const castVotes = computed(
  () => props.round.proposals.reduce((total, p) => total + (p.votes ?? 0), 0)
)

/**
 * El aviso se traduce por CÓDIGO, no por el texto del backend, que va en
 * inglés. `voting` no se avisa: que la ronda esté votando ya lo dice la fase, y
 * repetirlo sería ruido.
 */
const blockedNotice = computed(() => {
  const avisos = {
    rotation: 'Ganaste la ronda anterior, así que esta vez proponen los demás. Puedes votar igual.',
    already_proposed: 'Ya has propuesto: es una por persona y ronda.'
  }

  return avisos[props.round.reasonBlocked] ?? null
})

const nameOf = (userId) => {
  const miembro = props.members.find((m) => m.user_id === userId)

  // `username` es NULLable en `users` y se cae al nombre, que no lo es. Mismo
  // criterio que la lista de miembros de esta misma pantalla.
  return miembro ? (miembro.username || miembro.name) : 'alguien'
}

// ---------------------------------------------------------------------------
// Portadas: son de CATÁLOGO, no de biblioteca
// ---------------------------------------------------------------------------
//
// Una propuesta es un ítem que NADIE tiene guardado todavía, así que su fila de
// `cover_file` es de `scope = 'catalog'`. `catalogCoverUrl` devuelve `null`
// salvo en películas y álbumes —los únicos con resolución desde el mirror—, y
// entonces se cae directo a la URL copiada en la propuesta.

const failed = ref(new Set())

// El reset se ancla al conjunto de PROPUESTAS y no al objeto `round`, que el
// store reescribe entero en cada lectura: anclarlo a `round` anularía un
// fallback recién decidido a los pocos milisegundos.
watch(
  () => props.round.proposals.map((p) => p.id).join(','),
  () => { failed.value = new Set() }
)

const coverFor = (proposal) => {
  if (!failed.value.has(proposal.id)) {
    const local = CoverService.catalogCoverUrl(proposal.entity_type, proposal.entity_id)
    if (local) return local
  }

  return proposal.entity_cover || ''
}

const onCoverError = (proposalId) => {
  const siguiente = new Set(failed.value)
  siguiente.add(proposalId)
  failed.value = siguiente
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.club-round {
  &__phase,
  &__notice,
  &__empty {
    display: flex;
    align-items: flex-start;
    gap: spacing(2xs);
    margin-bottom: spacing(sm);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);

    i { color: var(--color-primary); }
  }

  &__notice {
    padding: spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
  }

  &__empty {
    justify-content: center;
    padding: spacing(lg);
  }

  &__proposals {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    list-style: none;
    padding: 0;
    margin: 0 0 spacing(md);
  }

  &__proposal {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
    border: 1px solid var(--color-border-light);

    &--eliminated { opacity: 0.55; }
  }

  &__cover {
    width: 40px;
    height: 60px;
    object-fit: cover;
    border-radius: radius(sm);
    background: var(--color-background);
    flex-shrink: 0;
  }

  &__info {
    display: flex;
    flex-direction: column;
    gap: spacing(3xs);
    min-width: 0;
    flex: 1;
  }

  &__title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-text);
  }

  &__by {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
  }

  &__tally {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    flex-shrink: 0;
  }

  &__votes {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-text);
  }

  &__vote {
    @include button-reset;

    display: inline-flex;
    align-items: center;
    gap: spacing(3xs);
    padding: spacing(3xs) spacing(2xs);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    font-size: 0.8125rem;

    &:hover { border-color: var(--color-primary); }
    &:disabled { opacity: 0.5; cursor: not-allowed; }

    &--mine {
      border-color: var(--color-primary);
      color: var(--color-primary);
    }
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
    text-decoration: none;

    &:hover { background: var(--color-background); }
    &:disabled { opacity: 0.5; cursor: not-allowed; }

    &--valve {
      border-color: var(--color-border);
      color: var(--color-text-secondary);
    }
  }

  &__valves {
    margin-top: spacing(md);
    padding-top: spacing(sm);
    border-top: 1px solid var(--color-border-light);
  }

  &__valves-title {
    margin-bottom: spacing(2xs);
    font-size: 0.75rem;
    color: var(--color-text-secondary);
  }
}
</style>
