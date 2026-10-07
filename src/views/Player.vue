<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, session, toast } from '../api'
import { paragraphs } from '../format'

const route = useRoute()
const router = useRouter()
const data = ref(null)
const error = ref('')
const answers = ref({})
const notes = ref('')
const comment = ref('')
const assignmentBody = ref('')
const busy = ref(false)
const kindLabel = { text: 'Reading', video: 'Video', quiz: 'Quiz', assignment: 'Assignment', document: 'Document' }

const lesson = computed(() => data.value?.lesson)

async function load() {
  error.value = ''
  try {
    data.value = await api(`/learn/${route.params.slug}/${route.params.chapter}/${route.params.lesson}`)
    notes.value = data.value.notes || ''
    const assignment = data.value.lesson.blocks.find((block) => block.type === 'assignment')
    assignmentBody.value = assignment?.assignment?.submission?.body || ''
    answers.value = {}
  } catch (err) {
    error.value = err.message
    data.value = null
  }
}

watch(() => [route.params.slug, route.params.chapter, route.params.lesson], load, { immediate: true })

function mediaUrl(url) {
  if (!url || !session.token || !url.startsWith('/api/library/')) return url
  const join = url.includes('?') ? '&' : '?'
  return `${url}${join}token=${encodeURIComponent(session.token)}`
}

function open(chapter, lessonNumber) {
  router.push(`/courses/${route.params.slug}/learn/${chapter}-${lessonNumber}`)
}

