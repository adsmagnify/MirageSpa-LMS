<script setup>
import { computed, ref, watch } from 'vue'
import { api, toast } from '../api'

const props = defineProps({
  rows: { type: Array, default: () => [] },
})
const emit = defineEmits(['changed'])
const q = ref('')
const page = ref(1)
const openMenu = ref(null)
const pageSize = 6

const filtered = computed(() => {
  const needle = q.value.trim().toLowerCase()
  if (!needle) return props.rows
  return props.rows.filter((row) => `${row.title} ${row.category} ${row.instructor_name}`.toLowerCase().includes(needle))
})
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)))
const visible = computed(() => {
  const current = Math.min(page.value, pageCount.value)
  return filtered.value.slice((current - 1) * pageSize, current * pageSize)
})

watch(q, () => {
  page.value = 1
})

function dash(value) {
  return value ? value : '—'
}

function when(row) {
  const fmt = (value) => {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
  }
  const start = fmt(row.starts_on || row.created_at)
  const end = fmt(row.ends_on || row.created_at)
  if (!start) return ''
  return start === end ? start : `${start} – ${end}`
}

function letter(title) {
  return (title || '?').trim().charAt(0).toUpperCase()
}

function tone(title) {
  const tones = ['#dbeafe', '#fce7f3', '#dcfce7', '#fef3c7', '#e0e7ff', '#ffedd5']
  let n = 0
  for (const ch of title || '') n += ch.charCodeAt(0)
  return tones[n % tones.length]
}

async function copy(row) {
  openMenu.value = null
  try {
    const result = await api(`/courses/${row.id}/copy`, { method: 'POST' })
    toast(`${result.course.title} is ready as a draft.`)
    emit('changed')
  } catch (error) {
    toast(error.message, 'warn')
  }
}

async function trash(row) {
  openMenu.value = null
  try {
    await api(`/courses/${row.id}/trash`, { method: 'POST' })
    toast('Moved to trash.')
    emit('changed')
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div class="course-table">
    <div class="table-head">
      <span>#</span>
      <span class="name-head">Name <input v-model="q" aria-label="Filter courses" placeholder="Search" /></span>
      <span>To score</span>
      <span>Learners</span>
      <span>Completed</span>
      <span>Deactivated</span>
    </div>
    <article v-for="(row, index) in visible" :key="row.id" class="course-row">
      <span class="row-no">{{ (Math.min(page, pageCount) - 1) * pageSize + index + 1 }}</span>
      <router-link class="course-name" :to="`/courses/${row.slug}`">
        <span class="thumb" :style="{ background: tone(row.title) }">{{ letter(row.title) }}</span>
        <span>
          <strong>{{ row.title }}</strong>
          <small>{{ when(row) }}<template v-if="row.product_code"> · {{ row.product_code }}</template></small>
          <small v-if="row.category">{{ row.category }}<template v-if="row.instructor_name"> · {{ row.instructor_name }}</template><template v-if="row.progress != null"> · {{ row.progress }}%</template></small>
        </span>
      </router-link>
      <router-link class="metric" data-label="To score" to="/assignments">{{ dash(row.to_score) }}</router-link>
      <span class="metric" data-label="Learners">{{ dash(row.learners) }}</span>
      <span class="metric" data-label="Completed">{{ dash(row.completed) }}</span>
      <span class="metric" data-label="Deactivated">{{ dash(row.deactivated) }}</span>
      <div v-if="row.can_edit" class="row-menu">
        <button type="button" aria-label="Course actions" @click="openMenu = openMenu === row.id ? null : row.id">⋯</button>
        <div v-if="openMenu === row.id" class="pop menu">
          <button type="button" @click="copy(row)">Copy course</button>
          <button type="button" @click="trash(row)">Move to trash</button>
        </div>
      </div>
    </article>
    <p v-if="!filtered.length" class="empty">No courses in this list yet.</p>
    <div v-if="pageCount > 1" class="pager">
      <button v-for="n in pageCount" :key="n" type="button" :class="{ on: n === Math.min(page, pageCount) }" @click="page = n">{{ n }}</button>
      <button v-if="page < pageCount" type="button" @click="page += 1">Next</button>
    </div>
  </div>
</template>
