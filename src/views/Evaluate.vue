<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, canTeach, session, toast } from '../api'
import { pretty } from '../format'

const router = useRouter()
const payload = ref({ slots: [], zoom_connected: false })
const learning = ref([])
const courseId = ref('')
const busy = ref(false)
const outcome = ref('Ready for the floor')
const notes = ref('')

const mine = computed(() => payload.value.slots.filter((slot) => slot.student_id === session.user?.id))
const openSlots = computed(() => payload.value.slots.filter((slot) => slot.status === 'open' && new Date(slot.starts_at) > new Date()))

async function load() {
  payload.value = await api('/evaluations')
  if (session.user) learning.value = await api('/me/learning')
}

onMounted(load)

function ensureUser() {
  if (session.user) return true
  router.push({ path: '/enter', query: { next: '/evaluations' } })
  return false
}

async function generate() {
  if (!ensureUser()) return
  busy.value = true
  try {
    const result = await api('/evaluations/generate', { method: 'POST' })
    toast(`${result.created} hours published.`)
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function book() {
  if (!ensureUser()) return
  busy.value = true
  try {
    const slot = await api('/evaluations/book', { method: 'POST', body: { course_id: Number(courseId.value) } })
    toast(`Booked ${pretty(slot.starts_at)} with ${slot.instructor_name}.`)
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function release(slot) {
  try {
    await api(`/evaluations/${slot.id}/release`, { method: 'POST' })
    toast('Time released.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function close(slot) {
  try {
    await api(`/evaluations/${slot.id}/close`, { method: 'POST', body: { outcome: outcome.value, notes: notes.value } })
    toast('Evaluation closed.')
    notes.value = ''
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <p class="kicker">Evaluation calls</p>
        <h2>The next open hour</h2>
        <p class="dek">
          Instructors publish a run of hours. A learner asks for an evaluation and is placed in the earliest open slot — no back-and-forth.
          <span v-if="payload.zoom_connected" class="pill good">Zoom connected</span>
        </p>
      </div>
      <button v-if="canTeach()" class="btn" type="button" :disabled="busy" @click="generate">Publish the next two weeks</button>
    </header>

    <section class="panel stack" style="padding: 1rem; margin-bottom: 1rem">
      <h3>Book automatically</h3>
      <div class="row-2">
        <label class="field">
          <span>Course</span>
          <select v-model="courseId">
            <option value="" disabled>Choose an enrolled course</option>
            <option v-for="course in learning" :key="course.id" :value="course.id">{{ course.title }}</option>
          </select>
        </label>
        <div class="field">
          <span>Placement</span>
          <button class="btn" type="button" :disabled="busy || !courseId" @click="book">Book the next open call</button>
        </div>
      </div>
      <p class="muted">{{ openSlots.length }} open times on the ledger.</p>
    </section>

    <section v-if="mine.length" style="margin-bottom: 1rem">
      <h3>Your calls</h3>
      <article v-for="slot in mine" :key="slot.id" class="panel spread" style="padding: 0.9rem; margin-top: 0.6rem">
        <div>
          <strong>{{ slot.course_title || 'Evaluation' }}</strong>
          <p class="muted">{{ slot.instructor_name }} · {{ pretty(slot.starts_at) }} · {{ slot.status }}</p>
          <p v-if="slot.outcome">{{ slot.outcome }}</p>
        </div>
        <button v-if="slot.status === 'booked'" class="btn small ghost" type="button" @click="release(slot)">Release</button>
      </article>
    </section>

    <section class="table-wrap panel" style="padding: 0.4rem 1rem 1rem">
      <table>
        <thead>
          <tr>
            <th>When</th>
            <th>Instructor</th>
            <th>Status</th>
            <th>Learner</th>
            <th>Course</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="slot in payload.slots" :key="slot.id">
            <td>{{ pretty(slot.starts_at) }}</td>
            <td>{{ slot.instructor_name }}</td>
            <td><span class="pill" :class="slot.status === 'open' ? 'good' : slot.status === 'booked' ? 'warn' : ''">{{ slot.status }}</span></td>
            <td>{{ slot.student_name || '—' }}</td>
            <td>{{ slot.course_title || '—' }}</td>
            <td>
              <button
                v-if="canTeach() && slot.status === 'booked' && (session.user?.id === slot.instructor_id || session.user?.role === 'admin')"
                class="btn small"
                type="button"
                @click="close(slot)"
              >
                Close
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
    <form v-if="canTeach()" class="row-2" style="margin-top: 0.8rem" @submit.prevent>
      <label class="field"><span>Outcome used when you close a call</span><input v-model="outcome" /></label>
      <label class="field"><span>Note</span><input v-model="notes" /></label>
    </form>
  </div>
</template>
