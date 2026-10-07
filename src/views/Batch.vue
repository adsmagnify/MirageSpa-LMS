<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, canTeach, isStaff, session, toast } from '../api'
import { pretty } from '../format'

const route = useRoute()
const data = ref(null)
const error = ref('')
const busy = ref(false)
const tab = ref('overview')
const announcements = ref([])
const discussions = ref([])
const announce = ref({ title: '', body: '' })
const comment = ref('')

const roster = computed(() => {
  if (!data.value || !session.user) return false
  return data.value.batch.joined || isStaff()
})

async function load() {
  try {
    data.value = await api(`/batches/${route.params.id}`)
    announcements.value = await api(`/batches/${route.params.id}/announcements`)
    discussions.value = await api(`/batches/${route.params.id}/discussions`)
  } catch (err) {
    error.value = err.message
  }
}

onMounted(load)

async function postAnnouncement() {
  try {
    await api(`/batches/${route.params.id}/announcements`, { method: 'POST', body: announce.value })
    announce.value = { title: '', body: '' }
    announcements.value = await api(`/batches/${route.params.id}/announcements`)
    toast('Announcement posted.')
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function postComment() {
  try {
    await api('/discussions', { method: 'POST', body: { batch_id: Number(route.params.id), body: comment.value } })
    comment.value = ''
    discussions.value = await api(`/batches/${route.params.id}/discussions`)
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function join() {
  if (!session.user) return
  busy.value = true
  try {
    data.value = await api(`/batches/${route.params.id}/join`, { method: 'POST' })
    toast('You are in the batch.')
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

</script>

<template>
  <div class="page" v-if="data">
    <header class="page-head">
      <div>
        <p class="kicker">{{ data.batch.course_title }}</p>
        <h1>{{ data.batch.title }}</h1>
        <p class="dek">{{ data.batch.description }}</p>
        <p class="muted" style="margin-top: 0.45rem">
          {{ data.batch.instructor_name }} · {{ data.batch.start_date }} – {{ data.batch.end_date }} · {{ data.batch.seats_taken }}/{{ data.batch.seat_limit }}
        </p>
      </div>
      <div class="actions">
        <router-link class="btn ghost" :to="`/courses/${data.batch.course_slug}`">Course</router-link>
        <router-link v-if="!session.user" class="btn" :to="{ path: '/login', query: { next: route.fullPath } }">Sign in to join</router-link>
        <button v-else-if="!data.batch.joined" class="btn" type="button" :disabled="busy" @click="join">Join batch</button>
        <router-link v-else-if="data.batch.joined" class="btn" :to="`/courses/${data.batch.course_slug}/learn/1-1`">Continue course</router-link>
      </div>
    </header>

    <nav class="tabs">
      <button type="button" :class="{ on: tab === 'overview' }" @click="tab = 'overview'">Overview</button>
      <button type="button" :class="{ on: tab === 'dashboard' }" @click="tab = 'dashboard'">Dashboard</button>
      <button type="button" :class="{ on: tab === 'classes' }" @click="tab = 'classes'">Live Classes</button>
      <button type="button" :class="{ on: tab === 'announcements' }" @click="tab = 'announcements'">Announcements</button>
      <button type="button" :class="{ on: tab === 'discussions' }" @click="tab = 'discussions'">Discussions</button>
    </nav>

    <section v-show="tab === 'overview' || tab === 'classes'" class="panel" style="padding: 1rem; margin-bottom: 1rem">
      <p class="kicker">Live classes</p>
      <p v-if="!data.live_classes.length" class="empty">No live class on this batch yet.</p>
      <p v-for="item in data.live_classes" :key="item.id" class="spread" style="padding: 0.55rem 0; border-bottom: 1px solid var(--line)">
        <span>{{ item.title }}</span>
        <span class="muted">{{ pretty(item.starts_at) }} · {{ item.status }}</span>
      </p>
    </section>

    <section v-show="tab === 'overview' || tab === 'dashboard'" v-if="roster" class="panel table-wrap" style="padding: 0.4rem 1rem 1rem">
      <p class="kicker" style="padding-top: 0.8rem">Engagement</p>
      <table>
        <thead>
          <tr>
            <th>Learner</th>
            <th>Lessons</th>
            <th>Progress</th>
            <th>Quizzes</th>
            <th>Live</th>
            <th>Engagement</th>
            <th>Last active</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="student in data.students" :key="student.id">
            <td>
              <strong>{{ student.full_name }}</strong>
              <div class="muted">{{ student.email }}</div>
            </td>
            <td>{{ student.lessons_done }}/{{ student.lessons_total }}</td>
            <td style="min-width: 110px"><div class="bar"><span :style="{ width: `${student.progress}%` }"></span></div></td>
            <td>{{ student.quizzes_passed }}</td>
            <td>{{ student.live_attended }}</td>
            <td>{{ student.engagement }}</td>
            <td>{{ pretty(student.last_active) }}</td>
          </tr>
        </tbody>
      </table>
    </section>
    <p v-else-if="tab === 'overview' || tab === 'dashboard'" class="empty">Join the batch to see the roster and how the cohort is moving.</p>

    <section v-show="tab === 'announcements'" class="stack">
      <form v-if="canTeach()" class="panel stack" style="padding: 14px" @submit.prevent="postAnnouncement">
        <input v-model="announce.title" placeholder="Title" required />
        <textarea v-model="announce.body" placeholder="Tell the batch" required></textarea>
        <button class="btn small" type="submit">Post announcement</button>
      </form>
      <article v-for="item in announcements" :key="item.id" class="panel" style="padding: 14px">
        <h3>{{ item.title }}</h3>
        <p class="muted">{{ item.full_name }}</p>
        <p>{{ item.body }}</p>
      </article>
      <p v-if="!announcements.length" class="empty">No announcements yet.</p>
    </section>

    <section v-show="tab === 'discussions'" class="stack">
      <form v-if="session.user" class="panel stack" style="padding: 14px" @submit.prevent="postComment">
        <textarea v-model="comment" placeholder="Reply to the batch" required></textarea>
        <button class="btn small" type="submit">Reply</button>
      </form>
      <article v-for="item in discussions" :key="item.id" class="panel" style="padding: 14px">
        <strong>{{ item.full_name }}</strong>
        <p>{{ item.body }}</p>
      </article>
      <p v-if="!discussions.length" class="empty">No discussion yet.</p>
    </section>
  </div>
  <div v-else class="page"><p class="empty">{{ error || 'Opening the batch…' }}</p></div>
</template>
