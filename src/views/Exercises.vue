<script setup>
import { onMounted, ref } from 'vue'
import { api, canTeach, toast } from '../api'

const exercises = ref([])
const submissions = ref([])
const drafts = ref({})

async function load() {
  exercises.value = await api('/exercises')
  submissions.value = await api('/exercise-submissions')
}
onMounted(load)

async function submit(exercise) {
  try {
    await api(`/exercises/${exercise.id}/submit`, { method: 'POST', body: { code: drafts.value[exercise.id] || '' } })
    toast('Submitted for review.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function grade(row, status) {
  try {
    await api(`/exercise-submissions/${row.id}/grade`, { method: 'POST', body: { status, feedback: 'Marked in the exercise queue.' } })
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page">
    <h1>Programming Exercises</h1>
    <p class="dek">A written exercise attached to a course. Instructors mark each submission pass or fail.</p>
    <article v-for="exercise in exercises" :key="exercise.id" class="panel stack" style="padding: 14px; margin-top: 12px">
      <p class="kicker">{{ exercise.course_title }}</p>
      <h3>{{ exercise.title }}</h3>
      <p>{{ exercise.problem }}</p>
      <textarea v-model="drafts[exercise.id]" aria-label="Answer"></textarea>
      <button class="btn small" type="button" @click="submit(exercise)">Submit</button>
    </article>
    <h2 style="margin-top: 24px">Submissions</h2>
    <article v-for="row in submissions" :key="row.id" class="panel" style="padding: 12px; margin-top: 8px">
      <div class="spread">
        <strong>{{ row.title }} · {{ row.student_name }}</strong>
        <span class="pill" :class="row.status === 'pass' ? 'good' : row.status === 'fail' ? 'warn' : ''">{{ row.status }}</span>
      </div>
      <p style="margin-top: 6px">{{ row.code }}</p>
      <div v-if="canTeach() && row.status === 'pending'" class="actions" style="margin-top: 8px">
        <button class="btn small" type="button" @click="grade(row, 'pass')">Pass</button>
        <button class="btn small ghost" type="button" @click="grade(row, 'fail')">Fail</button>
      </div>
    </article>
  </div>
</template>
