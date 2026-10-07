<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api, isStaff, session, toast } from '../api'

const router = useRouter()
const jobs = ref([])
const applied = ref([])
const query = ref('')
const open = ref(false)
const posting = ref(false)
const noteFor = ref(null)
const note = ref('')
const form = ref({ title: '', company: '', location: '', job_type: 'Full-time', description: '' })

async function load() {
  const params = query.value ? `?q=${encodeURIComponent(query.value)}` : ''
  jobs.value = await api(`/jobs${params}`)
  if (session.user) applied.value = await api('/me/applications')
}

onMounted(load)
let timer
watch(query, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 180)
})

async function post() {
  posting.value = true
  try {
    await api('/jobs', { method: 'POST', body: form.value })
    toast('Role posted.')
    form.value = { title: '', company: '', location: '', job_type: 'Full-time', description: '' }
    open.value = false
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    posting.value = false
  }
}

async function apply(job) {
  if (!session.user) {
    router.push({ path: '/login', query: { next: '/job-openings' } })
    return
  }
  try {
    await api(`/jobs/${job.id}/apply`, { method: 'POST', body: { note: note.value } })
    toast('Your name is on the list.')
    note.value = ''
    noteFor.value = null
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
        <p class="kicker">Job board</p>
        <h1>Job Openings</h1>
        <p class="dek">Posted to people already studying, not to the open internet first.</p>
      </div>
      <div class="actions">
        <input v-model="query" class="search" type="search" placeholder="Search roles" aria-label="Search roles" />
        <button v-if="isStaff()" class="btn" type="button" @click="open = !open">Post a role</button>
      </div>
    </header>

    <form v-if="open" class="panel stack" style="padding: 1rem; margin-bottom: 1rem" @submit.prevent="post">
      <div class="row-2">
        <label class="field"><span>Title</span><input v-model="form.title" required /></label>
        <label class="field"><span>Company</span><input v-model="form.company" required /></label>
      </div>
      <div class="row-2">
        <label class="field"><span>Location</span><input v-model="form.location" /></label>
        <label class="field">
          <span>Type</span>
          <select v-model="form.job_type">
            <option>Full-time</option>
            <option>Part-time</option>
            <option>Contract</option>
          </select>
        </label>
      </div>
      <label class="field"><span>The work</span><textarea v-model="form.description"></textarea></label>
      <button class="btn" type="submit" :disabled="posting">Publish</button>
    </form>

    <article v-for="job in jobs" :key="job.id" class="panel" style="padding: 1rem; margin-bottom: 0.7rem">
      <div class="spread">
        <div>
          <p class="kicker">{{ job.company }} · {{ job.job_type }}</p>
          <router-link :to="`/job-openings/${job.id}`"><h3>{{ job.title }}</h3></router-link>
          <p class="muted">{{ job.location }} · {{ job.applicants }} applied</p>
        </div>
        <span v-if="applied.includes(job.id)" class="pill good">Applied</span>
      </div>
      <p style="margin: 0.7rem 0">{{ job.description }}</p>
      <div v-if="!applied.includes(job.id)" class="stack">
        <textarea v-if="noteFor === job.id" v-model="note" placeholder="A short note with your name"></textarea>
        <div class="actions">
          <button v-if="noteFor !== job.id" class="btn small" type="button" @click="noteFor = job.id">Put your name forward</button>
          <button v-else class="btn small" type="button" @click="apply(job)">Send</button>
        </div>
      </div>
    </article>
    <p v-if="!jobs.length" class="empty">No open roles.</p>
  </div>
</template>
