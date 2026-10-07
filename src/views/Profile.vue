<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api, session, toast } from '../api'

const route = useRoute()
const data = ref(null)

async function load() {
  data.value = await api(`/profiles/${route.params.username}`)
}
onMounted(load)
watch(() => route.params.username, load)

async function decide(request, passed) {
  try {
    await api(`/certificates/${request.id}/decide`, { method: 'POST', body: { passed } })
    toast(passed ? 'Certificate issued.' : 'Marked as not passed.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page" v-if="data">
    <p class="kicker">{{ data.profile.frappe_role }}</p>
    <h1>{{ data.profile.full_name }}</h1>
    <p class="dek">{{ data.profile.headline }}</p>
    <p v-if="data.profile.email" class="muted">{{ data.profile.email }}</p>

    <h2 style="margin-top: 24px">Courses</h2>
    <router-link v-for="course in data.courses" :key="course.slug" class="panel spread" style="padding: 12px; margin-top: 8px" :to="`/courses/${course.slug}`">
      <span>{{ course.title }}</span><span>{{ course.progress }}%</span>
    </router-link>
    <p v-if="!data.courses.length" class="empty">No enrollments.</p>

    <h2 style="margin-top: 24px">Certificates</h2>
    <p v-for="item in data.certificates" :key="item.slug">{{ item.title }} · {{ item.issue_date.slice(0, 10) }}</p>
    <p v-if="!data.certificates.length" class="empty">None yet.</p>

    <section v-if="data.requests?.length" style="margin-top: 24px">
      <h2>Evaluation requests</h2>
      <article v-for="request in data.requests" :key="request.id" class="panel" style="padding: 12px; margin-top: 8px">
        <strong>{{ request.student_name }}</strong> · {{ request.course_title }} · {{ request.status }}
        <div v-if="request.status === 'scheduled' && session.user?.id === data.profile.id" class="actions" style="margin-top: 8px">
          <button class="btn small" type="button" @click="decide(request, true)">Pass</button>
          <button class="btn small ghost" type="button" @click="decide(request, false)">Fail</button>
        </div>
      </article>
    </section>
  </div>
</template>
