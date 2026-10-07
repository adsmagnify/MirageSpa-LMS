<script setup>
import { onMounted, ref } from 'vue'
import { api, isAdmin, toast } from '../api'

const data = ref(null)
const name = ref('')

async function load() {
  data.value = await api('/school')
  name.value = data.value.school.name
}
onMounted(load)

async function save() {
  try {
    const school = await api('/school', { method: 'PATCH', body: { name: name.value } })
    data.value.school = school
    toast('School name saved.')
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div v-if="data" class="page">
    <header class="page-head">
      <div>
        <h1>Schools</h1>
        <p class="dek">One school holds the courses, instructors, and learners on this desk.</p>
      </div>
    </header>
    <section class="card stack" style="max-width: 640px">
      <h2>{{ data.school.name }}</h2>
      <p class="muted">{{ data.courses }} courses · {{ data.learners }} learners · {{ data.instructors.length }} instructors</p>
      <form v-if="isAdmin()" class="stack" @submit.prevent="save">
        <label class="field"><span>School name</span><input v-model="name" required /></label>
        <button class="btn small" type="submit">Save</button>
      </form>
      <h3>Instructors</h3>
      <p v-for="person in data.instructors" :key="person.id">{{ person.full_name }} <span class="muted">{{ person.email }}</span></p>
    </section>
  </div>
</template>
