<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api'

const route = useRoute()
const data = ref(null)

async function load() {
  data.value = await api(`/search?q=${encodeURIComponent(route.query.q || '')}`)
}
onMounted(load)
watch(() => route.query.q, load)
</script>

<template>
  <div v-if="data" class="page">
    <h1>Search</h1>
    <p class="dek">Results for “{{ route.query.q || '' }}”.</p>
    <section class="card stack">
      <h2>Courses</h2>
      <router-link v-for="course in data.courses" :key="course.id" :to="`/courses/${course.slug}`">{{ course.title }}</router-link>
      <p v-if="!data.courses.length" class="muted">No courses.</p>
      <h2>People</h2>
      <p v-for="person in data.people" :key="person.id">{{ person.full_name }} <span class="muted">{{ person.email }}</span></p>
      <p v-if="!data.people.length" class="muted">No people.</p>
      <h2>Resources</h2>
      <router-link v-for="item in data.resources" :key="item.id" to="/library">{{ item.title }} <span class="muted">{{ item.kind }}</span></router-link>
      <p v-if="!data.resources.length" class="muted">No resources.</p>
      <h2>Groups</h2>
      <router-link v-for="group in data.groups" :key="group.id" :to="`/groups/${group.id}`">{{ group.name }}</router-link>
      <p v-if="!data.groups.length" class="muted">No groups.</p>
    </section>
  </div>
</template>
