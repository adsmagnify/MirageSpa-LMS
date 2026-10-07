<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, isStaff, session, toast } from '../api'

const route = useRoute()
const router = useRouter()
const data = ref(null)
const note = ref('')

async function load() {
  data.value = await api(`/jobs/${route.params.id}`)
}
onMounted(load)

async function apply() {
  if (!session.user) return router.push({ path: '/login', query: { next: route.fullPath } })
  try {
    await api(`/jobs/${route.params.id}/apply`, { method: 'POST', body: { note: note.value } })
    toast('Application sent.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page" v-if="data">
    <p class="crumbs"><router-link to="/job-openings">Job Openings</router-link><span>/</span><span>{{ data.job.title }}</span></p>
    <p class="kicker">{{ data.job.company }} · {{ data.job.job_type }}</p>
    <h1>{{ data.job.title }}</h1>
    <p class="dek">{{ data.job.location }} · {{ data.job.applicants }} applications</p>
    <p style="margin: 16px 0">{{ data.job.description }}</p>
    <p v-if="data.applied" class="pill good">Applied</p>
    <form v-else class="stack" style="max-width: 560px" @submit.prevent="apply">
      <label class="field"><span>Note</span><textarea v-model="note"></textarea></label>
      <button class="btn" type="submit">Apply</button>
    </form>
    <section v-if="isStaff()" style="margin-top: 24px">
      <h2>Applications</h2>
      <article v-for="item in data.applications" :key="item.id" class="panel" style="padding: 12px; margin-top: 8px">
        <strong>{{ item.full_name }}</strong>
        <p class="muted">{{ item.email }}</p>
        <p>{{ item.note }}</p>
      </article>
      <p v-if="!data.applications.length" class="empty">No applications yet.</p>
    </section>
  </div>
</template>
