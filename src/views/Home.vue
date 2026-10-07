<script setup>
import { onMounted, ref } from 'vue'
import CourseCard from '../components/CourseCard.vue'
import { api, session } from '../api'

const courses = ref([])
const jobs = ref([])
const features = [
  ['Courses', 'Publish a course and share the link. Drafts stay in the studio until you open them.'],
  ['Lessons', 'Build a chapter from reading, film, a quiz, and an assignment.'],
  ['Batches', 'Seat a cohort on a course and see who is actually moving.'],
  ['Live rooms', 'Schedule a class. Zoom opens when the account is connected.'],
  ['Assessments', 'Quizzes score themselves. Assignments wait for a human grade.'],
  ['Evaluations', 'Publish hours. A learner is booked into the next open call.'],
  ['Insights', 'Signups and enrollments, counted as they happen.'],
  ['Job board', 'Offer the next role to the people already in the room.'],
]

onMounted(async () => {
  try {
    ;[courses.value, jobs.value] = await Promise.all([api('/courses'), api('/jobs')])
  } catch {
    courses.value = []
    jobs.value = []
  }
})
</script>

<template>
  <div class="marketing">
    <header class="top">
      <router-link to="/" class="brand" style="color: inherit">
        <em>Mirage</em>
        <small style="color: var(--brass)">Academy</small>
      </router-link>
      <nav>
        <router-link to="/courses">Courses</router-link>
        <router-link to="/batches">Batches</router-link>
        <router-link to="/jobs">Jobs</router-link>
        <router-link v-if="session.user" to="/learn" class="btn small">Continue</router-link>
        <router-link v-else to="/enter" class="btn small">Enter</router-link>
      </nav>
    </header>

    <section class="hero">
      <div>
        <p class="kicker">A learning system for a working floor</p>
        <h1>Teach the room. Keep the record.</h1>
        <p class="lede">
          Mirage Academy is where a studio publishes courses, runs a cohort, opens a live room, grades the work, and sees who arrived. The shelves are stocked with spa training. The system will hold any subject you put in the studio.
        </p>
        <div class="actions">
          <router-link class="btn" to="/courses">Browse the courses</router-link>
          <router-link class="btn ghost" to="/studio">Open the studio</router-link>
        </div>
      </div>
      <ol class="index">
        <li v-for="(feature, index) in features" :key="feature[0]">
          <span>{{ String(index + 1).padStart(2, '0') }}</span>
          <div>
            <strong>{{ feature[0] }}</strong>
            <p>{{ feature[1] }}</p>
          </div>
        </li>
      </ol>
    </section>

    <section class="section">
      <div class="page-head">
        <div>
          <p class="kicker">Open courses</p>
          <h2>What the floor is studying</h2>
        </div>
        <router-link to="/courses">All courses</router-link>
      </div>
      <div class="cards">
        <CourseCard v-for="course in courses" :key="course.id" :course="course" />
      </div>
    </section>

    <section class="band">
      <div>
        <p class="kicker" style="color: #e0c48a">Cohorts and calls</p>
        <h2>Progress is a batch, not a guess.</h2>
        <p style="margin-top: 0.7rem; color: #e7ded2">Seat people together, open a live room, and let evaluation hours fill themselves.</p>
      </div>
      <div>
        <router-link class="quiet" to="/batches"><strong>Batches</strong><br /><span>Rosters, progress, and who went quiet.</span></router-link>
        <router-link class="quiet" to="/live"><strong>Live rooms</strong><br /><span>Zoom when it is connected. Attendance either way.</span></router-link>
        <router-link class="quiet" to="/evaluations"><strong>Evaluation calls</strong><br /><span>The next open half hour, booked without a thread.</span></router-link>
      </div>
    </section>

    <section class="section">
      <div class="page-head">
        <div>
          <p class="kicker">Job board</p>
          <h2>Work for people already learning</h2>
        </div>
        <router-link to="/jobs">The board</router-link>
      </div>
      <div class="classified">
        <router-link v-for="job in jobs.slice(0, 4)" :key="job.id" to="/jobs">
          <strong>{{ job.title }}</strong>
          <span>{{ job.company }}</span>
          <span class="muted">{{ job.location }}</span>
        </router-link>
      </div>
    </section>
  </div>
</template>
