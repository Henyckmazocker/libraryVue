<template>
  <p
    v-if="stale"
    class="stale-notice"
    role="status"
  >
    <i
      class="fas fa-cloud-arrow-down"
      aria-hidden="true"
    />
    <span>{{ message }}</span>
  </p>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/**
 * La franja que dice que lo que estás viendo sale de una caché caducada.
 *
 * Es una franja y no un toast a propósito: un toast se va, y esto describe lo
 * que estás viendo mientras lo estés viendo. No reserva hueco cuando no hay
 * nada que avisar — el `v-if` deja el DOM exactamente como estaba.
 */
// eslint-disable-next-line no-undef
const props = defineProps({
  /** `true` solo cuando el backend ha servido una copia caducada. */
  stale: {
    type: Boolean,
    default: false
  },
  /** ISO 8601 de cuándo se guardó esa copia, o `null` si no se sabe. */
  cachedAt: {
    type: String,
    default: null
  },
  /** Nombre del proveedor caído, tal y como lo lee un humano. */
  provider: {
    type: String,
    default: ''
  }
});

/**
 * «hace 3 d» en el mismo registro que usa la tarjeta del feed.
 *
 * Devuelve `null` en vez de una cadena rota cuando no hay fecha o no se puede
 * parsear: `cached_at` llega `null` si la copia se guardó sin marca de tiempo,
 * y pintar `Invalid Date` sería peor que no decir nada.
 */
const relativeTime = computed(() => {
  if (!props.cachedAt) return null;

  const cuando = new Date(props.cachedAt).getTime();
  if (Number.isNaN(cuando)) return null;

  const mins = Math.floor((Date.now() - cuando) / 60000);
  if (mins < 1) return t('feed.justNow');
  if (mins < 60) return t('feed.minutesAgo', { n: mins });

  const horas = Math.floor(mins / 60);
  if (horas < 24) return t('feed.hoursAgo', { n: horas });

  const dias = Math.floor(horas / 24);
  return t('feed.daysAgo', { n: dias });
});

const message = computed(() => {
  const cabecera = props.provider
    ? t('stale.offlineNamed', { proveedor: props.provider })
    : t('stale.offline');

  return relativeTime.value
    ? t('stale.from', { cabecera, cuando: relativeTime.value })
    : t('stale.maybeOld', { cabecera });
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.stale-notice {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: spacing(xs);
  width: 100%;
  box-sizing: border-box;
  margin-bottom: spacing(lg);
  padding: spacing(xs) spacing(md);
  border-radius: radius(md);
  font-size: var(--font-size-sm);
  text-align: center;

  // El mismo par que ya usa `.error-message` de GenericSearch: tinta semántica
  // sobre su propio tinte, y las dos conmutan con el tema.
  color: var(--color-warning);
  background-color: var(--color-warning-bg);
}

@include responsive-below(md) {
  .stale-notice {
    font-size: var(--font-size-xs);
    text-align: left;
  }
}
</style>
