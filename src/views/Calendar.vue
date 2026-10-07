<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'

const data = ref(null)
const cursor = ref(new Date())

onMounted(async () => {
  data.value = await api('/calendar')
})

const label = computed(() => cursor.value.toLocaleString(undefined, { month: 'long', year: 'numeric' }))
const cells = computed(() => {
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const first = new Date(year, month, 1).getDay()
  const count = new Date(year, month + 1, 0).getDate()
  const days = Array.from({ length: first }, () => null)
  for (let day = 1; day <= count; day += 1) days.push(day)
  return days
})

function eventsOn(day) {
  if (!data.value || !day) return []
  const year = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const key = new Date(year, month, day).toISOString().slice(0, 10)
  const items = []
  for (const item of data.value.live) if ((item.starts_at || '').slice(0, 10) === key) items.push(item.title)
  for (const item of data.value.batches) if ((item.start_date || '').slice(0, 10) === key) items.push(item.title)
  for (const item of data.value.evaluations) if ((item.starts_at || '').slice(0, 10) === key) items.push('Evaluation')
  return items
}

function shift(amount) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + amount, 1)
}
</script>

<template>
  <div class="page">
    <header class="spread">
      <h1>Calendar</h1>
      <div class="actions">
        <button class="btn small ghost" type="button" @click="shift(-1)">Previous</button>
        <strong>{{ label }}</strong>
        <button class="btn small ghost" type="button" @click="shift(1)">Next</button>
      </div>
    </header>
    <section class="card month">
      <div class="month-grid">
        <span v-for="name in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="name" class="dow">{{ name }}</span>
        <div v-for="(day, index) in cells" :key="index" class="month-day">
          <strong>{{ day || '' }}</strong>
          <small v-for="item in eventsOn(day)" :key="item">{{ item }}</small>
        </div>
      </div>
    </section>
    <section v-if="data" class="card stack" style="margin-top: 16px">
      <h2>Coming up</h2>
      <p v-for="item in data.live" :key="'l' + item.id"><strong>{{ item.title }}</strong> <span class="muted">{{ item.starts_at?.replace('T', ' ').slice(0, 16) }}</span></p>
      <p v-for="item in data.batches" :key="'b' + item.id"><strong>{{ item.title }}</strong> <span class="muted">{{ item.start_date || 'Dates not set' }} · {{ item.course_title }}</span></p>
      <p v-if="!data.live.length && !data.batches.length" class="muted">Nothing is scheduled yet. Live classes and batches appear here.</p>
    </section>
  </div>
</template>
