<script setup lang="ts">
import AppHeader from './components/layout/AppHeader.vue'
import AppFooter from './components/layout/AppFooter.vue'
import ToastContainer from './components/base/ToastContainer.vue'
</script>

<template>
  <div class="app-shell">
    <AppHeader />
    <main class="app-shell__content">
      <RouterView />
    </main>
    <AppFooter />
    <ToastContainer />
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-shell__content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
</style>
