<script setup>
import { computed, onMounted, ref } from 'vue'
import { api, session, toast } from '../api'

const items = ref([])
const courses = ref([])
const chapters = ref([])
const filter = ref('all')
const busy = ref(false)
const file = ref(null)
const fileKey = ref(0)
const upload = ref({ title: '', kind: 'document' })
const quiz = ref({
  title: '',
  passing_score: 70,
  questions: [{ prompt: '', options: ['', ''], answer_index: 0, explanation: '' }],
})
const placing = ref(null)
const target = ref({ course_id: '', chapter_id: '' })

const visible = computed(() => (filter.value === 'all' ? items.value : items.value.filter((item) => item.kind === filter.value)))
const labels = { video: 'Video', document: 'Document', quiz: 'Quiz' }

function bytes(size) {
  if (!size) return ''
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`
  return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

async function load() {
  items.value = await api('/library')
  courses.value = await api('/studio/courses')
}

onMounted(load)

function onFile(event) {
  file.value = event.target.files?.[0] || null
  if (file.value && !upload.value.title) upload.value.title = file.value.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ')
}

async function sendFile() {
  if (!file.value) {
    toast('Choose a file.', 'warn')
    return
  }
  const body = new FormData()
  body.append('title', upload.value.title)
  body.append('kind', upload.value.kind)
  body.append('file', file.value)
  busy.value = true
  try {
    await api('/library', { method: 'POST', body, form: true })
    upload.value = { title: '', kind: upload.value.kind }
    file.value = null
    fileKey.value += 1
    toast('Added to the library.')
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function sendQuiz() {
  busy.value = true
  try {
    await api('/library/quizzes', {
      method: 'POST',
      body: {
        title: quiz.value.title,
        passing_score: Number(quiz.value.passing_score),
        questions: quiz.value.questions.map((question) => ({
          ...question,
          options: question.options.map((option) => option.trim()).filter(Boolean),
        })),
      },
    })
    quiz.value = { title: '', passing_score: 70, questions: [{ prompt: '', options: ['', ''], answer_index: 0, explanation: '' }] }
    toast('Quiz added to the library.')
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function remove(item) {
  try {
    await api(`/library/${item.id}`, { method: 'DELETE' })
    toast('Removed from the library.')
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  }
}

async function chooseCourse() {
  chapters.value = []
  target.value.chapter_id = ''
  if (!target.value.course_id) return
  const data = await api(`/studio/courses/${target.value.course_id}`)
  chapters.value = data.chapters
  if (chapters.value[0]) target.value.chapter_id = chapters.value[0].id
}

async function place(item) {
  try {
    const result = await api(`/library/${item.id}/place`, { method: 'POST', body: { chapter_id: Number(target.value.chapter_id) } })
    toast(`Added to ${result.course.title}.`)
    placing.value = null
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Resources</h1>
        <p class="dek">Upload videos, documents, and quizzes here. Place a material into a course when a class needs it.</p>
      </div>
    </header>

    <div class="filters" style="margin-bottom: 16px">
      <button class="chip" :class="{ on: filter === 'all' }" type="button" @click="filter = 'all'">All</button>
      <button class="chip" :class="{ on: filter === 'video' }" type="button" @click="filter = 'video'">Videos</button>
      <button class="chip" :class="{ on: filter === 'document' }" type="button" @click="filter = 'document'">Documents</button>
      <button class="chip" :class="{ on: filter === 'quiz' }" type="button" @click="filter = 'quiz'">Quizzes</button>
    </div>

    <div class="cards" style="align-items: start">
      <form v-if="filter !== 'quiz'" class="panel stack" style="padding: 16px" @submit.prevent="sendFile">
        <h2>Upload a file</h2>
        <label class="field">
          <span>Kind</span>
          <select v-model="upload.kind">
            <option value="document">Document</option>
            <option value="video">Video</option>
          </select>
        </label>
        <label class="field"><span>Title</span><input v-model="upload.title" placeholder="What learners will see" /></label>
        <label class="field">
          <span>{{ upload.kind === 'video' ? 'Video file' : 'Document' }}</span>
          <input :key="`${upload.kind}-${fileKey}`" type="file" :accept="upload.kind === 'video' ? '.mp4,.webm,.mov,.m4v' : '.pdf,.txt,.doc,.docx,.ppt,.pptx'" required @change="onFile" />
        </label>
        <button class="btn" type="submit" :disabled="busy">Add to library</button>
      </form>

      <form v-else class="panel stack" style="padding: 16px" @submit.prevent="sendQuiz">
        <h2>Write a quiz</h2>
        <label class="field"><span>Title</span><input v-model="quiz.title" required /></label>
        <label class="field"><span>Passing score</span><input v-model="quiz.passing_score" type="number" min="1" max="100" /></label>
        <article v-for="(question, qIndex) in quiz.questions" :key="qIndex" class="stack">
          <label class="field"><span>Question {{ qIndex + 1 }}</span><input v-model="question.prompt" required /></label>
          <div v-for="(option, oIndex) in question.options" :key="oIndex" class="choice-row">
            <input type="radio" :name="`lib-${qIndex}`" :checked="question.answer_index === oIndex" @change="question.answer_index = oIndex" :aria-label="`Correct choice ${oIndex + 1}`" />
            <input v-model="question.options[oIndex]" :placeholder="`Choice ${oIndex + 1}`" />
          </div>
          <button class="btn small ghost" type="button" @click="question.options.push('')">Add a choice</button>
        </article>
        <button class="btn small ghost" type="button" @click="quiz.questions.push({ prompt: '', options: ['', ''], answer_index: 0, explanation: '' })">Add a question</button>
        <button class="btn" type="submit" :disabled="busy">Add quiz</button>
      </form>

      <section class="stack">
        <article v-for="item in visible" :key="item.id" class="panel stack" style="padding: 14px">
          <div class="spread">
            <div>
              <p class="kicker">{{ labels[item.kind] }}<template v-if="item.original_name"> · {{ item.original_name }}</template><template v-if="item.question_count"> · {{ item.question_count }} questions</template></p>
              <h3>{{ item.title }}</h3>
              <p class="muted">{{ item.owner_name }}<template v-if="item.size"> · {{ bytes(item.size) }}</template></p>
            </div>
            <button v-if="session.user?.role === 'admin' || session.user?.id === item.owner_id" class="btn small ghost" type="button" @click="remove(item)">Remove</button>
          </div>
          <button v-if="placing !== item.id" class="btn small" type="button" @click="placing = item.id">Add to a course</button>
          <form v-else class="stack" @submit.prevent="place(item)">
            <label class="field">
              <span>Course</span>
              <select v-model="target.course_id" required @change="chooseCourse">
                <option value="" disabled>Choose a course</option>
                <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option>
              </select>
            </label>
            <label class="field">
              <span>Chapter</span>
              <select v-model="target.chapter_id" required>
                <option v-for="chapter in chapters" :key="chapter.id" :value="chapter.id">{{ chapter.title }}</option>
              </select>
            </label>
            <p v-if="target.course_id && !chapters.length" class="muted">Add a chapter in the course studio first.</p>
            <div class="actions">
              <button class="btn small" type="submit" :disabled="!target.chapter_id">Place in chapter</button>
              <button class="btn small ghost" type="button" @click="placing = null">Cancel</button>
            </div>
          </form>
        </article>
        <p v-if="!visible.length" class="empty">Nothing in this part of the library yet.</p>
      </section>
    </div>
  </div>
</template>
