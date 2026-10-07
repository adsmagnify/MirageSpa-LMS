<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const rows = ref([])
onMounted(async () => {
  rows.value = await api('/certificates')
})
</script>

<template>
  <div class="page">
    <h1>Certified Participants</h1>
    <p class="dek">Members who passed an evaluation and hold a certificate.</p>
    <table style="margin-top: 16px">
      <thead><tr><th>Member</th><th>Course</th><th>Issued</th></tr></thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td><router-link :to="`/user/${row.username}`">{{ row.full_name }}</router-link></td>
          <td><router-link :to="`/courses/${row.slug}`">{{ row.course_title }}</router-link></td>
          <td>{{ row.issue_date.slice(0, 10) }}</td>
        </tr>
      </tbody>
    </table>
    <p v-if="!rows.length" class="empty">No certificates yet.</p>
  </div>
</template>
