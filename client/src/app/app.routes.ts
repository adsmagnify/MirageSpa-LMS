import { Routes, UrlMatchResult, UrlSegment } from '@angular/router';
import { authGuard, guestGuard } from './auth.guard';
import { ShellComponent } from './components/shell.component';
import { AdminComponent } from './pages/admin.component';
import { AssessComponent } from './pages/assess.component';
import { AuthComponent } from './pages/auth.component';
import { BatchComponent } from './pages/batch.component';
import { BatchesComponent } from './pages/batches.component';
import { CalendarComponent } from './pages/calendar.component';
import { CertificationComponent } from './pages/certification.component';
import { CertifiedComponent } from './pages/certified.component';
import { ClassesComponent } from './pages/classes.component';
import { CourseComponent } from './pages/course.component';
import { CoursesComponent } from './pages/courses.component';
import { DeskComponent } from './pages/desk.component';
import { ExercisesComponent } from './pages/exercises.component';
import { GroupComponent } from './pages/group.component';
import { GroupsComponent } from './pages/groups.component';
import { HelpComponent } from './pages/help.component';
import { InsightsComponent } from './pages/insights.component';
import { JobDetailComponent } from './pages/job-detail.component';
import { JobsComponent } from './pages/jobs.component';
import { LibraryComponent } from './pages/library.component';
import { PlayerComponent } from './pages/player.component';
import { ProfileComponent } from './pages/profile.component';
import { ProgramsComponent } from './pages/programs.component';
import { QuizPageComponent } from './pages/quiz-page.component';
import { QuizzesComponent } from './pages/quizzes.component';
import { ReportsComponent } from './pages/reports.component';
import { SchoolsComponent } from './pages/schools.component';
import { SearchComponent } from './pages/search.component';
import { StudioCourseComponent } from './pages/studio-course.component';
import { StudioComponent } from './pages/studio.component';
import { TrashComponent } from './pages/trash.component';
import { UsersComponent } from './pages/users.component';

function lessonPath(segments: UrlSegment[]): UrlMatchResult | null {
  if (segments.length !== 4 || segments[0].path !== 'courses' || segments[2].path !== 'learn') return null;
  const parts = segments[3].path.split('-');
  if (parts.length < 2 || !parts[0] || !parts[1]) return null;
  return {
    consumed: segments,
    posParams: {
      slug: segments[1],
      chapter: new UrlSegment(parts[0], {}),
      lesson: new UrlSegment(parts.slice(1).join('-'), {}),
    },
  };
}

export const routes: Routes = [
  { path: 'login', component: AuthComponent, canActivate: [guestGuard] },
  { path: 'enter', redirectTo: 'login', pathMatch: 'full' },
  {
    path: '',
    component: ShellComponent,
    children: [
      { path: '', component: DeskComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'classes', component: ClassesComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'users', component: UsersComponent, canActivate: [authGuard], data: { auth: true, admin: true } },
      { path: 'library', component: LibraryComponent, canActivate: [authGuard], data: { auth: true, teach: true } },
      { path: 'groups', component: GroupsComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'groups/:id', component: GroupComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'schools', component: SchoolsComponent, canActivate: [authGuard], data: { auth: true, teach: true } },
      { path: 'reports', component: ReportsComponent, canActivate: [authGuard], data: { auth: true, teach: true } },
      { path: 'admin', component: AdminComponent, canActivate: [authGuard], data: { auth: true, admin: true } },
      { path: 'help', component: HelpComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'calendar', component: CalendarComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'trash', component: TrashComponent, canActivate: [authGuard], data: { auth: true, teach: true } },
      { path: 'search', component: SearchComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'courses', component: CoursesComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'courses/new', component: StudioComponent, canActivate: [authGuard], data: { auth: true, teach: true } },
      { path: 'courses/:slug', component: CourseComponent },
      { matcher: lessonPath, component: PlayerComponent, data: { lesson: true } },
      { path: 'courses/:slug/certification', component: CertificationComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'batches', component: BatchesComponent },
      { path: 'batches/:id', component: BatchComponent },
      { path: 'programs', component: ProgramsComponent },
      { path: 'quizzes', component: QuizzesComponent },
      { path: 'quiz/:id', component: QuizPageComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'assignments', component: AssessComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'programming-exercises', component: ExercisesComponent, canActivate: [authGuard], data: { auth: true } },
      { path: 'job-openings', component: JobsComponent },
      { path: 'job-openings/:id', component: JobDetailComponent },
      { path: 'statistics', component: InsightsComponent, canActivate: [authGuard], data: { auth: true, staff: true } },
      { path: 'certified-participants', component: CertifiedComponent },
      { path: 'user/:username', component: ProfileComponent },
      { path: 'studio/:id', component: StudioCourseComponent, canActivate: [authGuard], data: { auth: true, teach: true } },
      { path: 'learn', redirectTo: 'courses', pathMatch: 'full' },
      { path: 'jobs', redirectTo: 'job-openings', pathMatch: 'full' },
      { path: 'insights', redirectTo: 'statistics', pathMatch: 'full' },
    ],
  },
];
