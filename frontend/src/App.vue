<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from './components/layout/AppHeader.vue'
import AppFooter from './components/layout/AppFooter.vue'
import AdminSidebar from './components/layout/AdminSidebar.vue'
import ToastContainer from './components/base/ToastContainer.vue'

const route = useRoute()
const isAdminArea = computed(() => Boolean(route.meta.requiresAdmin))
</script>

<template>
  <div class="app-shell">
    <AppHeader />
    <main class="app-shell__content" :class="{ 'app-shell__content--admin': isAdminArea }">
      <AdminSidebar v-if="isAdminArea" />
      <div class="app-shell__main">
        <RouterView />
      </div>
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

.app-shell__content--admin {
  flex-direction: row;
}

.app-shell__main {
  flex: 1;
  min-width: 0;
}

@media (max-width: 768px) {
  .app-shell__content--admin {
    flex-direction: column;
  }
}
</style>
