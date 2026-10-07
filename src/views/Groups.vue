<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, canTeach, toast } from '../api'

const router = useRouter()
const groups = ref([])
const form = ref({ name: '', description: '' })

async function load() {
  groups.value = await api('/groups')
}
onMounted(load)

async function create() {
  try {
    const group = await api('/groups', { method: 'POST', body: form.value })
    toast('Group created.')
    router.push(`/groups/${group.id}`)
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Groups</h1>
        <p class="dek">School groups keep a class, a clinic team, or a staff circle in one conversation.</p>
      </div>
    </header>
    <div class="cards">
      <form v-if="canTeach()" class="card stack" @submit.prevent="create">
        <h2>New group</h2>
        <label class="field"><span>Name</span><input v-model="form.name" required /></label>
        <label class="field"><span>Description</span><textarea v-model="form.description" rows="3"></textarea></label>
        <button class="btn" type="submit">Create group</button>
      </form>
      <section class="stack">
        <router-link v-for="group in groups" :key="group.id" class="card widget-row" :to="`/groups/${group.id}`">
          <span>
            <strong>{{ group.name }}</strong>
            <small class="muted">{{ group.owner_name }} · {{ group.members || 0 }} people</small>
          </span>
        </router-link>
        <p v-if="!groups.length" class="empty">You are not a member of any group.</p>
      </section>
    </div>
  </div>
</template>
