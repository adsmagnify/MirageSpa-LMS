<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, canTeach, homePath, isAdmin, logout, session, ui } from '../api'
import Assistant from './Assistant.vue'

const route = useRoute()
const router = useRouter()
const query = ref('')
const notes = ref([])

const items = computed(() => {
  const rows = [
    { to: '/', label: 'Home', icon: 'home' },
    { to: '/courses', label: 'Courses', icon: 'courses' },
    { to: '/groups', label: 'Groups', icon: 'groups' },
  ]
  if (isAdmin()) rows.push({ to: '/users', label: 'Users', icon: 'users' })
  if (canTeach()) {
    rows.push(
      { to: '/schools', label: 'Schools', icon: 'schools' },
      { to: '/library', label: 'Resources', icon: 'resources' },
      { to: '/reports', label: 'Reports', icon: 'reports' },
    )
  }
  if (isAdmin()) rows.push({ to: '/admin', label: 'Admin', icon: 'admin' })
  rows.push({ to: '/help', label: 'Help', icon: 'help' })
  return rows
})

const icons = {
  home: 'M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1V10.5z',
  courses: 'M4 6.5h16M4 12h16M4 17.5h10',
  groups: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm8 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM3 19c0-2.5 2.2-4 5-4s5 1.5 5 4M13 15.2c1.6.3 3.8 1.2 4.5 3.3',
  users: 'M12 12a3.2 3.2 0 1 0 0-6.4A3.2 3.2 0 0 0 12 12zm-6.5 7c.6-2.6 3-4 6.5-4s5.9 1.4 6.5 4',
  schools: 'M4 20V9l8-5 8 5v11M9 20v-6h6v6',
  resources: 'M6 4.5h8l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1zm8 0v4h4',
  reports: 'M5 19V10M12 19V5M19 19v-7',
  admin: 'M12 3.5 5 6.5v5.2c0 4.2 2.9 7.2 7 8.8 4.1-1.6 7-4.6 7-8.8V6.5L12 3.5z',
  help: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-6.2v.2M9.6 9.2a2.4 2.4 0 1 1 3.2 2.3c-.7.3-1.2.9-1.2 1.6',
}

const initials = computed(() =>
  (session.user?.full_name || '?')
    .split(' ')
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase()
)
const unread = computed(() => notes.value.filter((item) => !item.read).length)

async function loadNotes() {
  if (!session.user) return
  try {
    notes.value = await api('/notifications')
  } catch {
    notes.value = []
  }
}

onMounted(loadNotes)

function goSearch() {
  const q = query.value.trim()
  if (q) router.push({ path: '/search', query: { q } })
}

async function openNotes() {
  ui.notices = !ui.notices
  ui.account = false
  if (ui.notices && unread.value) {
    await api('/notifications/read', { method: 'POST' })
    notes.value = notes.value.map((item) => ({ ...item, read: 1 }))
  }
}

function signOut() {
  logout()
  router.push('/login')
}

function active(item, isActive, isExactActive) {
  return item.to === '/' ? isExactActive : isActive
}
</script>

<template>
  <div class="app">
    <aside v-if="!route.meta.lesson" class="side">
      <router-link :to="homePath()" class="logo">
        <span class="logo-mark">M</span>
        <span><strong>MIRAGE</strong><small>SPA ACADEMY</small></span>
      </router-link>
      <nav>
        <router-link v-for="item in items" :key="item.to" v-slot="{ href, navigate, isActive, isExactActive }" :to="item.to" custom>
          <a :href="href" class="item" :class="{ on: active(item, isActive, isExactActive) }" @click="navigate">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path :d="icons[item.icon]" />
            </svg>
            {{ item.label }}
          </a>
        </router-link>
      </nav>
      <div v-if="session.user" class="who">
        <span class="avatar">{{ initials }}</span>
        <span>
          <strong>{{ session.user.full_name }}</strong><br />
          <small style="color: #9aa0a8">{{ session.user.frappe_role }}</small>
        </span>
      </div>
    </aside>
    <div class="workspace">
      <header v-if="!route.meta.lesson" class="topbar">
        <form class="top-search" @submit.prevent="goSearch">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
          <input v-model="query" type="search" placeholder="Search" aria-label="Search" />
        </form>
        <div class="top-tools">
          <button class="icon-btn" type="button" aria-label="Notifications" @click="openNotes">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 9a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9" /><path d="M10 20a2 2 0 0 0 4 0" /></svg>
            <span v-if="unread" class="dot">{{ unread }}</span>
          </button>
          <router-link class="icon-btn" to="/calendar" aria-label="Calendar">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3.5V7M16 3.5V7M4 10h16" /></svg>
          </router-link>
          <router-link v-if="canTeach()" class="icon-btn" to="/trash" aria-label="Trash">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14M9 7V5h6v2M8 7l1 13h6l1-13" /></svg>
          </router-link>
          <button class="who-btn" type="button" @click="ui.account = !ui.account">
            <span>{{ session.user?.full_name }}</span>
            <span class="avatar sm">{{ initials }}</span>
          </button>
          <div v-if="ui.notices" class="pop">
            <p v-if="!notes.length" class="muted">No notifications yet.</p>
            <router-link v-for="item in notes" :key="item.id" :to="item.href || '/'" class="pop-row" @click="ui.notices = false">
              {{ item.body }}
            </router-link>
          </div>
          <div v-if="ui.account" class="pop account">
            <router-link :to="`/user/${session.user?.username}`" @click="ui.account = false">Profile</router-link>
            <button type="button" @click="signOut">Sign out</button>
          </div>
        </div>
      </header>
      <div class="main-stage">
        <slot />
      </div>
    </div>
    <aside v-if="ui.assistant" class="assistant-panel">
      <header class="spread">
        <strong>Student Assistant</strong>
        <button class="btn small ghost" type="button" @click="ui.assistant = false">Close</button>
      </header>
      <Assistant />
    </aside>
  </div>
</template>
