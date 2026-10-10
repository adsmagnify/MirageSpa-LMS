import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-quiz-page',
  imports: [RouterLink],
  template: `
    @if (quiz) {
      <div class="page">
        <p class="crumbs"><a routerLink="/quizzes">Quizzes</a><span>/</span><span>{{ quiz.title }}</span></p>
        <div class="spread">
          <h1>{{ quiz.title }}</h1>
          @if (quiz.duration_minutes) {
            <span class="pill">{{ clock() }}</span>
          }
        </div>
        <p class="dek">Passing mark {{ quiz.passing_score }}%.</p>
        @if (quiz.last_attempt) {
          <p><span class="pill" [class.good]="quiz.last_attempt.passed" [class.warn]="!quiz.last_attempt.passed">Last attempt {{ quiz.last_attempt.score }}%</span></p>
        }
        @for (question of quiz.questions; track question.id) {
          <div class="question">
            <strong>{{ question.prompt }}</strong>
            @for (option of question.options; track $index; let index = $index) {
              <button class="choice" type="button" [class.on]="answers[question.id] === index" (click)="answers[question.id] = index">
                {{ option }}
              </button>
            }
            @if (question.explanation) {
              <p class="muted">{{ question.explanation }}</p>
            }
          </div>
        }
        <button class="btn" type="button" [disabled]="busy" (click)="submit()">Submit</button>
      </div>
    }
  `,
})
export class QuizPageComponent implements OnInit, OnDestroy {
  api = inject(ApiService);
  route = inject(ActivatedRoute);
  quiz: any = null;
  answers: any = {};
  busy = false;
  seconds = 0;
  private timer: any;

  ngOnInit() {
    this.load();
  }

  ngOnDestroy() {
    clearInterval(this.timer);
  }

  async load() {
    this.quiz = await this.api.api(`/quizzes/${this.route.snapshot.paramMap.get('id')}`);
    if (this.quiz.duration_minutes) {
      this.seconds = this.quiz.duration_minutes * 60;
      this.timer = setInterval(() => {
        this.seconds -= 1;
        if (this.seconds <= 0) {
          clearInterval(this.timer);
          this.submit();
        }
      }, 1000);
    }
  }

  async submit() {
    if (this.busy || !this.quiz) return;
    this.busy = true;
    clearInterval(this.timer);
    try {
      this.quiz = await this.api.api(`/quizzes/${this.quiz.id}/submit`, { method: 'POST', body: { answers: this.answers } });
      this.api.toast(this.quiz.passed ? `Passed · ${this.quiz.score}%` : `${this.quiz.score}%`, this.quiz.passed ? 'ok' : 'warn');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  clock() {
    const minutes = Math.floor(this.seconds / 60);
    const secs = String(Math.max(this.seconds, 0) % 60).padStart(2, '0');
    return `${minutes}:${secs}`;
  }
}
