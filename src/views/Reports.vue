<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const data = ref(null)
onMounted(async () => {
  data.value = await api('/reports')
})
</script>

<template>
  <div v-if="data" class="page">
    <header class="page-head">
      <div>
        <h1>Reports</h1>
        <p class="dek">Progress across the classes you teach. Completed means every lesson in that course is finished.</p>
      </div>
    </header>
    <div class="stat-row">
      <article class="card"><span>Courses</span><strong>{{ data.totals.courses }}</strong></article>
      <article class="card"><span>Learners</span><strong>{{ data.totals.learners }}</strong></article>
      <article class="card"><span>Completed</span><strong>{{ data.totals.completed }}</strong></article>
      <article class="card"><span>To score</span><strong>{{ data.totals.to_score }}</strong></article>
    </div>
    <section class="card">
      <div class="table-head report-head">
        <span>Course</span><span>Learners</span><span>Completed</span><span>Average</span><span>To score</span>
      </div>
      <div v-for="row in data.courses" :key="row.id" class="course-row report-row">
        <router-link :to="`/courses/${row.slug}`"><strong>{{ row.title }}</strong></router-link>
        <span>{{ row.learners }}</span>
        <span>{{ row.completed }}</span>
        <span>{{ row.average_progress }}%</span>
        <span>{{ row.to_score }}</span>
      </div>
      <p v-if="!data.courses.length" class="empty">No courses to report yet.</p>
    </section>
  </div>
</template>
