<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { api, session, toast } from '../api'
import { pretty, shortDay } from '../format'

const data = ref(null)
const people = ref([])
const error = ref('')
let timer

const max = computed(() => {
  const series = data.value?.series || []
  return Math.max(1, ...series.map((point) => Math.max(point.signups, point.enrollments)))
})

function height(value) {
  return `${Math.max(value ? 6 : 0, (value / max.value) * 120)}px`
}

async function load() {
  try {
    data.value = await api('/analytics')
    if (session.user?.role === 'admin') people.value = await api('/users')
  } catch (err) {
    error.value = err.message
  }
}

async function setRole(person, role) {
  try {
    await api(`/users/${person.id}`, { method: 'PATCH', body: { role } })
    person.role = role
    toast(`${person.full_name} is now ${role}.`)
  } catch (err) {
    toast(err.message, 'warn')
  }
}

onMounted(() => {
  load()
  timer = setInterval(load, 8000)
})
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div class="page" v-if="data">
    <header class="page-head">
      <div>
        <p class="kicker">Insights · live</p>
        <h1>Statistics</h1>
        <p class="dek">Refreshed every few seconds. Last count {{ pretty(data.generated_at) }}.</p>
      </div>
    </header>
    <section class="kpis">
      <article class="panel kpi"><span class="muted">Signups, 14 days</span><strong>{{ data.signups_14d }}</strong></article>
      <article class="panel kpi"><span class="muted">Enrollments, 14 days</span><strong>{{ data.enrollments_14d }}</strong></article>
      <article class="panel kpi"><span class="muted">Quiz passes</span><strong>{{ data.quiz_passes }}/{{ data.quiz_attempts }}</strong></article>
      <article class="panel kpi"><span class="muted">Live joins</span><strong>{{ data.live_joins }}</strong></article>
    </section>

    <section class="panel" style="padding: 1rem; margin-top: 1rem">
      <div class="spread">
        <h3>Fourteen days</h3>
        <p class="legend"><span><i class="signup"></i>Signups</span><span><i class="enroll"></i>Enrollments</span></p>
      </div>
      <div class="chart" style="margin-top: 0.8rem">
        <div v-for="point in data.series" :key="point.day" class="col">
          <div class="pair">
            <i class="signup" :style="{ height: height(point.signups) }" :title="`${point.signups} signups`"></i>
            <i class="enroll" :style="{ height: height(point.enrollments) }" :title="`${point.enrollments} enrollments`"></i>
          </div>
          <small>{{ shortDay(point.day).replace(/.*, /, '') }}</small>
        </div>
      </div>
    </section>

    <section class="cards" style="margin-top: 1rem; align-items: start">
      <article class="panel table-wrap" style="padding: 0.6rem 1rem 1rem">
        <p class="kicker">Courses</p>
        <table>
          <thead><tr><th>Course</th><th>Enrolled</th><th>Avg progress</th></tr></thead>
          <tbody>
            <tr v-for="course in data.courses" :key="course.id">
              <td><router-link :to="`/courses/${course.slug}`">{{ course.title }}</router-link><div class="muted">{{ course.status }}</div></td>
              <td>{{ course.enrollments }}</td>
              <td style="min-width: 120px"><div class="bar"><span :style="{ width: `${course.avg_progress}%` }"></span></div></td>
            </tr>
          </tbody>
        </table>
      </article>
      <article class="panel" style="padding: 1rem">
        <p class="kicker">Just happened</p>
        <p v-for="(item, index) in data.feed" :key="index" style="padding: 0.55rem 0; border-bottom: 1px solid var(--line)">
          <strong>{{ item.full_name || 'New account' }}</strong>
          {{ item.kind === 'signup' ? 'joined the academy' : `enrolled in ${item.course_title}` }}
          <span class="muted"> · {{ pretty(item.created_at) }}</span>
        </p>
      </article>
    </section>

    <section v-if="people.length" class="panel table-wrap" style="padding: 0.6rem 1rem 1rem; margin-top: 1rem">
      <p class="kicker">Accounts</p>
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
        <tbody>
          <tr v-for="person in people" :key="person.id">
            <td>{{ person.full_name }}</td>
            <td>{{ person.email }}</td>
            <td>
              <select :value="person.role" :disabled="person.id === session.user.id" @change="setRole(person, $event.target.value)">
                <option>student</option>
                <option>instructor</option>
                <option>moderator</option>
                <option>admin</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
  <div v-else class="page"><p class="empty">{{ error || 'Counting…' }}</p></div>
</template>