async function complete() {
  busy.value = true
  try {
    const result = await api(`/lessons/${lesson.value.id}/complete`, { method: 'POST' })
    data.value.progress = result.progress
    data.value.lesson.completed = true
    toast('Progress saved.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function submitQuiz(quiz) {
  busy.value = true
  try {
    const result = await api(`/quizzes/${quiz.id}/submit`, { method: 'POST', body: { answers: answers.value } })
    toast(result.passed ? `Passed · ${result.score}%` : `${result.score}% · passing mark ${result.passing_score}%`, result.passed ? 'ok' : 'warn')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function submitAssignment(assignment) {
  busy.value = true
  try {
    await api(`/assignments/${assignment.id}/submit`, { method: 'POST', body: { body: assignmentBody.value } })
    toast('Assignment submitted.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function saveNotes() {
  if (!session.user) return
  try {
    await api(`/notes/${lesson.value.id}`, { method: 'POST', body: { body: notes.value } })
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function discuss() {
  if (!session.user) return router.push({ path: '/login', query: { next: route.fullPath } })
  try {
    await api('/discussions', { method: 'POST', body: { lesson_id: lesson.value.id, body: comment.value } })
    comment.value = ''
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}

function choiceClass(quiz, question, index) {
  const attempt = quiz.last_attempt
  if (!attempt) return { on: answers.value[question.id] === index }
  const chosen = attempt.answers?.[String(question.id)]?.chosen === index
  return { right: index === question.answer_index, wrong: chosen && index !== question.answer_index, on: chosen }
}
</script>

<template>
  <p v-if="error" class="page empty">{{ error }} <router-link :to="`/courses/${route.params.slug}`">Back to course</router-link></p>
  <div v-else-if="data && lesson" class="lesson-app">
    <aside class="outline">
      <router-link :to="`/courses/${data.course.slug}`"><strong>{{ data.course.title }}</strong></router-link>
      <p class="muted" style="margin: 8px 0">{{ data.progress }}% complete</p>
      <div class="bar"><span :style="{ width: data.progress + '%' }"></span></div>
      <template v-for="chapter in data.outline" :key="chapter.id">
        <p class="chapter-label">{{ chapter.number }}. {{ chapter.title }}</p>
        <button
          v-for="item in chapter.lessons"
          :key="item.id"
          type="button"
          :class="{ on: item.id === lesson.id }"
          @click="open(chapter.number, item.number)"
        >
          <span>{{ chapter.number }}.{{ item.number }} {{ item.title }}</span>
          <span class="muted">{{ item.completed ? 'Done' : kindLabel[item.kind] }}</span>
        </button>
      </template>
    </aside>
    <article class="stage">
      <p class="crumbs">
        <router-link to="/courses">Courses</router-link><span>/</span>
        <router-link :to="`/courses/${data.course.slug}`">{{ data.course.title }}</router-link><span>/</span>
        <span>{{ lesson.title }}</span>
      </p>
      <p class="kicker">{{ data.chapter_number }}.{{ data.lesson_number }} · {{ lesson.minutes }} min</p>
      <h1>{{ lesson.title }}</h1>

      <section v-for="(block, index) in lesson.blocks" :key="index" class="block">
        <div v-if="block.type === 'markdown'" class="prose">
          <p v-for="(paragraph, p) in paragraphs(block.text)" :key="p">{{ paragraph }}</p>
        </div>
        <div v-else-if="block.type === 'video'" class="film">
          <video v-if="block.url" :src="mediaUrl(block.url)" controls playsinline></video>
        </div>
        <div v-else-if="block.type === 'document'" class="panel" style="padding: 14px">
          <p>{{ block.name }}</p>
          <a class="btn" :href="mediaUrl(block.url)" target="_blank" rel="noopener">Open document</a>
        </div>
        <div v-else-if="block.type === 'quiz' && block.quiz">
          <h2>{{ block.quiz.title }}</h2>
          <p class="muted">Passing {{ block.quiz.passing_score }}%<template v-if="block.quiz.max_attempts"> · {{ block.quiz.max_attempts }} attempts</template><template v-if="block.quiz.duration_minutes"> · {{ block.quiz.duration_minutes }} min</template></p>
          <p v-if="block.quiz.last_attempt"><span class="pill" :class="block.quiz.last_attempt.passed ? 'good' : 'warn'">{{ block.quiz.last_attempt.passed ? 'Passed' : 'Not passed' }} · {{ block.quiz.last_attempt.score }}%</span></p>
          <div v-for="question in block.quiz.questions" :key="question.id" class="question">
            <strong>{{ question.prompt }}</strong>
            <button v-for="(option, optionIndex) in question.options" :key="option" type="button" class="choice" :class="choiceClass(block.quiz, question, optionIndex)" @click="answers[question.id] = optionIndex">
              <span>{{ String.fromCharCode(65 + optionIndex) }}</span><span>{{ option }}</span>
            </button>
            <p v-if="question.explanation" class="muted">{{ question.explanation }}</p>
          </div>
          <button class="btn" type="button" :disabled="busy" @click="submitQuiz(block.quiz)">Submit quiz</button>
        </div>
        <form v-else-if="block.type === 'assignment' && block.assignment" class="stack" @submit.prevent="submitAssignment(block.assignment)">
          <h2>{{ block.assignment.title }}</h2>
          <p>{{ block.assignment.instructions }}</p>
          <p v-if="block.assignment.submission">
            <span class="pill" :class="block.assignment.submission.status === 'graded' ? 'good' : 'warn'">
              {{ block.assignment.submission.status }}
              <template v-if="block.assignment.submission.score != null"> · {{ block.assignment.submission.score }}/{{ block.assignment.max_score }}</template>
            </span>
          </p>
          <p v-if="block.assignment.submission?.feedback">{{ block.assignment.submission.feedback }}</p>
          <textarea v-model="assignmentBody" :disabled="block.assignment.submission?.status === 'graded'" aria-label="Assignment"></textarea>
          <button class="btn" :disabled="busy || block.assignment.submission?.status === 'graded'">Submit assignment</button>
        </form>
      </section>

      <div class="actions" style="margin-top: 18px">
        <button class="btn" type="button" :disabled="busy || lesson.completed" @click="complete">{{ lesson.completed ? 'Completed' : 'Mark as complete' }}</button>
        <button v-if="data.prev" class="btn ghost" type="button" @click="open(data.prev.chapter, data.prev.lesson)">Previous</button>
        <button v-if="data.next" class="btn ghost" type="button" @click="open(data.next.chapter, data.next.lesson)">Next</button>
      </div>

      <section v-if="data.progress === 100" class="certificate">
        <p class="kicker">Course complete</p>
        <h2>{{ session.user?.full_name }}</h2>
        <p>finished {{ data.course.title }}.</p>
        <router-link v-if="data.course.enable_certification" :to="`/courses/${data.course.slug}/certification`">Request certificate</router-link>
      </section>

      <section v-if="session.user" style="margin-top: 28px">
        <h2>Notes</h2>
        <textarea v-model="notes" aria-label="Lesson notes" @blur="saveNotes"></textarea>
      </section>

      <section style="margin-top: 28px">
        <h2>Discussions</h2>
        <article v-for="item in data.discussions" :key="item.id" style="padding: 10px 0; border-bottom: 1px solid var(--line)">
          <strong>{{ item.full_name }}</strong>
          <p>{{ item.body }}</p>
        </article>
        <form class="stack" style="margin-top: 10px" @submit.prevent="discuss">
          <textarea v-model="comment" placeholder="Ask a question about this lesson" aria-label="Comment"></textarea>
          <button class="btn small" type="submit">Comment</button>
        </form>
      </section>
    </article>
  </div>
  <p v-else class="page empty">Opening lesson…</p>
</template>
