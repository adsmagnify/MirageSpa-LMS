<script setup>
import { onMounted, ref } from 'vue'
import { api, canTeach, toast } from '../api'

const batches = ref([])
const courses = ref([])
const error = ref('')
const open = ref(false)
const busy = ref(false)
const form = ref({ title: '', course_id: '', start_date: '', end_date: '', seat_limit: 12, description: '' })

async function load() {
  try {
    batches.value = await api('/batches')
    if (canTeach()) courses.value = await api('/studio/courses')
  } catch (err) {
    error.value = err.message
  }
}

onMounted(load)

async function create() {
  busy.value = true
  try {
    await api('/batches', { method: 'POST', body: { ...form.value, course_id: Number(form.value.course_id), seat_limit: Number(form.value.seat_limit) } })
    toast('Batch opened.')
    open.value = false
    form.value.title = ''
    await load()
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
        <p class="kicker">Cohorts</p>
        <h1>Batches</h1>
        <p class="dek">A batch is a course with names, dates, and a shared live room. Progress is counted per person.</p>
      </div>
      <button v-if="canTeach()" class="btn" type="button" @click="open = !open">New batch</button>
    </header>
    <form v-if="open" class="panel stack" style="padding: 1rem; margin-bottom: 1rem" @submit.prevent="create">
      <div class="row-2">
        <label class="field"><span>Name</span><input v-model="form.title" required /></label>
        <label class="field">
          <span>Course</span>
          <select v-model="form.course_id" required>
            <option value="" disabled>Choose</option>
            <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option>
          </select>
        </label>
      </div>
      <div class="row-3">
        <label class="field"><span>Starts</span><input v-model="form.start_date" type="date" /></label>
        <label class="field"><span>Ends</span><input v-model="form.end_date" type="date" /></label>
        <label class="field"><span>Seats</span><input v-model="form.seat_limit" type="number" min="1" /></label>
      </div>
      <label class="field"><span>Note</span><textarea v-model="form.description"></textarea></label>
      <button class="btn" :disabled="busy" type="submit">Open batch</button>
    </form>
    <p v-if="error" class="empty">{{ error }}</p>
    <div v-else class="cards">
      <router-link v-for="batch in batches" :key="batch.id" class="panel" style="padding: 1rem; text-decoration: none" :to="`/batches/${batch.id}`">
        <p class="kicker">{{ batch.course_title }}</p>
        <h3>{{ batch.title }}</h3>
        <p class="muted" style="margin: 0.35rem 0 0.8rem">{{ batch.instructor_name }} · {{ batch.start_date }} to {{ batch.end_date }}</p>
        <div class="spread">
          <span>{{ batch.seats_taken }}/{{ batch.seat_limit }} seated</span>
          <span class="pill" :class="batch.joined ? 'good' : ''">{{ batch.joined ? 'You are in' : batch.status }}</span>
        </div>
        <div v-if="batch.joined" class="bar" style="margin-top: 0.7rem"><span :style="{ width: `${batch.progress || 0}%` }"></span></div>
      </router-link>
    </div>
  </div>
</template>
