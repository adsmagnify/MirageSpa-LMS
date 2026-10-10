import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';
import { pretty as formatPretty } from '../format';

@Component({
  selector: 'app-assess',
  imports: [RouterLink, FormsModule],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <p class="kicker">Assessments</p>
          <h1>Assignments</h1>
          <p class="dek">Quizzes mark themselves. Assignments sit here until an instructor scores them.</p>
        </div>
      </header>

      @if (api.canTeach()) {
        <section style="margin-bottom: 1.4rem">
          <h3>Grading queue</h3>
          @if (!queue.length) {
            <p class="empty">Nothing waiting.</p>
          }
          @for (item of queue; track item.id) {
            <article class="panel stack" style="padding: 1rem; margin-top: 0.7rem">
              <div class="spread">
                <strong>{{ item.student_name }} · {{ item.assignment_title }}</strong>
                <span class="pill" [class.good]="item.status === 'graded'" [class.warn]="item.status !== 'graded'">{{ item.status }}</span>
              </div>
              <p class="muted">{{ item.course_title }} · {{ pretty(item.submitted_at) }}</p>
              <p>{{ item.body }}</p>
              @if (item.feedback) {
                <p>{{ item.feedback }}</p>
              }
              @if (item.status !== 'graded' && drafts[item.id]) {
                <div class="row-2">
                  <label class="field">
                    <span>Score / {{ item.max_score }}</span>
                    <input [(ngModel)]="drafts[item.id].score" [name]="'score-' + item.id" type="number" [max]="item.max_score" min="0" />
                  </label>
                  <label class="field">
                    <span>Feedback</span>
                    <input [(ngModel)]="drafts[item.id].feedback" [name]="'feedback-' + item.id" />
                  </label>
                </div>
              }
              @if (item.status !== 'graded') {
                <button class="btn small" type="button" [disabled]="busy" (click)="grade(item)">Save grade</button>
              }
            </article>
          }
        </section>
      }

      <section class="cards" style="align-items: start">
        <div>
          <h3>Your quizzes</h3>
          @for (quiz of mine.quizzes; track quiz.id) {
            <article class="panel" style="padding: 0.9rem; margin-top: 0.6rem">
              <div class="spread">
                <div>
                  <strong>{{ quiz.title }}</strong>
                  <p class="muted">{{ quiz.course_title }}</p>
                </div>
                @if (quiz.last_attempt) {
                  <span class="pill" [class.good]="quiz.last_attempt.passed" [class.warn]="!quiz.last_attempt.passed">{{ quiz.last_attempt.score }}%</span>
                } @else {
                  <span class="pill">Not taken</span>
                }
              </div>
              <a [routerLink]="['/learn', quiz.slug]">Open</a>
            </article>
          }
          @if (!mine.quizzes.length) {
            <p class="empty">Enroll in a course to see its quizzes.</p>
          }
        </div>
        <div>
          <h3>Your assignments</h3>
          @for (item of mine.submissions; track item.id) {
            <article class="panel" style="padding: 0.9rem; margin-top: 0.6rem">
              <div class="spread">
                <strong>{{ item.assignment_title }}</strong>
                <span class="pill" [class.good]="item.status === 'graded'" [class.warn]="item.status !== 'graded'">
                  {{ item.status }}@if (item.score != null) { · {{ item.score }}/{{ item.max_score }} }
                </span>
              </div>
              <p class="muted">{{ item.course_title }}</p>
              @if (item.feedback) {
                <p style="margin-top: 0.4rem">{{ item.feedback }}</p>
              }
            </article>
          }
          @if (!mine.submissions.length) {
            <p class="empty">No assignments handed in.</p>
          }
        </div>
      </section>
    </div>
  `,
})
export class AssessComponent implements OnInit {
  api = inject(ApiService);
  mine: any = { quizzes: [], submissions: [] };
  queue: any[] = [];
  drafts: any = {};
  busy = false;

  ngOnInit() {
    this.load();
  }

  pretty(value: any) {
    return formatPretty(value);
  }

  async load() {
    this.mine = await this.api.api('/me/assessments');
    if (this.api.canTeach()) {
      const rows = await this.api.api('/submissions');
      for (const item of rows) {
        if (!this.drafts[item.id]) this.drafts[item.id] = { score: '', feedback: '' };
      }
      this.queue = rows;
    }
  }

  async grade(item: any) {
    const draft = this.drafts[item.id] || {};
    this.busy = true;
    try {
      await this.api.api(`/submissions/${item.id}/grade`, {
        method: 'POST',
        body: { score: Number(draft.score), feedback: draft.feedback || '' },
      });
      this.api.toast('Graded.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }
}
