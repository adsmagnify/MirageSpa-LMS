<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, toast } from '../api'

const route = useRoute()
const data = ref(null)
const error = ref('')
const busy = ref(false)
const meta = reactive({ title: '', summary: '', description: '', category: '', level: '' })
const chapterTitle = ref('')
const lesson = reactive({
  chapter_id: '',
  title: '',
  kind: 'text',
  minutes: 8,
  body: '',
  video_url: '',
  passing_score: 70,
  instructions: '',
  max_score: 100,
  questions: [{ prompt: '', options: ['', ''], answer_index: 0, explanation: '' }],
})

async function load() {
  try {
    data.value = await api(`/studio/courses/${route.params.id}`)
    Object.assign(meta, data.value.course)
    if (!lesson.chapter_id && data.value.chapters[0]) lesson.chapter_id = data.value.chapters[0].id
  } catch (err) {
    error.value = err.message
  }
}
onMounted(load)

async function saveMeta() {
  busy.value = true
  try {
    data.value.course = await api(`/studio/courses/${route.params.id}`, { method: 'PATCH', body: { ...meta } })
    toast('Course saved.')
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function publish(status) {
  try {
    data.value.course = await api(`/studio/courses/${route.params.id}`, { method: 'PATCH', body: { status } })
    toast(status === 'published' ? 'Published. The link is live.' : 'Moved back to draft.')
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function share() {
  if (data.value.course.status !== 'published') {
    toast('Publish the course before sharing.', 'warn')
    return
  }
  const url = `${location.origin}/courses/${data.value.course.slug}`
  try {
    await navigator.clipboard.writeText(url)
    toast('Link copied.')
  } catch {
    toast(url)
  }
}

async function addChapter() {
  try {
    await api('/studio/chapters', { method: 'POST', body: { course_id: Number(route.params.id), title: chapterTitle.value } })
    chapterTitle.value = ''
    toast('Chapter added.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function addLesson() {
  const body = {
    chapter_id: Number(lesson.chapter_id),
    title: lesson.title,
    kind: lesson.kind,
    minutes: Number(lesson.minutes),
    body: lesson.body,
    video_url: lesson.video_url,
  }
  if (lesson.kind === 'quiz') {
    body.quiz = {
      title: lesson.title,
      passing_score: Number(lesson.passing_score),
      questions: lesson.questions.map((question) => ({
        ...question,
        options: question.options.map((option) => option.trim()).filter(Boolean),
      })),
    }
  }
  if (lesson.kind === 'assignment') {
    body.assignment = { title: lesson.title, instructions: lesson.instructions || lesson.body, max_score: Number(lesson.max_score) }
  }
  busy.value = true
  try {
    await api('/studio/lessons', { method: 'POST', body })
    lesson.title = ''
    lesson.body = ''
    toast('Lesson added.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page" v-if="data">
    <header class="page-head">
      <div>
        <p class="kicker">Studio · {{ data.course.status }}</p>
        <h2>{{ data.course.title }}</h2>
      </div>
      <div class="actions">
        <router-link class="btn ghost" :to="`/learn/${data.course.slug}`">Preview</router-link>
        <button class="btn ghost" type="button" @click="share">Share</button>
        <button v-if="data.course.status !== 'published'" class="btn" type="button" @click="publish('published')">Publish</button>
        <button v-else class="btn ghost" type="button" @click="publish('draft')">Unpublish</button>
      </div>
    </header>

    <div class="cards" style="align-items: start">
      <form class="panel stack" style="padding: 1rem" @submit.prevent="saveMeta">
        <h3>The course</h3>
        <label class="field"><span>Title</span><input v-model="meta.title" required /></label>
        <label class="field"><span>Summary</span><input v-model="meta.summary" /></label>
        <label class="field"><span>Description</span><textarea v-model="meta.description"></textarea></label>
        <div class="row-2">
          <label class="field"><span>Category</span><input v-model="meta.category" /></label>
          <label class="field"><span>Level</span><input v-model="meta.level" /></label>
        </div>
        <button class="btn small" type="submit" :disabled="busy">Save</button>
      </form>

      <div class="stack">
        <article class="panel" style="padding: 1rem">
          <h3>Outline</h3>
          <div v-for="chapter in data.chapters" :key="chapter.id" style="margin-top: 0.7rem">
            <strong>{{ chapter.title }}</strong>
            <p v-for="item in chapter.lessons" :key="item.id" class="spread muted">
              <span>{{ item.title }}</span><span>{{ item.kind }}</span>
            </p>
            <p v-if="!chapter.lessons.length" class="muted">No lessons yet.</p>
          </div>
          <p v-if="!data.chapters.length" class="empty">Add a chapter before the lessons.</p>
          <form class="spread" style="margin-top: 0.8rem" @submit.prevent="addChapter">
            <input v-model="chapterTitle" placeholder="Chapter title" aria-label="Chapter title" required />
            <button class="btn small" type="submit">Add</button>
          </form>
        </article>
      </div>
    </div>

    <form class="panel stack" style="padding: 1rem; margin-top: 1rem" @submit.prevent="addLesson">
      <h3>Add a lesson</h3>
      <div class="row-3">
        <label class="field">
          <span>Chapter</span>
          <select v-model="lesson.chapter_id" required>
            <option v-for="chapter in data.chapters" :key="chapter.id" :value="chapter.id">{{ chapter.title }}</option>
          </select>
        </label>
        <label class="field">
          <span>Type</span>
          <select v-model="lesson.kind">
            <option value="text">Reading</option>
            <option value="video">Film</option>
            <option value="quiz">Quiz</option>
            <option value="assignment">Assignment</option>
          </select>
        </label>
        <label class="field"><span>Minutes</span><input v-model="lesson.minutes" type="number" min="1" /></label>
      </div>
      <label class="field"><span>Title</span><input v-model="lesson.title" required /></label>
      <label class="field"><span>Body</span><textarea v-model="lesson.body"></textarea></label>
      <label v-if="lesson.kind === 'video'" class="field"><span>Video URL</span><input v-model="lesson.video_url" placeholder="https://" /></label>
      <template v-if="lesson.kind === 'quiz'">
        <label class="field"><span>Passing score</span><input v-model="lesson.passing_score" type="number" min="1" max="100" /></label>
        <article v-for="(question, qIndex) in lesson.questions" :key="qIndex" class="stack">
          <label class="field"><span>Question {{ qIndex + 1 }}</span><input v-model="question.prompt" required /></label>
          <div v-for="(option, oIndex) in question.options" :key="oIndex" class="choice-row">
            <input type="radio" :name="`answer-${qIndex}`" :checked="question.answer_index === oIndex" @change="question.answer_index = oIndex" :aria-label="`Correct choice ${oIndex + 1}`" />
            <input v-model="question.options[oIndex]" :placeholder="`Choice ${oIndex + 1}`" />
          </div>
          <button class="btn small ghost" type="button" @click="question.options.push('')">Add a choice</button>
          <label class="field"><span>Why this answer</span><input v-model="question.explanation" /></label>
        </article>
        <button class="btn small ghost" type="button" @click="lesson.questions.push({ prompt: '', options: ['', ''], answer_index: 0, explanation: '' })">Add a question</button>
      </template>
      <template v-if="lesson.kind === 'assignment'">
        <label class="field"><span>Instructions</span><textarea v-model="lesson.instructions"></textarea></label>
        <label class="field"><span>Max score</span><input v-model="lesson.max_score" type="number" min="1" /></label>
      </template>
      <button class="btn" type="submit" :disabled="busy || !data.chapters.length">Add lesson</button>
    </form>
  </div>
  <div v-else class="page"><p class="empty">{{ error || 'Opening the studio…' }}</p></div>
</template>
