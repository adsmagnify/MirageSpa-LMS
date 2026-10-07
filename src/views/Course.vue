<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, canTeach, session, toast } from '../api'

const route = useRoute()
const router = useRouter()
const data = ref(null)
const error = ref('')
const busy = ref(false)
const review = ref({ rating: 5, body: '' })
const learnerEmail = ref('')
const roster = ref([])
const kindLabel = { text: 'Reading', video: 'Video', quiz: 'Quiz', assignment: 'Assignment' }

async function load() {
  try {
    data.value = await api(`/courses/${route.params.slug}/page`)
    if (canTeach()) {
      try {
        roster.value = await api(`/courses/${data.value.course.id}/roster`)
      } catch {
        roster.value = []
      }
    }
  } catch (err) {
    error.value = err.message
  }
}
onMounted(load)

async function assignLearner() {
  busy.value = true
  try {
    await api(`/courses/${data.value.course.id}/assign`, { method: 'POST', body: { email: learnerEmail.value } })
    toast('Class assigned.')
    learnerEmail.value = ''
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function toggleLearner(person) {
  try {
    await api(`/courses/${data.value.course.id}/learners/${person.id}`, { method: 'POST', body: { active: !person.active } })
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
async function share() {
  const url = `${location.origin}/courses/${data.value.course.slug}`
  try {
    await navigator.clipboard.writeText(url)
    toast('Course link copied.')
  } catch {
    toast(url)
  }
}

async function sendReview() {
  if (!session.user) return router.push({ path: '/login', query: { next: route.fullPath } })
  try {
    await api(`/courses/${data.value.course.id}/reviews`, { method: 'POST', body: review.value })
    toast('Review saved.')
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page" v-if="data">
    <p class="crumbs"><router-link to="/courses">Courses</router-link><span>/</span><span>{{ data.course.title }}</span></p>
    <div class="split">
      <div>
        <div class="swatch" :data-cat="data.course.category" style="height: 180px; border-radius: 10px"></div>
        <header style="margin: 16px 0">
          <p class="kicker">{{ data.course.category }} · {{ data.course.level }}</p>
          <h1>{{ data.course.title }}</h1>
          <p class="dek">{{ data.course.summary }}</p>
          <p class="muted" style="margin-top: 8px">
            {{ data.course.instructor_name }}
            <template v-if="data.rating"> · {{ data.rating }} / 5</template>
            · {{ data.course.learners }} enrolled
          </p>
        </header>
        <div class="prose">
          <p v-for="(paragraph, index) in data.course.description.split(/\n\s*\n/)" :key="index">{{ paragraph }}</p>
        </div>
        <h2 style="margin: 22px 0 8px">Curriculum</h2>
        <article v-for="chapter in data.outline" :key="chapter.id" class="panel" style="padding: 12px 14px; margin-bottom: 8px">
          <strong>{{ chapter.number }}. {{ chapter.title }}</strong>
          <router-link
            v-for="lesson in chapter.lessons"
            :key="lesson.id"
            class="spread"
            style="padding: 8px 0; border-top: 1px solid var(--line); margin-top: 8px"
            :to="`/courses/${data.course.slug}/learn/${chapter.number}-${lesson.number}`"
          >
            <span>{{ chapter.number }}.{{ lesson.number }} {{ lesson.title }}</span>
            <span class="muted">{{ kindLabel[lesson.kind] }}<template v-if="lesson.include_in_preview"> · Preview</template><template v-if="lesson.completed"> · Done</template></span>
          </router-link>
        </article>
        <h2 style="margin: 22px 0 8px">Reviews</h2>
        <article v-for="item in data.reviews" :key="item.created_at + item.username" class="panel" style="padding: 12px; margin-bottom: 8px">
          <strong>{{ item.full_name }}</strong> <span class="pill">{{ item.rating }}/5</span>
          <p style="margin-top: 6px">{{ item.body }}</p>
        </article>
        <form v-if="data.enrolled" class="stack" style="margin-top: 8px" @submit.prevent="sendReview">
          <label class="field"><span>Your rating</span>
            <select v-model="review.rating"><option :value="5">5</option><option :value="4">4</option><option :value="3">3</option><option :value="2">2</option><option :value="1">1</option></select>
          </label>
          <label class="field"><span>Review</span><textarea v-model="review.body"></textarea></label>
          <button class="btn small" type="submit">Save review</button>
        </form>
      </div>
      <aside class="stack">
        <article class="panel stack" style="padding: 14px">
          <div v-if="data.enrolled" class="bar"><span :style="{ width: data.progress + '%' }"></span></div>
          <p v-if="data.enrolled" class="muted">{{ data.progress }}% complete</p>
          <router-link v-if="data.enrolled" class="btn" :to="`/courses/${data.course.slug}/learn/1-1`">
            {{ data.progress ? 'Continue' : 'Start lesson' }}
          </router-link>
          <form v-else-if="canTeach()" class="stack" @submit.prevent="assignLearner">
            <label class="field"><span>Assign a learner</span><input v-model="learnerEmail" type="email" required placeholder="learner@email" /></label>
            <button class="btn" type="submit" :disabled="busy">Assign class</button>
          </form>
          <div v-if="canTeach() && roster.length" class="stack">
            <strong>Roster</strong>
            <p v-for="person in roster" :key="person.id" class="spread">
              <span>{{ person.full_name }} <span v-if="!person.active" class="muted">Deactivated</span></span>
              <button class="btn small ghost" type="button" @click="toggleLearner(person)">{{ person.active ? 'Deactivate' : 'Activate' }}</button>
            </p>
          </div>
          <p v-else class="muted">An instructor assigns this class before you can open it.</p>
          <button class="btn ghost" type="button" @click="share">Share</button>
          <router-link v-if="data.course.enable_certification" class="btn ghost" :to="`/courses/${data.course.slug}/certification`">Certification</router-link>
          <router-link v-if="canTeach() && (session.user.role === 'admin' || session.user.id === data.course.instructor_id)" class="btn ghost" :to="`/studio/${data.course.id}`">Edit course</router-link>
          <p class="muted">{{ data.course.instructor_name }} · {{ data.course.instructor_headline }}</p>
          <p v-if="data.certificate" class="pill good">Certified {{ data.certificate.issue_date.slice(0, 10) }}</p>
        </article>
      </aside>
    </div>
  </div>
  <div v-else class="page"><p class="empty">{{ error || 'Loading course…' }}</p></div>
</template>
