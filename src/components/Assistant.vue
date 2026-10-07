<script setup>
import { ref } from 'vue'

const question = ref('')
const messages = ref([
  {
    role: 'assistant',
    text: 'Ask how to copy a course, grade work, use groups, upload a resource, or restore something from trash.',
  },
])

const guides = [
  { keys: ['copy', 'parent', 'duplicate'], text: 'On Home or Courses, open the row menu and choose Copy course. The copy is a draft with the same chapters, lessons, and quizzes. Learners stay on the original until you assign them.' },
  { keys: ['group'], text: 'Open Groups, create a group, and add people by email. Messages posted there also show in the News widget on Home.' },
  { keys: ['trash', 'delete', 'restore'], text: 'Move a course to Trash from its row menu. Open the trash icon in the top bar to restore it, or remove it for good. Resources you remove go to the same trash.' },
  { keys: ['resource', 'upload', 'video', 'library', 'document'], text: 'Resources holds videos, documents, and quizzes. Upload a file or write a quiz, then place it into a chapter. There is no file size cap.' },
  { keys: ['grade', 'score', 'assignment'], text: 'To score counts assignments that are still waiting for a grade. Open Assignments, enter the score, and the count drops.' },
  { keys: ['assign', 'learner', 'enroll', 'class'], text: 'Open the course and assign a learner by email. Learners only see classes assigned to them. You can deactivate someone on that course without deleting their work.' },
  { keys: ['report', 'progress', 'complete'], text: 'Reports lists each course with learners, how many finished every lesson, average progress, and work still to score.' },
  { keys: ['calendar', 'live'], text: 'The calendar collects live classes, batches, and evaluation times. Open the calendar icon in the top bar for the full month.' },
  { keys: ['school', 'user', 'role'], text: 'Administrators create accounts and assign Administrator, Instructor, or Learner. Schools shows the school name, instructors, and how many learners and courses you have.' },
]

function ask() {
  const text = question.value.trim()
  if (!text) return
  messages.value.push({ role: 'you', text })
  const lower = text.toLowerCase()
  const match = guides.find((guide) => guide.keys.some((key) => lower.includes(key)))
  messages.value.push({
    role: 'assistant',
    text: match ? match.text : 'I can walk through courses, copying a class, groups, resources, grading, reports, the calendar, and trash. Name one of those.',
  })
  question.value = ''
}
</script>

<template>
  <div class="assistant-log">
    <p v-for="(message, index) in messages" :key="index" :class="message.role">{{ message.text }}</p>
  </div>
  <form class="assistant-ask" @submit.prevent="ask">
    <input v-model="question" placeholder="Ask about the school desk" />
    <button class="btn small" type="submit">Ask</button>
  </form>
</template>
