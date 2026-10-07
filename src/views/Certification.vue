<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, session, toast } from '../api'

const route = useRoute()
const router = useRouter()
const data = ref(null)

async function load() {
  if (!session.user) {
    router.push({ path: '/login', query: { next: route.fullPath } })
    return
  }
  data.value = await api(`/courses/${route.params.slug}/certification`)
}
onMounted(load)

async function request() {
  try {
    await api('/certificates/request', { method: 'POST', body: { course_id: data.value.course.id } })
    toast('Evaluation booked. The next open slot is yours.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function decide(passed) {
  try {
    await api(`/certificates/${data.value.request.id}/decide`, { method: 'POST', body: { passed } })
    toast(passed ? 'Certificate issued.' : 'Marked as not passed.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page" v-if="data">
    <p class="crumbs">
      <router-link to="/courses">Courses</router-link><span>/</span>
      <router-link :to="`/courses/${data.course.slug}`">{{ data.course.title }}</router-link><span>/</span>
      <span>Certification</span>
    </p>
    <h1>Certification</h1>
    <p v-if="data.certificate" class="dek">Issued {{ data.certificate.issue_date.slice(0, 10) }} for {{ data.certificate.member_name || session.user.full_name }}.</p>
    <p v-else-if="!data.course.enable_certification" class="dek">This course does not issue a certificate.</p>
    <p v-else-if="!data.eligible" class="dek">Finish every lesson ({{ data.progress }}%) before you can book an evaluation.</p>
    <p v-else class="dek">You are eligible. Booking places you in the evaluator's next open slot.</p>

    <article v-if="data.certificate" class="certificate">
      <p class="kicker">LMS Certificate</p>
      <h2>{{ data.certificate.member_name || session.user.full_name }}</h2>
      <p>{{ data.course.title }}</p>
    </article>

    <article v-else-if="data.request" class="panel" style="padding: 14px; margin-top: 16px">
      <p class="kicker">Evaluation</p>
      <p>Status: {{ data.request.status }}</p>
      <p v-if="data.request.starts_at" class="muted">{{ data.request.starts_at }}</p>
      <div v-if="session.user.role !== 'student' && data.request.status === 'scheduled'" class="actions" style="margin-top: 10px">
        <button class="btn small" type="button" @click="decide(true)">Pass and issue</button>
        <button class="btn small ghost" type="button" @click="decide(false)">Do not pass</button>
      </div>
    </article>

    <button v-else-if="data.eligible" class="btn" style="margin-top: 16px" type="button" @click="request">Book evaluation</button>
  </div>
</template>
