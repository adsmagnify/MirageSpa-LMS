import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-quizzes',
  imports: [RouterLink],
  template: `
    <div class="page">
      <h1>Quizzes</h1>
      <p class="dek">Each quiz belongs to a course. Passing marks the lesson complete, up to the attempt limit.</p>
      <div class="cards" style="margin-top: 16px">
        @for (quiz of quizzes; track quiz.id) {
          <article class="panel" style="padding: 14px">
            <p class="kicker">{{ quiz.course_title }}</p>
            <h3>{{ quiz.title }}</h3>
            <p class="muted">Pass {{ quiz.passing_score }}%@if (quiz.max_attempts) { · {{ quiz.attempts || 0 }}/{{ quiz.max_attempts }} attempts }</p>
            @if (quiz.last_attempt) {
              <p style="margin: 8px 0"><span class="pill" [class.good]="quiz.last_attempt.passed" [class.warn]="!quiz.last_attempt.passed">{{ quiz.last_attempt.score }}%</span></p>
            }
            <a [routerLink]="['/quiz', quiz.id]">Open quiz</a>
          </article>
        }
      </div>
      @if (submissions.length) {
        <section style="margin-top: 28px">
          <h2>Submissions</h2>
          <table>
            <thead><tr><th>Member</th><th>Quiz</th><th>Course</th><th>Score</th></tr></thead>
            <tbody>
              @for (row of submissions; track row.id) {
                <tr>
                  <td>{{ row.full_name }}</td>
                  <td>{{ row.quiz_title }}</td>
                  <td>{{ row.course_title }}</td>
                  <td>{{ row.score }}%</td>
                </tr>
              }
            </tbody>
          </table>
        </section>
      }
    </div>
  `,
})
export class QuizzesComponent implements OnInit {
  api = inject(ApiService);
  quizzes: any[] = [];
  submissions: any[] = [];

  ngOnInit() {
    this.load();
  }

  async load() {
    this.quizzes = await this.api.api('/quizzes');
    this.submissions = await this.api.api('/quiz-submissions').catch(() => []);
  }
}
