<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, homePath, login, session, signup, toast } from '../api'

const route = useRoute()
const router = useRouter()
const needsSetup = ref(false)
const ready = ref(false)
const form = ref({ full_name: '', email: '', password: '' })
const busy = ref(false)

const roles = [
  ['Administrator', 'Manages users, roles, and the whole platform.'],
  ['Instructor', 'Uploads courses, lessons, and other materials.'],
  ['Learner', 'Opens the classes assigned to them.'],
]

function go() {
  router.push(route.query.next || homePath())
}

async function submit() {
  busy.value = true
  try {
    if (needsSetup.value) await signup(form.value)
    else await login(form.value.email, form.value.password)
    go()
  } catch (error) {
    toast(error.message, 'warn')
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  if (session.user && !route.query.next) {
    router.replace(homePath())
    return
  }
  try {
    const status = await api('/auth/status')
    needsSetup.value = status.needs_setup
  } catch (error) {
    toast(error.message, 'warn')
  } finally {
    ready.value = true
  }
})
</script>

<template>
  <div class="auth-wrap">
    <div class="auth" v-if="ready">
      <section class="auth-panel">
        <h1>{{ needsSetup ? 'Set up the administrator' : 'Sign in' }}</h1>
        <p class="dek" v-if="needsSetup">This first account manages the platform and assigns every other role.</p>
        <p class="dek" v-else>Sign in with the role an administrator assigned to you.</p>
        <ul v-if="!needsSetup" class="role-list">
          <li v-for="role in roles" :key="role[0]"><strong>{{ role[0] }}</strong> {{ role[1] }}</li>
        </ul>
        <form class="stack" @submit.prevent="submit">
          <label v-if="needsSetup" class="field">
            <span>Name</span>
            <input v-model="form.full_name" autocomplete="name" required />
          </label>
          <label class="field">
            <span>Email</span>
            <input v-model="form.email" type="email" autocomplete="username" required />
          </label>
          <label class="field">
            <span>Password</span>
            <input v-model="form.password" type="password" :autocomplete="needsSetup ? 'new-password' : 'current-password'" required minlength="4" />
          </label>
          <button class="btn" type="submit" :disabled="busy">{{ needsSetup ? 'Create administrator' : 'Sign in' }}</button>
        </form>
      </section>
    </div>
  </div>
</template>
