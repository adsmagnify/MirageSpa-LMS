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
        <div class="split">
          <div>
            <div class="swatch" [attr.data-cat]="data.course.category" style="height: 180px; border-radius: 10px"></div>
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
                  {{ data.progress ? 'Continue' : 'Start lesson' }}
                </a>
              } @else if (api.canTeach()) {
                <form class="stack" (ngSubmit)="assignLearner()">
                  <label class="field"><span>Assign a learner</span><input [(ngModel)]="learnerEmail" name="learnerEmail" type="email" required placeholder="learner@email" /></label>
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
              } @else {
                <p class="muted">An instructor assigns this class before you can open it.</p>
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
  kindLabel: any = { text: 'Reading', video: 'Video', quiz: 'Quiz', assignment: 'Assignment' };

  ngOnInit() {
    this.load();
  }

  async load() {
    try {
      this.data = await this.api.api(`/courses/${this.route.snapshot.paramMap.get('slug')}/page`);
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
