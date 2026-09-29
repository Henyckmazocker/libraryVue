<template>
  <article class="privacy-view">
    <!-- Qué mide la analítica de uso y qué no (Plan «Consentimiento de Analítica», M3). Ruta
         pública: se tiene que poder leer ANTES de decidir, con o sin sesión.
         🔴 Este comentario va DENTRO del <article>: en la raíz del <template> la vista sería un
         Fragment y el <Transition mode="out-in"> de App.vue se quedaría colgado al salir de aquí
         (centro en blanco para siempre, sin error). Lo vigila tests/unit/viewSingleRoot.spec.js. -->
    <h1 class="privacy-view__title">
      {{ t('privacy.page.title') }}
    </h1>
    <p class="privacy-view__intro">
      {{ t('privacy.page.intro') }}
    </p>

    <section
      class="privacy-view__section"
      aria-labelledby="privacy-sent"
    >
      <h2
        id="privacy-sent"
        class="privacy-view__heading"
      >
        {{ t('privacy.page.sentTitle') }}
      </h2>
      <ul class="privacy-view__list">
        <li>{{ t('privacy.page.sentPages') }}</li>
        <li>{{ t('privacy.page.sentActions') }}</li>
        <li>{{ t('privacy.page.sentErrors') }}</li>
        <li>{{ t('privacy.page.sentSession') }}</li>
      </ul>
    </section>

    <section
      class="privacy-view__section"
      aria-labelledby="privacy-not-sent"
    >
      <h2
        id="privacy-not-sent"
        class="privacy-view__heading"
      >
        {{ t('privacy.page.notSentTitle') }}
      </h2>
      <ul class="privacy-view__list">
        <li>{{ t('privacy.page.notSentContent') }}</li>
        <li>{{ t('privacy.page.notSentNotes') }}</li>
        <li>{{ t('privacy.page.notSentIdentity') }}</li>
        <li>{{ t('privacy.page.notSentIp') }}</li>
      </ul>
    </section>

    <section
      class="privacy-view__section"
      aria-labelledby="privacy-where"
    >
      <h2
        id="privacy-where"
        class="privacy-view__heading"
      >
        {{ t('privacy.page.whereTitle') }}
      </h2>
      <p class="privacy-view__text">
        {{ t('privacy.page.whereBody') }}
      </p>
    </section>

    <section
      class="privacy-view__section"
      aria-labelledby="privacy-control"
    >
      <h2
        id="privacy-control"
        class="privacy-view__heading"
      >
        {{ t('privacy.page.controlTitle') }}
      </h2>
      <p class="privacy-view__text">
        {{ t('privacy.page.controlBody') }}
        <router-link
          to="/profile"
          class="privacy-view__link"
        >
          {{ t('privacy.page.controlLink') }}
        </router-link>.
      </p>
    </section>

    <router-link
      to="/"
      class="privacy-view__back"
    >
      {{ t('privacy.page.back') }}
    </router-link>
  </article>
</template>

<script setup>
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.privacy-view {
  max-width: 720px;
  margin: 0 auto;
  padding: spacing(md) 0;
  color: var(--color-text);
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.privacy-view__title {
  margin: 0 0 spacing(sm);
  font-size: var(--font-size-xl);
  font-weight: var(--font-weight-semibold);
}

.privacy-view__intro {
  margin: 0 0 spacing(lg);
  color: var(--color-text-secondary);
}

.privacy-view__section {
  margin-bottom: spacing(lg);
}

.privacy-view__heading {
  margin: 0 0 spacing(xs);
  font-size: var(--font-size-md);
  font-weight: var(--font-weight-semibold);
}

.privacy-view__list {
  margin: 0;
  padding-left: spacing(lg);

  li + li {
    margin-top: spacing(2xs);
  }
}

.privacy-view__text {
  margin: 0;
}

.privacy-view__link,
.privacy-view__back {
  color: var(--color-link);
}
</style>
