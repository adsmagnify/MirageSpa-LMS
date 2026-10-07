<script setup>
import { onMounted, ref } from 'vue'
import { api, session, toast } from '../api'

const programs = ref([])

async function load() {
  programs.value = await api('/programs')
}
onMounted(load)

async function enroll(program) {
  if (!session.user) return
  try {
    await api(`/programs/${program.id}/enroll`, { method: 'POST' })
    toast('Enrolled in every course in the program.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page">
    <h1>Programs</h1>
    <p class="dek">A program is an ordered set of courses. Joining enrolls you in each course, and progress is the average.</p>
    <article v-for="program in programs" :key="program.id" class="panel" style="padding: 16px; margin-top: 16px">
      <div class="spread">
        <div>
          <h2>{{ program.title }}</h2>
          <p class="dek">{{ program.description }}</p>
        </div>
        <button v-if="session.user && !program.joined" class="btn" type="button" @click="enroll(program)">Enroll</button>
        <span v-else-if="program.joined" class="pill good">{{ program.progress }}%</span>
      </div>
      <p v-for="(course, index) in program.courses" :key="course.id" style="margin-top: 8px">
        <router-link :to="`/courses/${course.slug}`">{{ index + 1 }}. {{ course.title }}</router-link>
      </p>
      <p class="muted" style="margin-top: 8px">{{ program.members }} members</p>
    </article>
  </div>
</template>
