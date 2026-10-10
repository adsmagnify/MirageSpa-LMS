import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-exercises',
  imports: [FormsModule],
  template: `
    <div class="page">
      <h1>Programming Exercises</h1>
      <p class="dek">A written exercise attached to a course. Instructors mark each submission pass or fail.</p>
      @for (exercise of exercises; track exercise.id) {
        <article class="panel stack" style="padding: 14px; margin-top: 12px">
          <p class="kicker">{{ exercise.course_title }}</p>
          <h3>{{ exercise.title }}</h3>
          <p>{{ exercise.problem }}</p>
          <textarea [(ngModel)]="drafts[exercise.id]" [name]="'code-' + exercise.id" aria-label="Answer"></textarea>
          <button class="btn small" type="button" (click)="submit(exercise)">Submit</button>
        </article>
      }
      <h2 style="margin-top: 24px">Submissions</h2>
      @for (row of submissions; track row.id) {
        <article class="panel" style="padding: 12px; margin-top: 8px">
          <div class="spread">
            <strong>{{ row.title }} · {{ row.student_name }}</strong>
            <span class="pill" [class.good]="row.status === 'pass'" [class.warn]="row.status === 'fail'">{{ row.status }}</span>
          </div>
          <p style="margin-top: 6px">{{ row.code }}</p>
          @if (api.canTeach() && row.status === 'pending') {
            <div class="actions" style="margin-top: 8px">
              <button class="btn small" type="button" (click)="grade(row, 'pass')">Pass</button>
              <button class="btn small ghost" type="button" (click)="grade(row, 'fail')">Fail</button>
            </div>
          }
        </article>
      }
    </div>
  `,
})
export class ExercisesComponent implements OnInit {
  api = inject(ApiService);
  exercises: any[] = [];
  submissions: any[] = [];
  drafts: any = {};

  ngOnInit() {
    this.load();
  }

  async load() {
    this.exercises = await this.api.api('/exercises');
    this.submissions = await this.api.api('/exercise-submissions');
  }

  async submit(exercise: any) {
    try {
      await this.api.api(`/exercises/${exercise.id}/submit`, { method: 'POST', body: { code: this.drafts[exercise.id] || '' } });
      this.api.toast('Submitted for review.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async grade(row: any, status: string) {
    try {
      await this.api.api(`/exercise-submissions/${row.id}/grade`, {
        method: 'POST',
        body: { status, feedback: 'Marked in the exercise queue.' },
      });
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
