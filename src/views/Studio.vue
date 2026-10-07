<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, toast } from '../api'

const router = useRouter()
const courses = ref([])
const busy = ref(false)
const form = ref({
  title: '',
  summary: '',
  description: '',
  category: 'General',
  level: 'Foundation',
})

async function load() {
  courses.value = await api('/studio/courses')
}
onMounted(load)

async function create() {
  busy.value = true
  try {
    const course = await api('/studio/courses', { method: 'POST', body: form.value })
    toast('Draft opened.')
    router.push(`/studio/${course.id}`)
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <p class="kicker">Course studio</p>
        <h2>Make something teachable</h2>
        <p class="dek">A draft stays private. Publish it, then share the course link.</p>
      </div>
    </header>
    <div class="cards" style="align-items: start">
      <form class="panel stack" style="padding: 1rem" @submit.prevent="create">
        <h3>New course</h3>
        <label class="field"><span>Title</span><input v-model="form.title" required /></label>
        <label class="field"><span>Summary</span><input v-model="form.summary" /></label>
        <label class="field"><span>Description</span><textarea v-model="form.description"></textarea></label>
        <div class="row-2">
          <label class="field"><span>Category</span><input v-model="form.category" /></label>
          <label class="field"><span>Level</span><input v-model="form.level" /></label>
        </div>
        <button class="btn" type="submit" :disabled="busy">Create draft</button>
      </form>
      <div class="stack">
        <router-link v-for="course in courses" :key="course.id" class="panel" style="padding: 0.9rem; text-decoration: none" :to="`/studio/${course.id}`">
          <div class="spread">
            <h3>{{ course.title }}</h3>
            <span class="pill" :class="course.status === 'published' ? 'good' : 'warn'">{{ course.status }}</span>
          </div>
          <p class="muted">{{ course.lesson_count }} lessons · {{ course.learners }} enrolled</p>
        </router-link>
        <p v-if="!courses.length" class="empty">No courses in your studio yet.</p>
      </div>
    </div>
  </div>
</template>
