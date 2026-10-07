<script setup>
import { onMounted, ref } from 'vue'
import { api, toast } from '../api'

const data = ref(null)

async function load() {
  data.value = await api('/trash')
}
onMounted(load)

async function run(path, method, ok) {
  try {
    await api(path, { method })
    toast(ok)
    await load()
  } catch (error) {
    toast(error.message, 'warn')
  }
}
</script>

<template>
  <div v-if="data" class="page">
    <header class="page-head">
      <div>
        <h1>Trash</h1>
        <p class="dek">Restore a course or resource, or remove it permanently.</p>
      </div>
    </header>
    <section class="card stack">
      <h2>Courses</h2>
      <article v-for="course in data.courses" :key="course.id" class="spread">
        <span><strong>{{ course.title }}</strong><br /><span class="muted">{{ course.deleted_at?.slice(0, 10) }}</span></span>
        <span class="actions">
          <button class="btn small" type="button" @click="run(`/trash/courses/${course.id}/restore`, 'POST', 'Course restored.')">Restore</button>
          <button class="btn small ghost" type="button" @click="run(`/trash/courses/${course.id}`, 'DELETE', 'Course removed.')">Remove</button>
        </span>
      </article>
      <p v-if="!data.courses.length" class="muted">No courses in the trash.</p>
    </section>
    <section class="card stack" style="margin-top: 16px">
      <h2>Resources</h2>
      <article v-for="item in data.resources" :key="item.id" class="spread">
        <span><strong>{{ item.title }}</strong> <span class="muted">{{ item.kind }}</span></span>
        <span class="actions">
          <button class="btn small" type="button" @click="run(`/trash/resources/${item.id}/restore`, 'POST', 'Resource restored.')">Restore</button>
          <button class="btn small ghost" type="button" @click="run(`/trash/resources/${item.id}`, 'DELETE', 'Resource removed.')">Remove</button>
        </span>
      </article>
      <p v-if="!data.resources.length" class="muted">No resources in the trash.</p>
    </section>
  </div>
</template>
