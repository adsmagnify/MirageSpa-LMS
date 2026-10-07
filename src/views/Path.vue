<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const courses = ref([])
const error = ref('')

onMounted(async () => {
  try {
    courses.value = await api('/me/learning')
  } catch (err) {
    error.value = err.message
  }
})
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <p class="kicker">My path</p>
        <h2>What you are in</h2>
        <p class="dek">Enrolled courses and how far the lessons have gone.</p>
      </div>
    </header>
    <p v-if="error" class="empty">{{ error }}</p>
    <div v-else-if="!courses.length" class="empty">
      You have not enrolled yet. <router-link to="/courses">Browse the courses.</router-link>
    </div>
    <div v-else class="stack">
      <router-link v-for="course in courses" :key="course.id" class="panel spread" style="padding: 1rem 1.1rem; text-decoration: none" :to="`/learn/${course.slug}`">
        <div>
          <p class="kicker">{{ course.category }}</p>
          <h3>{{ course.title }}</h3>
          <p class="muted">{{ course.instructor_name }}</p>
        </div>
        <div style="min-width: 140px">
          <strong class="serif" style="font-size: 1.8rem">{{ course.progress }}%</strong>
          <div class="bar"><span :style="{ width: `${course.progress}%` }"></span></div>
        </div>
      </router-link>
    </div>
  </div>
</template>
