<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, canTeach, toast, ui } from '../api'
import CourseTable from '../components/CourseTable.vue'

const data = ref(null)
const tab = ref('teaching')
const message = ref('')
const month = new Date()

const rows = computed(() => {
  if (!data.value) return []
  return tab.value === 'enrolled' ? data.value.enrolled : data.value.teaching
})

const days = computed(() => {
  const year = month.getFullYear()
  const index = month.getMonth()
  const first = new Date(year, index, 1).getDay()
  const count = new Date(year, index + 1, 0).getDate()
  const cells = Array.from({ length: first }, () => null)
  for (let day = 1; day <= count; day += 1) cells.push(day)
  return cells
})

async function load() {
  data.value = await api('/desk')
  if (!canTeach()) tab.value = 'enrolled'
}

onMounted(load)

async function post() {
  try {
    await api('/feed', { method: 'POST', body: { body: message.value } })
    message.value = ''
    toast('Posted to news.')
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div v-if="data" class="desk">
    <div class="desk-main">
      <h1>{{ data.school?.name || 'School' }}</h1>
      <section class="card">
        <header class="spread">
          <h2>Courses</h2>
        </header>
        <div class="segs">
          <button type="button" :class="{ on: tab === 'teaching' }" @click="tab = 'teaching'">
            Teaching <span>{{ data.teaching.length }}</span>
          </button>
          <button type="button" :class="{ on: tab === 'enrolled' }" @click="tab = 'enrolled'">
            Enrolled <span>{{ data.enrolled.length }}</span>
          </button>
        </div>
        <CourseTable :rows="rows" @changed="load" />
      </section>
      <div class="widgets">
        <section class="card">
          <header class="spread"><h2>Groups</h2><router-link to="/groups">Open</router-link></header>
          <p v-if="!data.groups.length" class="muted">You are not a member of any group.</p>
          <router-link v-for="group in data.groups" :key="group.id" class="widget-row" :to="`/groups/${group.id}`">
            <strong>{{ group.name }}</strong>
            <span class="muted">{{ group.members }} people</span>
          </router-link>
          <router-link class="plus" to="/groups">+</router-link>
        </section>
        <section class="card">
          <header class="spread"><h2>News</h2></header>
          <p v-if="!data.news.length" class="muted">The activity feed is ready for some news.</p>
          <article v-for="item in data.news" :key="item.id" class="news-item">
            <strong>{{ item.author_name }}</strong>
            <span v-if="item.group_name" class="muted"> · {{ item.group_name }}</span>
            <p>{{ item.body }}</p>
          </article>
          <form v-if="canTeach()" class="news-form" @submit.prevent="post">
            <input v-model="message" placeholder="Post a message" required />
            <button class="btn small" type="submit">Post</button>
          </form>
        </section>
      </div>
    </div>
    <aside class="rail">
      <section class="card mini-cal">
        <header class="spread">
          <strong>{{ month.toLocaleString(undefined, { month: 'long', year: 'numeric' }) }}</strong>
          <router-link to="/calendar">full calendar</router-link>
        </header>
        <div class="cal-grid">
          <span v-for="label in ['S', 'M', 'T', 'W', 'T', 'F', 'S']" :key="label" class="dow">{{ label }}</span>
          <span v-for="(day, index) in days" :key="index" :class="{ today: day === month.getDate() }">{{ day || '' }}</span>
        </div>
      </section>
      <button class="promo" type="button" @click="ui.assistant = true">
        <span class="promo-mark">&lt;/&gt;</span>
        <strong>See the Student Assistant in action</strong>
        <p>Get a direct answer for courses, groups, grading, resources, and trash.</p>
      </button>
      <section class="card promo-copy">
        <h3>What’s new</h3>
        <p>Copy a course when a new class starts, post news to a group, and restore anything you move to trash.</p>
        <router-link to="/help">See all updates</router-link>
      </section>
      <section class="card promo-copy help-card">
        <h3>Need help?</h3>
        <p>Help walks through assigning a class, uploading a resource, and reading a report.</p>
        <router-link to="/help">Open help</router-link>
      </section>
    </aside>
  </div>
  <p v-else class="page muted">Loading the school desk…</p>
</template>
