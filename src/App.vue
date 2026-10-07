<script setup>
import { useRoute } from 'vue-router'
import Shell from './components/Shell.vue'
import { notices } from './api'

const route = useRoute()
</script>

<template>
  <Shell v-if="route.meta.shell">
    <RouterView />
  </Shell>
  <RouterView v-else />
  <div class="toasts" aria-live="polite">
    <p v-for="item in notices" :key="item.id" :data-tone="item.tone">{{ item.message }}</p>
  </div>
</template>
