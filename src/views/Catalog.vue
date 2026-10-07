<script setup>
import { onMounted, ref, watch } from 'vue'
import CourseCard from '../components/CourseCard.vue'
import { api, canTeach, session } from '../api'

const courses = ref([])
const learning = ref([])
const categories = ref([])
const query = ref('')
const category = ref('')

async function load() {
  const params = new URLSearchParams()
  if (query.value) params.set('q', query.value)
  if (category.value) params.set('category', category.value)
  const suffix = params.toString() ? `?${params}` : ''
  courses.value = await api(`/courses${suffix}`)
}

onMounted(async () => {
  categories.value = await api('/categories').catch(() => [])
  if (session.user) learning.value = await api('/me/learning').catch(() => [])
  await load()
})

let timer
watch(query, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 180)
})
watch(category, load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Courses</h1>
        <p class="dek">Nothing is published until you create it. Add a course, then chapters and lessons, and publish when it is ready.</p>
      </div>
      <div class="actions">
        <input v-model="query" class="search" type="search" placeholder="Search courses" aria-label="Search courses" />
        <router-link v-if="canTeach()" class="btn" to="/courses/new">New course</router-link>
      </div>
    </header>

    <section v-if="learning.length" class="continue">
      <p class="kicker">Continue learning</p>
      <router-link v-for="course in learning" :key="course.id" class="panel" :to="`/courses/${course.slug}`">
        <span>
          <strong>{{ course.title }}</strong>
          <span class="muted"> · {{ course.instructor_name }}</span>
        </span>
        <span style="min-width: 140px">
          <strong>{{ course.progress }}%</strong>
          <span class="bar"><span :style="{ width: course.progress + '%' }"></span></span>
        </span>
      </router-link>
    </section>

    <div class="filters" style="margin-bottom: 16px">
      <button class="chip" :class="{ on: category === '' }" type="button" @click="category = ''">All</button>
      <button v-for="item in categories" :key="item" class="chip" :class="{ on: category === item }" type="button" @click="category = item">
        {{ item }}
      </button>
    </div>
    <div class="cards">
      <CourseCard v-for="course in courses" :key="course.id" :course="course" />
    </div>
    <p v-if="!courses.length" class="empty">No courses yet. Create one to build the outline and publish it.</p>
  </div>
</template>
