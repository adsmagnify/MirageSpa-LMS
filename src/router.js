import { createRouter, createWebHistory } from 'vue-router'
import { canTeach, homePath, hydrate, isAdmin, isStaff, session } from './api'
import Desk from './views/Desk.vue'
import Courses from './views/Courses.vue'
import Groups from './views/Groups.vue'
import Group from './views/Group.vue'
import Schools from './views/Schools.vue'
import Reports from './views/Reports.vue'
import Help from './views/Help.vue'
import Calendar from './views/Calendar.vue'
import Trash from './views/Trash.vue'
import Search from './views/Search.vue'
import Admin from './views/Admin.vue'
import Auth from './views/Auth.vue'
import Course from './views/Course.vue'
import Player from './views/Player.vue'
import Batches from './views/Batches.vue'
import Batch from './views/Batch.vue'
import Insights from './views/Insights.vue'
import Jobs from './views/Jobs.vue'
import JobDetail from './views/JobDetail.vue'
import Studio from './views/Studio.vue'
import StudioCourse from './views/StudioCourse.vue'
import Programs from './views/Programs.vue'
import Quizzes from './views/Quizzes.vue'
import QuizPage from './views/QuizPage.vue'
import Assign from './views/Assess.vue'
import Certification from './views/Certification.vue'
import Certified from './views/Certified.vue'
import Profile from './views/Profile.vue'
import Exercises from './views/Exercises.vue'
import Classes from './views/Classes.vue'
import Users from './views/Users.vue'
import Library from './views/Library.vue'

export const router = createRouter({
  history: createWebHistory(),
  scrollBehavior() {
    return { top: 0 }
  },
  routes: [
    { path: '/', component: Desk, meta: { shell: true, auth: true } },
    { path: '/login', component: Auth },
    { path: '/enter', redirect: '/login' },
    { path: '/classes', component: Classes, meta: { shell: true, auth: true } },
    { path: '/users', component: Users, meta: { shell: true, auth: true, admin: true } },
    { path: '/library', component: Library, meta: { shell: true, auth: true, teach: true } },
    { path: '/groups', component: Groups, meta: { shell: true, auth: true } },
    { path: '/groups/:id', component: Group, meta: { shell: true, auth: true } },
    { path: '/schools', component: Schools, meta: { shell: true, auth: true, teach: true } },
    { path: '/reports', component: Reports, meta: { shell: true, auth: true, teach: true } },
    { path: '/admin', component: Admin, meta: { shell: true, auth: true, admin: true } },
    { path: '/help', component: Help, meta: { shell: true, auth: true } },
    { path: '/calendar', component: Calendar, meta: { shell: true, auth: true } },
    { path: '/trash', component: Trash, meta: { shell: true, auth: true, teach: true } },
    { path: '/search', component: Search, meta: { shell: true, auth: true } },
    { path: '/courses', component: Courses, meta: { shell: true, auth: true } },
    { path: '/courses/new', component: Studio, meta: { shell: true, auth: true, teach: true } },
    { path: '/courses/:slug', component: Course, meta: { shell: true } },
    { path: '/courses/:slug/learn/:chapter-:lesson', component: Player, meta: { shell: true, lesson: true } },
    { path: '/courses/:slug/certification', component: Certification, meta: { shell: true, auth: true } },
    { path: '/batches', component: Batches, meta: { shell: true } },
    { path: '/batches/:id', component: Batch, meta: { shell: true } },
    { path: '/programs', component: Programs, meta: { shell: true } },
    { path: '/quizzes', component: Quizzes, meta: { shell: true } },
    { path: '/quiz/:id', component: QuizPage, meta: { shell: true, auth: true } },
    { path: '/assignments', component: Assign, meta: { shell: true, auth: true } },
    { path: '/programming-exercises', component: Exercises, meta: { shell: true, auth: true } },
    { path: '/job-openings', component: Jobs, meta: { shell: true } },
    { path: '/job-openings/:id', component: JobDetail, meta: { shell: true } },
    { path: '/statistics', component: Insights, meta: { shell: true, auth: true, staff: true } },
    { path: '/certified-participants', component: Certified, meta: { shell: true } },
    { path: '/user/:username', component: Profile, meta: { shell: true } },
    { path: '/studio/:id', component: StudioCourse, meta: { shell: true, auth: true, teach: true } },
    { path: '/learn', redirect: '/courses' },
    { path: '/jobs', redirect: '/job-openings' },
    { path: '/insights', redirect: '/statistics' },
  ],
})

router.beforeEach(async (to) => {
  if (!session.ready) await hydrate()
  if (to.meta.auth && !session.user) return { path: '/login', query: { next: to.fullPath } }
  if (to.meta.admin && !isAdmin()) return homePath()
  if (to.meta.staff && !isStaff()) return homePath()
  if (to.meta.teach && !canTeach()) return homePath()
  if (to.path === '/login' && session.user && !to.query.next) return homePath()
  if (session.user?.role === 'student' && to.path === '/courses/new') return '/'
})
