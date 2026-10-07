<script setup>
defineProps({
  course: { type: Object, required: true },
})
</script>

<template>
  <router-link class="course-card" :to="`/courses/${course.slug}`">
    <div class="swatch" :data-cat="course.category"></div>
    <div class="course-body">
      <div class="kicker">{{ course.category }} · {{ course.level }}</div>
      <h3>{{ course.title }}</h3>
      <p>{{ course.summary }}</p>
      <div class="meta">
        <span>{{ course.instructor_name }}</span>
        <span>{{ course.learners || 0 }} enrolled</span>
      </div>
    </div>
  </router-link>
</template>
