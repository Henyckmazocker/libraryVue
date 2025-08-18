<template>
  <AppLayout>
    <router-view />
  </AppLayout>
</template>

<script>
import AppLayout from '@/components/common/Layout.vue';

export default {
  name: 'App',
  components: {
    AppLayout
  }
}
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: #d7dadc;
  background: #030303;
  min-height: 100vh;
}

/* Reset de estilos base */
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 0;
  background: #030303;
  color: #d7dadc;
  font-family: Avenir, Helvetica, Arial, sans-serif;
}

/* Estilos globales para enlaces */
a {
  color: #0079d3;
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
}

/* Estilos para botones */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  gap: 8px;
}

.btn--primary {
  background: #0079d3;
  color: white;
}

.btn--primary:hover {
  background: #0060a8;
}

.btn--secondary {
  background: #272729;
  color: #d7dadc;
  border: 1px solid #343536;
}

.btn--secondary:hover {
  background: #343536;
}

/* Utilidades */
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-right { text-align: right; }

.mb-1 { margin-bottom: 0.5rem; }
.mb-2 { margin-bottom: 1rem; }
.mb-3 { margin-bottom: 1.5rem; }
.mb-4 { margin-bottom: 2rem; }

.mt-1 { margin-top: 0.5rem; }
.mt-2 { margin-top: 1rem; }
.mt-3 { margin-top: 1.5rem; }
.mt-4 { margin-top: 2rem; }
</style>
