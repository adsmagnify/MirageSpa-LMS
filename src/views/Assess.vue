<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api, canTeach, toast } from '../api'
import { pretty } from '../format'

const mine = ref({ quizzes: [], submissions: [] })
const queue = ref([])
const drafts = reactive({})
const busy = ref(false)

async function load() {
  mine.value = await api('/me/assessments')
  if (canTeach()) {
    const rows = await api('/submissions')
    for (const item of rows) {
      if (!drafts[item.id]) drafts[item.id] = { score: '', feedback: '' }
    }
    queue.value = rows
  }
}

onMounted(load)

async function grade(item) {
  const draft = drafts[item.id] || {}
  busy.value = true
  try {
    await api(`/submissions/${item.id}/grade`, {
      method: 'POST',
      body: { score: Number(draft.score), feedback: draft.feedback || '' },
    })
    toast('Graded.')
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
        <p class="kicker">Assessments</p>
        <h1>Assignments</h1>
        <p class="dek">Quizzes mark themselves. Assignments sit here until an instructor scores them.</p>
      </div>
    </header>

    <section v-if="canTeach()" style="margin-bottom: 1.4rem">
      <h3>Grading queue</h3>
      <p v-if="!queue.length" class="empty">Nothing waiting.</p>
      <article v-for="item in queue" :key="item.id" class="panel stack" style="padding: 1rem; margin-top: 0.7rem">
        <div class="spread">
          <strong>{{ item.student_name }} · {{ item.assignment_title }}</strong>
          <span class="pill" :class="item.status === 'graded' ? 'good' : 'warn'">{{ item.status }}</span>
        </div>
        <p class="muted">{{ item.course_title }} · {{ pretty(item.submitted_at) }}</p>
        <p>{{ item.body }}</p>
        <p v-if="item.feedback">{{ item.feedback }}</p>
        <div v-if="item.status !== 'graded' && drafts[item.id]" class="row-2">
          <label class="field">
            <span>Score / {{ item.max_score }}</span>
            <input v-model="drafts[item.id].score" type="number" :max="item.max_score" min="0" />
          </label>
          <label class="field">
            <span>Feedback</span>
            <input v-model="drafts[item.id].feedback" />
          </label>
        </div>
        <button v-if="item.status !== 'graded'" class="btn small" type="button" :disabled="busy" @click="grade(item)">Save grade</button>
      </article>
    </section>

    <section class="cards" style="align-items: start">
      <div>
        <h3>Your quizzes</h3>
        <article v-for="quiz in mine.quizzes" :key="quiz.id" class="panel" style="padding: 0.9rem; margin-top: 0.6rem">
          <div class="spread">
            <div>
              <strong>{{ quiz.title }}</strong>
              <p class="muted">{{ quiz.course_title }}</p>
            </div>
            <span v-if="quiz.last_attempt" class="pill" :class="quiz.last_attempt.passed ? 'good' : 'warn'">{{ quiz.last_attempt.score }}%</span>
            <span v-else class="pill">Not taken</span>
          </div>
          <router-link :to="`/learn/${quiz.slug}`">Open</router-link>
        </article>
        <p v-if="!mine.quizzes.length" class="empty">Enroll in a course to see its quizzes.</p>
      </div>
      <div>
        <h3>Your assignments</h3>
        <article v-for="item in mine.submissions" :key="item.id" class="panel" style="padding: 0.9rem; margin-top: 0.6rem">
          <div class="spread">
            <strong>{{ item.assignment_title }}</strong>
            <span class="pill" :class="item.status === 'graded' ? 'good' : 'warn'">
              {{ item.status }}<template v-if="item.score != null"> · {{ item.score }}/{{ item.max_score }}</template>
            </span>
          </div>
          <p class="muted">{{ item.course_title }}</p>
          <p v-if="item.feedback" style="margin-top: 0.4rem">{{ item.feedback }}</p>
        </article>
        <p v-if="!mine.submissions.length" class="empty">No assignments handed in.</p>
      </div>
    </section>
  </div>
</template>
