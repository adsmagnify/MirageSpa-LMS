<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, toast } from '../api'

const route = useRoute()
const quiz = ref(null)
const answers = ref({})
const busy = ref(false)
const seconds = ref(0)
let timer

onUnmounted(() => clearInterval(timer))

onMounted(async () => {
  quiz.value = await api(`/quizzes/${route.params.id}`)
  if (quiz.value.duration_minutes) {
    seconds.value = quiz.value.duration_minutes * 60
    timer = setInterval(() => {
      seconds.value -= 1
      if (seconds.value <= 0) {
        clearInterval(timer)
        submit()
      }
    }, 1000)
  }
})

async function submit() {
  if (busy.value || !quiz.value) return
  busy.value = true
  clearInterval(timer)
  try {
    quiz.value = await api(`/quizzes/${quiz.value.id}/submit`, { method: 'POST', body: { answers: answers.value } })
    toast(quiz.value.passed ? `Passed · ${quiz.value.score}%` : `${quiz.value.score}%`, quiz.value.passed ? 'ok' : 'warn')
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

function clock() {
  const m = Math.floor(seconds.value / 60)
  const s = String(Math.max(seconds.value, 0) % 60).padStart(2, '0')
  return `${m}:${s}`
}
</script>

<template>
  <div class="page" v-if="quiz">
    <p class="crumbs"><router-link to="/quizzes">Quizzes</router-link><span>/</span><span>{{ quiz.title }}</span></p>
    <div class="spread">
      <h1>{{ quiz.title }}</h1>
      <span v-if="quiz.duration_minutes" class="pill">{{ clock() }}</span>
    </div>
    <p class="dek">Passing mark {{ quiz.passing_score }}%.</p>
    <p v-if="quiz.last_attempt"><span class="pill" :class="quiz.last_attempt.passed ? 'good' : 'warn'">Last attempt {{ quiz.last_attempt.score }}%</span></p>
    <div v-for="question in quiz.questions" :key="question.id" class="question">
      <strong>{{ question.prompt }}</strong>
      <button v-for="(option, index) in question.options" :key="option" class="choice" type="button" :class="{ on: answers[question.id] === index }" @click="answers[question.id] = index">
        {{ option }}
      </button>
      <p v-if="question.explanation" class="muted">{{ question.explanation }}</p>
    </div>
    <button class="btn" type="button" :disabled="busy" @click="submit">Submit</button>
  </div>
</template>
