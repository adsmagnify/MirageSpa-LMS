<script setup>
import { onMounted, ref } from 'vue'
import { api, canTeach } from '../api'
import CourseTable from '../components/CourseTable.vue'

const data = ref(null)
const tab = ref('teaching')

async function load() {
  data.value = await api('/desk')
  if (!canTeach()) tab.value = 'enrolled'
}
onMounted(load)
</script>

<template>
  <div v-if="data" class="page desk-page">
    <header class="spread">
      <div>
        <h1>Courses</h1>
        <p class="dek">Teaching is every class you run. Enrolled is every class assigned to you.</p>
      </div>
      <router-link v-if="canTeach()" class="btn" to="/courses/new">New course</router-link>
    </header>
    <section class="card">
      <div class="segs">
        <button type="button" :class="{ on: tab === 'teaching' }" @click="tab = 'teaching'">Teaching <span>{{ data.teaching.length }}</span></button>
        <button type="button" :class="{ on: tab === 'enrolled' }" @click="tab = 'enrolled'">Enrolled <span>{{ data.enrolled.length }}</span></button>
      </div>
      <CourseTable :rows="tab === 'enrolled' ? data.enrolled : data.teaching" @changed="load" />
    </section>
  </div>
</template>
