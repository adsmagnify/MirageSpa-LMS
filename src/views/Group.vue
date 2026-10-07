<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, canTeach, toast } from '../api'

const route = useRoute()
const group = ref(null)
const email = ref('')
const body = ref('')

async function load() {
  group.value = await api(`/groups/${route.params.id}`)
}
onMounted(load)

async function add() {
  try {
    group.value = await api(`/groups/${route.params.id}/members`, { method: 'POST', body: { email: email.value } })
    email.value = ''
    toast('Added to the group.')
  } catch (error) {
    toast(error.message, 'warn')
  }
}

async function post() {
  try {
    await api('/feed', { method: 'POST', body: { body: body.value, group_id: Number(route.params.id) } })
    body.value = ''
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div v-if="group" class="page">
    <p class="crumbs"><router-link to="/groups">Groups</router-link><span>/</span><span>{{ group.name }}</span></p>
    <header class="page-head">
      <div>
        <h1>{{ group.name }}</h1>
        <p class="dek">{{ group.description || 'No description yet.' }}</p>
      </div>
    </header>
    <div class="cards">
      <section class="card stack">
        <h2>People</h2>
        <p v-for="member in group.members_list" :key="member.id">{{ member.full_name }} <span class="muted">{{ member.email }}</span></p>
        <form v-if="canTeach()" class="stack" @submit.prevent="add">
          <label class="field"><span>Add by email</span><input v-model="email" type="email" required /></label>
          <button class="btn small" type="submit">Add</button>
        </form>
      </section>
      <section class="card stack">
        <h2>Messages</h2>
        <article v-for="item in group.posts" :key="item.id">
          <strong>{{ item.author_name }}</strong>
          <p>{{ item.body }}</p>
        </article>
        <p v-if="!group.posts.length" class="muted">No messages in this group yet.</p>
        <form class="news-form" @submit.prevent="post">
          <input v-model="body" placeholder="Write a message" required />
          <button class="btn small" type="submit">Post</button>
        </form>
      </section>
    </div>
  </div>
</template>
