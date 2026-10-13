import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';
import { paragraphs } from '../format';

@Component({
  selector: 'app-course',
  imports: [FormsModule, RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <p class="crumbs"><a routerLink="/courses">Courses</a><span>/</span><span>{{ data.course.title }}</span></p>
        @if (canEdit()) {
          <nav class="tabs">
            <button type="button" [class.on]="view === 'overview'" (click)="view = 'overview'">Overview</button>
            <button type="button" [class.on]="view === 'dashboard'" (click)="view = 'dashboard'">Dashboard</button>
            <button type="button" [class.on]="view === 'editor'" (click)="view = 'editor'">Editor</button>
            <button type="button" [class.on]="view === 'settings'" (click)="view = 'settings'">Settings</button>
          </nav>
        }
        @if (view === 'dashboard' && canEdit()) {
          <section class="stat-kpis">
            <article><span>Enrolled</span><strong>{{ data.course.learners }}</strong><small>Active learners</small></article>
            <article><span>Your progress</span><strong>{{ data.progress }}%</strong><small>As the instructor</small></article>
            <article><span>Rating</span><strong>{{ data.rating || '—' }}</strong><small>{{ data.reviews.length }} reviews</small></article>
            <article><span>Lessons</span><strong>{{ lessonCount() }}</strong><small>In the outline</small></article>
          </section>
          <article class="panel stack" style="padding: 16px; margin-top: 16px">
            <form class="stack" (ngSubmit)="assignLearner()">
              <label class="field"><span>Enroll a learner</span><input [(ngModel)]="learnerEmail" name="learnerEmail" type="email" required placeholder="learner@email" /></label>
              <button class="btn" type="submit" [disabled]="busy">Enroll</button>
            </form>
            @for (person of roster; track person.id) {
              <p class="spread">
                <span>{{ person.full_name }} @if (!person.active) { <span class="muted">Deactivated</span> }</span>
                <button class="btn small ghost" type="button" (click)="toggleLearner(person)">{{ person.active ? 'Deactivate' : 'Activate' }}</button>
              </p>
            }
            @if (!roster.length) {
              <p class="empty">No students enrolled yet.</p>
            }
          </article>
        } @else if (view === 'editor' && canEdit()) {
          <article class="panel stack" style="padding: 16px">
            <p class="dek">Chapters and lessons are edited here, the same way a course outline is built.</p>
            <a class="btn" [routerLink]="['/studio', data.course.id]">Open course editor</a>
          </article>
        } @else if (view === 'settings' && canEdit()) {
          <form class="maker-form" (ngSubmit)="saveSettings()">
            <h2>Settings</h2>
            <div class="maker-grid">
              <label class="field wide"><span>Title</span><input [(ngModel)]="settings.title" name="title" required /></label>
              <label class="field"><span>Category</span><input [(ngModel)]="settings.category" name="category" /></label>
              <label class="field"><span>Level</span><input [(ngModel)]="settings.level" name="level" /></label>
              <label class="field wide"><span>Short introduction</span><input [(ngModel)]="settings.summary" name="summary" /></label>
              <label class="field wide"><span>Description</span><textarea [(ngModel)]="settings.description" name="description"></textarea></label>
            </div>
            <div class="checks">
              <label><input type="checkbox" [(ngModel)]="settings.enable_certification" name="cert" /> Offer a certificate</label>
              <label><input type="checkbox" [(ngModel)]="settings.disable_self_learning" name="closed" /> Learners must be enrolled by an instructor</label>
              <label><input type="checkbox" [(ngModel)]="settings.paid" name="paid" /> Paid course</label>
            </div>
            <div class="maker-actions">
              <button class="btn" type="submit" [disabled]="busy">Save</button>
              <button class="btn ghost" type="button" (click)="setStatus(data.course.status === 'published' ? 'draft' : 'published')">
                {{ data.course.status === 'published' ? 'Unpublish' : 'Publish' }}
              </button>
            </div>
          </form>
        } @else {
        <div class="split">
          <div>
            <div class="banner-wrap">
              @if (data.course.thumbnail) {
                <img class="course-thumb" [class.adjusting]="adjusting" [src]="'/api/courses/' + data.course.id + '/thumbnail?v=' + data.course.thumbnail" [alt]="data.course.title" [style.object-position]="focusStyle()" (pointerdown)="dragBanner($event)" />
              } @else {
                <div class="swatch" [attr.data-cat]="data.course.category" style="height: 180px; border-radius: 10px"></div>
              }
              @if (canEdit()) {
                <div class="banner-tools">
                  <button class="btn small" type="button" (click)="adjusting = !adjusting">{{ adjusting ? 'Done' : 'Adjust picture' }}</button>
                  @if (adjusting) {
                    <label class="btn small ghost">
                      {{ data.course.thumbnail ? 'Replace' : 'Choose photo' }}
                      <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden (change)="setThumb($event)" />
                    </label>
                  }
                </div>
              }
            </div>
            <header style="margin: 16px 0">
              <p class="kicker">{{ data.course.category }} · {{ data.course.level }}</p>
              <h1>{{ data.course.title }}</h1>
              <p class="dek">{{ data.course.summary }}</p>
              <p class="muted" style="margin-top: 8px">
                {{ data.course.instructor_name }}@if (data.rating) { · {{ data.rating }} / 5 } · {{ data.course.learners }} enrolled
              </p>
            </header>
            <div class="prose">
              @for (paragraph of paragraphs(data.course.description); track $index) {
                <p>{{ paragraph }}</p>
              }
            </div>
            <h2 style="margin: 22px 0 8px">Curriculum</h2>
            @for (chapter of data.outline; track chapter.id) {
              <article class="panel" style="padding: 12px 14px; margin-bottom: 8px">
                <strong>{{ chapter.number }}. {{ chapter.title }}</strong>
                @for (lesson of chapter.lessons; track lesson.id) {
                  <a
                    class="spread"
                    style="padding: 8px 0; border-top: 1px solid var(--line); margin-top: 8px"
                    [routerLink]="['/courses', data.course.slug, 'learn', chapter.number + '-' + lesson.number]"
                  >
                    <span>{{ chapter.number }}.{{ lesson.number }} {{ lesson.title }}</span>
                    <span class="muted">{{ kindLabel[lesson.kind] }}@if (lesson.include_in_preview) { · Preview }@if (lesson.completed) { · Done }</span>
                  </a>
                }
              </article>
            }
            <h2 style="margin: 22px 0 8px">Reviews</h2>
            @for (item of data.reviews; track $index) {
              <article class="panel" style="padding: 12px; margin-bottom: 8px">
                <strong>{{ item.full_name }}</strong> <span class="pill">{{ item.rating }}/5</span>
                <p style="margin-top: 6px">{{ item.body }}</p>
              </article>
            }
            @if (data.enrolled) {
              <form class="stack" style="margin-top: 8px" (ngSubmit)="sendReview()">
                <label class="field"><span>Your rating</span>
                  <select [(ngModel)]="review.rating" name="rating">
                    <option [ngValue]="5">5</option>
                    <option [ngValue]="4">4</option>
                    <option [ngValue]="3">3</option>
                    <option [ngValue]="2">2</option>
                    <option [ngValue]="1">1</option>
                  </select>
                </label>
                <label class="field"><span>Review</span><textarea [(ngModel)]="review.body" name="body"></textarea></label>
                <button class="btn small" type="submit">Save review</button>
              </form>
            }
          </div>
          <aside class="stack">
            <article class="panel stack" style="padding: 14px">
              @if (data.enrolled) {
                <div class="bar"><span [style.width]="data.progress + '%'"></span></div>
              }
              @if (data.enrolled) {
                <p class="muted">{{ data.progress }}% complete</p>
              }
              @if (data.enrolled) {
                <a class="btn" [routerLink]="['/courses', data.course.slug, 'learn', '1-1']">
                  {{ data.progress ? 'Continue learning' : 'Start lesson' }}
                </a>
              } @else if (!api.user) {
                <a class="btn" routerLink="/login" [queryParams]="{ next: router.url }">Sign in to enroll</a>
              } @else if (data.course.disable_self_learning && !canEdit()) {
                <p class="muted">Contact the administrator to enroll for this course.</p>
              } @else if (data.course.paid && !canEdit()) {
                <p class="muted">This course is paid. An instructor enrolls you.</p>
              } @else if (!canEdit()) {
                <button class="btn" type="button" [disabled]="busy" (click)="enroll()">Enroll now</button>
              }
              @if (canEdit()) {
                <form class="stack" (ngSubmit)="assignLearner()">
                  <label class="field"><span>Assign a learner</span><input [(ngModel)]="learnerEmail" name="learnerEmail" type="email" required placeholder="kajal@adsmagnify.in" /></label>
                  <button class="btn" type="submit" [disabled]="busy">Assign class</button>
                </form>
              }
              @if (api.canTeach() && roster.length) {
                <div class="stack">
                  <strong>Roster</strong>
                  @for (person of roster; track person.id) {
                    <p class="spread">
                      <span>{{ person.full_name }} @if (!person.active) { <span class="muted">Deactivated</span> }</span>
                      <button class="btn small ghost" type="button" (click)="toggleLearner(person)">{{ person.active ? 'Deactivate' : 'Activate' }}</button>
                    </p>
                  }
                </div>
              } @else if (!data.enrolled && !api.user) {
                <p class="muted">Sign in to enroll.</p>
              }
              <button class="btn ghost" type="button" (click)="share()">Share</button>
              @if (data.course.enable_certification) {
                <a class="btn ghost" [routerLink]="['/courses', data.course.slug, 'certification']">Certification</a>
              }
              @if (api.canTeach() && (api.isAdmin() || api.user.id === data.course.instructor_id)) {
                <a class="btn ghost" [routerLink]="['/studio', data.course.id]">Edit course</a>
              }
              <p class="muted">{{ data.course.instructor_name }} · {{ data.course.instructor_headline }}</p>
              @if (data.certificate) {
                <p class="pill good">Certified {{ data.certificate.issue_date.slice(0, 10) }}</p>
              }
            </article>
          </aside>
        </div>
        }
      </div>
    } @else {
      <div class="page"><p class="empty">{{ error || 'Loading course…' }}</p></div>
    }
  `,
})
export class CourseComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  route = inject(ActivatedRoute);
  paragraphs = paragraphs;
  data: any = null;
  error = '';
  busy = false;
  review: any = { rating: 5, body: '' };
  learnerEmail = '';
  roster: any[] = [];
  adjusting = false;
  view = 'overview';
  settings: any = { title: '', summary: '', description: '', category: '', level: '', enable_certification: false, disable_self_learning: false, paid: false };
  kindLabel: any = { text: 'Reading', video: 'Video', quiz: 'Quiz', assignment: 'Assignment' };

  ngOnInit() {
    this.load();
  }

  async load() {
    try {
      this.data = await this.api.api(`/courses/${this.route.snapshot.paramMap.get('slug')}/page`);
      const course = this.data.course;
      this.settings = {
        title: course.title,
        summary: course.summary,
        description: course.description,
        category: course.category,
        level: course.level,
        enable_certification: !!course.enable_certification,
        disable_self_learning: !!course.disable_self_learning,
        paid: !!course.paid,
      };
      if (this.api.canTeach()) {
        try {
          this.roster = await this.api.api(`/courses/${this.data.course.id}/roster`);
        } catch {
          this.roster = [];
        }
      }
    } catch (err: any) {
      this.error = err.message;
    }
  }

  lessonCount() {
    return (this.data?.outline || []).reduce((sum: number, chapter: any) => sum + (chapter.lessons?.length || 0), 0);
  }

  async enroll() {
    this.busy = true;
    try {
      await this.api.api(`/courses/${this.data.course.id}/enroll`, { method: 'POST' });
      this.api.toast('You are enrolled.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async saveSettings() {
    this.busy = true;
    try {
      this.data.course = await this.api.api(`/studio/courses/${this.data.course.id}`, {
        method: 'PATCH',
        body: {
          ...this.settings,
          enable_certification: this.settings.enable_certification ? 1 : 0,
          disable_self_learning: this.settings.disable_self_learning ? 1 : 0,
          paid: this.settings.paid ? 1 : 0,
        },
      });
      this.api.toast('Course saved.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async setStatus(status: string) {
    try {
      this.data.course = await this.api.api(`/studio/courses/${this.data.course.id}`, { method: 'PATCH', body: { status } });
      this.api.toast(status === 'published' ? 'Course published.' : 'Course unpublished.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  canEdit() {
    return this.api.canTeach() && (this.api.isAdmin() || this.api.user?.id === this.data?.course?.instructor_id);
  }

  focusStyle() {
    return `${this.data?.course?.thumb_x ?? 50}% ${this.data?.course?.thumb_y ?? 50}%`;
  }

  dragBanner(event: PointerEvent) {
    if (!this.adjusting || !this.data?.course?.thumbnail) return;
    const frame = event.currentTarget as HTMLElement;
    const place = (ev: PointerEvent) => {
      const box = frame.getBoundingClientRect();
      this.data.course.thumb_x = Math.max(0, Math.min(100, Math.round(((ev.clientX - box.left) / box.width) * 100)));
      this.data.course.thumb_y = Math.max(0, Math.min(100, Math.round(((ev.clientY - box.top) / box.height) * 100)));
    };
    const move = (ev: PointerEvent) => place(ev);
    const up = () => {
      frame.removeEventListener('pointermove', move);
      frame.removeEventListener('pointerup', up);
      this.saveFocus();
    };
    frame.setPointerCapture(event.pointerId);
    frame.addEventListener('pointermove', move);
    frame.addEventListener('pointerup', up);
    place(event);
  }

  async saveFocus() {
    try {
      const course = await this.api.api(`/studio/courses/${this.data.course.id}/thumbnail`, {
        method: 'PATCH',
        body: { x: this.data.course.thumb_x, y: this.data.course.thumb_y },
      });
      this.data.course.thumb_x = course.thumb_x;
      this.data.course.thumb_y = course.thumb_y;
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async setThumb(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    const body = new FormData();
    body.append('file', file);
    try {
      this.data.course = await this.api.api(`/studio/courses/${this.data.course.id}/thumbnail`, { method: 'POST', body, form: true });
      this.api.toast('Picture saved.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      input.value = '';
    }
  }

  async assignLearner() {
    this.busy = true;
    try {
      await this.api.api(`/courses/${this.data.course.id}/assign`, { method: 'POST', body: { email: this.learnerEmail } });
      this.api.toast('Class assigned.');
      this.learnerEmail = '';
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async toggleLearner(person: any) {
    try {
      await this.api.api(`/courses/${this.data.course.id}/learners/${person.id}`, { method: 'POST', body: { active: !person.active } });
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async share() {
    const url = `${location.origin}/courses/${this.data.course.slug}`;
    try {
      await navigator.clipboard.writeText(url);
      this.api.toast('Course link copied.');
    } catch {
      this.api.toast(url);
    }
  }

  async sendReview() {
    if (!this.api.user) {
      this.router.navigate(['/login'], { queryParams: { next: this.router.url } });
      return;
    }
    try {
      await this.api.api(`/courses/${this.data.course.id}/reviews`, { method: 'POST', body: this.review });
      this.api.toast('Review saved.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
