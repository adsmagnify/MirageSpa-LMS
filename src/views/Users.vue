<script setup>
import { onMounted, ref } from 'vue'
import { api, toast } from '../api'

const people = ref([])
const busy = ref(false)
const form = ref({ full_name: '', email: '', password: '', role: 'student' })

const roleName = {
  admin: 'Administrator',
  instructor: 'Instructor',
  student: 'Learner',
  moderator: 'Moderator',
}

async function load() {
  people.value = await api('/users')
}

onMounted(load)

async function create() {
  busy.value = true
  try {
    await api('/users', { method: 'POST', body: form.value })
    toast('Account created.')
    form.value = { full_name: '', email: '', password: '', role: 'student' }
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  } finally {
    busy.value = false
  }
}

async function changeRole(person, role) {
  try {
    const updated = await api(`/users/${person.id}`, { method: 'PATCH', body: { role } })
    Object.assign(person, updated)
    toast(`${person.full_name} is now ${roleName[role]}.`)
  } catch (error) {
    toast(error.message, 'warn')
    await load()
  }
}
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Users</h1>
        <p class="dek">Create each account and assign a role. People sign in with that role.</p>
      </div>
    </header>

    <form class="panel stack" style="padding: 16px; margin-bottom: 16px" @submit.prevent="create">
      <h2>New account</h2>
      <div class="row-2">
        <label class="field"><span>Name</span><input v-model="form.full_name" required /></label>
        <label class="field"><span>Email</span><input v-model="form.email" type="email" required /></label>
      </div>
      <div class="row-2">
        <label class="field"><span>Password</span><input v-model="form.password" type="text" minlength="4" required /></label>
        <label class="field">
          <span>Role</span>
          <select v-model="form.role">
            <option value="student">Learner</option>
            <option value="instructor">Instructor</option>
            <option value="admin">Administrator</option>
          </select>
        </label>
      </div>
      <button class="btn" type="submit" :disabled="busy">Create account</button>
    </form>

    <table>
      <thead>
        <tr><th>Name</th><th>Email</th><th>Role</th></tr>
      </thead>
      <tbody>
        <tr v-for="person in people" :key="person.id">
          <td>{{ person.full_name }}</td>
          <td>{{ person.email }}</td>
          <td>
            <select :value="person.role" aria-label="Role" @change="changeRole(person, $event.target.value)">
              <option value="student">Learner</option>
              <option value="instructor">Instructor</option>
              <option value="admin">Administrator</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
