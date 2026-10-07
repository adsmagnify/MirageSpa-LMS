<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const data = ref({ courses: [], batches: [] })
const error = ref('')

onMounted(async () => {
  try {
    data.value = await api('/me/classes')
  } catch (err) {
    error.value = err.message
  }
})
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>My Classes</h1>
        <p class="dek">These are the courses and batches assigned to you.</p>
      </div>
    </header>
    <p v-if="error" class="empty">{{ error }}</p>
    <section>
      <article v-for="course in data.courses" :key="course.id" class="panel spread" style="padding: 14px; margin-bottom: 8px">
        <div>
          <p class="kicker">{{ course.instructor_name }}</p>
          <router-link :to="`/courses/${course.slug}`"><strong>{{ course.title }}</strong></router-link>
        </div>
        <span>{{ course.progress }}%</span>
      </article>
      <article v-for="batch in data.batches" :key="batch.id" class="panel" style="padding: 14px; margin-bottom: 8px">
        <p class="kicker">Batch · {{ batch.course_title }}</p>
        <router-link :to="`/batches/${batch.id}`"><strong>{{ batch.title }}</strong></router-link>
      </article>
      <p v-if="!data.courses.length && !data.batches.length" class="empty">No classes have been assigned to you yet.</p>
    </section>
  </div>
</template>
