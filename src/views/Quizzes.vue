<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api'

const quizzes = ref([])
const submissions = ref([])

onMounted(async () => {
  quizzes.value = await api('/quizzes')
  submissions.value = await api('/quiz-submissions').catch(() => [])
})
</script>

<template>
  <div class="page">
    <h1>Quizzes</h1>
    <p class="dek">Each quiz belongs to a course. Passing marks the lesson complete, up to the attempt limit.</p>
    <div class="cards" style="margin-top: 16px">
      <article v-for="quiz in quizzes" :key="quiz.id" class="panel" style="padding: 14px">
        <p class="kicker">{{ quiz.course_title }}</p>
        <h3>{{ quiz.title }}</h3>
        <p class="muted">Pass {{ quiz.passing_score }}%<template v-if="quiz.max_attempts"> · {{ quiz.attempts || 0 }}/{{ quiz.max_attempts }} attempts</template></p>
        <p v-if="quiz.last_attempt" style="margin: 8px 0"><span class="pill" :class="quiz.last_attempt.passed ? 'good' : 'warn'">{{ quiz.last_attempt.score }}%</span></p>
        <router-link :to="`/quiz/${quiz.id}`">Open quiz</router-link>
      </article>
    </div>
    <section v-if="submissions.length" style="margin-top: 28px">
      <h2>Submissions</h2>
      <table>
        <thead><tr><th>Member</th><th>Quiz</th><th>Course</th><th>Score</th></tr></thead>
        <tbody>
          <tr v-for="row in submissions" :key="row.id">
            <td>{{ row.full_name }}</td>
            <td>{{ row.quiz_title }}</td>
            <td>{{ row.course_title }}</td>
            <td>{{ row.score }}%</td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
