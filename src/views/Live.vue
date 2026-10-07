<script setup>
import { onMounted, ref } from 'vue'
import { api, canTeach, session, toast } from '../api'
import { pretty } from '../format'

const payload = ref({ zoom_connected: false, classes: [] })
const batches = ref([])
const open = ref(false)
const room = ref(null)
const busy = ref(false)
const form = ref({ title: '', batch_id: '', starts_at: '', minutes: 60, description: '' })

function defaultStart() {
  const date = new Date(Date.now() + 26 * 3600 * 1000)
  date.setMinutes(0, 0, 0)
  const pad = (value) => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:00`
}
form.value.starts_at = defaultStart()

async function load() {
  payload.value = await api('/live-classes')
  if (canTeach()) batches.value = await api('/batches')
}

onMounted(load)

async function create() {
  busy.value = true
  try {
    await api('/live-classes', {
      method: 'POST',
      body: {
        ...form.value,
        batch_id: form.value.batch_id ? Number(form.value.batch_id) : null,
        minutes: Number(form.value.minutes),
        starts_at: new Date(form.value.starts_at).toISOString(),
      },
    })
    toast('Live class scheduled.')
    open.value = false
    form.value.title = ''
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function attend(item) {
  if (!session.user) {
    toast('Sign in to enter the room.', 'warn')
    return
  }
  try {
    const updated = await api(`/live-classes/${item.id}/attend`, { method: 'POST' })
    if (updated.provider === 'zoom' && updated.zoom_join_url) {
      window.open(updated.zoom_join_url, '_blank', 'noopener')
      toast('Opening Zoom. Attendance is marked.')
    } else {
      room.value = updated
      toast('You are in the studio room.')
    }
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}

async function setStatus(item, status) {
  try {
    await api(`/live-classes/${item.id}/status`, { method: 'POST', body: { status } })
    await load()
  } catch (err) {
    toast(err.message, 'warn')
  }
}
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <p class="kicker">Live classes</p>
        <h2>Rooms</h2>
        <p class="dek">
          <span v-if="payload.zoom_connected" class="pill good">Zoom connected</span>
          <span v-else>New classes use a studio room until Zoom is connected with ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, and ZOOM_CLIENT_SECRET.</span>
        </p>
      </div>
      <button v-if="canTeach()" class="btn" type="button" @click="open = !open">Schedule</button>
    </header>

    <form v-if="open" class="panel stack" style="padding: 1rem; margin-bottom: 1rem" @submit.prevent="create">
      <div class="row-2">
        <label class="field"><span>Title</span><input v-model="form.title" required /></label>
        <label class="field">
          <span>Batch</span>
          <select v-model="form.batch_id">
            <option value="">No batch</option>
            <option v-for="batch in batches" :key="batch.id" :value="batch.id">{{ batch.title }}</option>
          </select>
        </label>
      </div>
      <div class="row-2">
        <label class="field"><span>Starts</span><input v-model="form.starts_at" type="datetime-local" required /></label>
        <label class="field"><span>Minutes</span><input v-model="form.minutes" type="number" min="15" /></label>
      </div>
      <label class="field"><span>Note</span><textarea v-model="form.description"></textarea></label>
      <button class="btn" type="submit" :disabled="busy">Create class</button>
    </form>

    <div v-if="room" class="room">
      <p class="kicker" style="color: #e0c48a">In the room</p>
      <h2>{{ room.title }}</h2>
      <p style="margin-top: 0.4rem">{{ room.description || 'Attendance is on the ledger. The host can end the class when the hour is done.' }}</p>
    </div>

    <div class="stack" style="margin-top: 1rem">
      <article v-for="item in payload.classes" :key="item.id" class="panel" style="padding: 1rem">
        <div class="spread">
          <div>
            <p class="kicker">{{ item.batch_title || item.course_title || 'Studio' }} · {{ item.provider === 'zoom' ? 'Zoom' : 'Studio room' }}</p>
            <h3>{{ item.title }}</h3>
            <p class="muted">{{ item.host_name }} · {{ pretty(item.starts_at) }} · {{ item.minutes }} min · {{ item.attendance }} attended</p>
          </div>
          <span class="pill" :class="item.status === 'live' ? 'good' : item.status === 'ended' ? '' : 'warn'">{{ item.status }}</span>
        </div>
        <p v-if="item.description" style="margin: 0.6rem 0">{{ item.description }}</p>
        <div class="actions" style="margin-top: 0.7rem">
          <button v-if="item.status !== 'ended'" class="btn small" type="button" @click="attend(item)">
            {{ item.provider === 'zoom' ? 'Join Zoom' : 'Enter room' }}
          </button>
          <a v-if="item.zoom_start_url" class="btn small ghost" :href="item.zoom_start_url" target="_blank" rel="noopener">Start as host</a>
          <button v-if="session.user && (session.user.id === item.host_id || session.user.role === 'admin') && item.status !== 'live'" class="btn small ghost" type="button" @click="setStatus(item, 'live')">Mark live</button>
          <button v-if="session.user && (session.user.id === item.host_id || session.user.role === 'admin') && item.status !== 'ended'" class="btn small ghost" type="button" @click="setStatus(item, 'ended')">End</button>
        </div>
      </article>
    </div>
  </div>
</template>
