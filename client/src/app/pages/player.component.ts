import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { NgClass } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';
import { paragraphs } from '../format';

@Component({
  selector: 'app-player',
  imports: [NgClass, FormsModule, RouterLink],
  template: `
    @if (error) {
      <p class="page empty">{{ error }} <a [routerLink]="['/courses', route.snapshot.paramMap.get('slug')]">Back to course</a></p>
    } @else if (data && lesson) {
      <div class="lesson-app">
        <aside class="outline">
          <a [routerLink]="['/courses', data.course.slug]"><strong>{{ data.course.title }}</strong></a>
          <p class="muted" style="margin: 8px 0">{{ data.progress }}% complete</p>
          <div class="bar"><span [style.width]="data.progress + '%'"></span></div>
          @for (chapter of data.outline; track chapter.id) {
            <p class="chapter-label">{{ chapter.number }}. {{ chapter.title }}</p>
            @for (item of chapter.lessons; track item.id) {
              <button type="button" [class.on]="item.id === lesson.id" (click)="open(chapter.number, item.number)">
                <span>{{ chapter.number }}.{{ item.number }} {{ item.title }}</span>
                <span class="muted">{{ item.completed ? 'Done' : kindLabel[item.kind] }}</span>
              </button>
            }
          }
        </aside>
        <article class="stage">
          <p class="crumbs">
            <a routerLink="/courses">Courses</a><span>/</span>
            <a [routerLink]="['/courses', data.course.slug]">{{ data.course.title }}</a><span>/</span>
            <span>{{ lesson.title }}</span>
          </p>
          <p class="kicker">{{ data.chapter_number }}.{{ data.lesson_number }} · {{ lesson.minutes }} min</p>
          <h1>{{ lesson.title }}</h1>

          @for (block of lesson.blocks; track $index) {
            <section class="block">
              @if (block.type === 'markdown') {
                <div class="prose">
                  @for (paragraph of paragraphs(block.text); track $index) {
                    <p>{{ paragraph }}</p>
                  }
                </div>
              } @else if (block.type === 'video') {
                <div class="film">
                  @if (block.url) {
                    <video [src]="mediaUrl(block.url)" controls playsinline></video>
                  }
                </div>
              } @else if (block.type === 'document') {
                <div class="panel" style="padding: 14px">
                  <p>{{ block.name }}</p>
                  <a class="btn" [href]="mediaUrl(block.url)" target="_blank" rel="noopener">Open document</a>
                </div>
              } @else if (block.type === 'quiz' && block.quiz) {
                <div>
                  <h2>{{ block.quiz.title }}</h2>
                  <p class="muted">Passing {{ block.quiz.passing_score }}%@if (block.quiz.max_attempts) { · {{ block.quiz.max_attempts }} attempts }@if (block.quiz.duration_minutes) { · {{ block.quiz.duration_minutes }} min }</p>
                  @if (block.quiz.last_attempt) {
                    <p><span class="pill" [class.good]="block.quiz.last_attempt.passed" [class.warn]="!block.quiz.last_attempt.passed">{{ block.quiz.last_attempt.passed ? 'Passed' : 'Not passed' }} · {{ block.quiz.last_attempt.score }}%</span></p>
                  }
                  @for (question of block.quiz.questions; track question.id) {
                    <div class="question">
                      <strong>{{ question.prompt }}</strong>
                      @for (option of question.options; track $index; let optionIndex = $index) {
                        <button type="button" class="choice" [ngClass]="choiceClass(block.quiz, question, optionIndex)" (click)="answers[question.id] = optionIndex">
                          <span>{{ letter(optionIndex) }}</span><span>{{ option }}</span>
                        </button>
                      }
                      @if (question.explanation) {
                        <p class="muted">{{ question.explanation }}</p>
                      }
                    </div>
                  }
                  <button class="btn" type="button" [disabled]="busy" (click)="submitQuiz(block.quiz)">Submit quiz</button>
                </div>
              } @else if (block.type === 'assignment' && block.assignment) {
                <form class="stack" (ngSubmit)="submitAssignment(block.assignment)">
                  <h2>{{ block.assignment.title }}</h2>
                  <p>{{ block.assignment.instructions }}</p>
                  @if (block.assignment.submission) {
                    <p>
                      <span class="pill" [class.good]="block.assignment.submission.status === 'graded'" [class.warn]="block.assignment.submission.status !== 'graded'">
                        {{ block.assignment.submission.status }}
                        @if (block.assignment.submission.score != null) { · {{ block.assignment.submission.score }}/{{ block.assignment.max_score }} }
                      </span>
                    </p>
                  }
                  @if (block.assignment.submission?.feedback) {
                    <p>{{ block.assignment.submission.feedback }}</p>
                  }
                  <textarea [(ngModel)]="assignmentBody" name="assignmentBody" [disabled]="block.assignment.submission?.status === 'graded'" aria-label="Assignment"></textarea>
                  <button class="btn" [disabled]="busy || block.assignment.submission?.status === 'graded'">Submit assignment</button>
                </form>
              }
            </section>
          }

          <div class="actions" style="margin-top: 18px">
            <button class="btn" type="button" [disabled]="busy || lesson.completed" (click)="complete()">{{ lesson.completed ? 'Completed' : 'Mark as complete' }}</button>
            @if (data.prev) {
              <button class="btn ghost" type="button" (click)="open(data.prev.chapter, data.prev.lesson)">Previous</button>
            }
            @if (data.next) {
              <button class="btn ghost" type="button" (click)="open(data.next.chapter, data.next.lesson)">Next</button>
            }
          </div>

          @if (data.progress === 100) {
            <section class="certificate">
              <p class="kicker">Course complete</p>
              <h2>{{ api.user?.full_name }}</h2>
              <p>finished {{ data.course.title }}.</p>
              @if (data.course.enable_certification) {
                <a [routerLink]="['/courses', data.course.slug, 'certification']">Request certificate</a>
              }
            </section>
          }

          @if (api.user) {
            <section style="margin-top: 28px">
              <h2>Notes</h2>
              <textarea [(ngModel)]="notes" name="notes" aria-label="Lesson notes" (blur)="saveNotes()"></textarea>
            </section>
          }

          <section style="margin-top: 28px">
            <h2>Discussions</h2>
            @for (item of data.discussions; track item.id) {
              <article style="padding: 10px 0; border-bottom: 1px solid var(--line)">
                <strong>{{ item.full_name }}</strong>
                <p>{{ item.body }}</p>
              </article>
            }
            <form class="stack" style="margin-top: 10px" (ngSubmit)="discuss()">
              <textarea [(ngModel)]="comment" name="comment" placeholder="Ask a question about this lesson" aria-label="Comment"></textarea>
              <button class="btn small" type="submit">Comment</button>
            </form>
          </section>
        </article>
      </div>
    } @else {
      <p class="page empty">Opening lesson…</p>
    }
  `,
})
export class PlayerComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  route = inject(ActivatedRoute);
  private destroyRef = inject(DestroyRef);
  paragraphs = paragraphs;
  data: any = null;
  error = '';
  answers: any = {};
  notes = '';
  comment = '';
  assignmentBody = '';
  busy = false;
  kindLabel: any = { text: 'Reading', video: 'Video', quiz: 'Quiz', assignment: 'Assignment', document: 'Document' };

  ngOnInit() {
    this.route.paramMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.load());
  }

  get lesson() {
    return this.data?.lesson;
  }

  mediaUrl(url: string) {
    return this.api.mediaUrl(url);
  }

  letter(index: number) {
    return String.fromCharCode(65 + index);
  }

  async load() {
    this.error = '';
    const slug = this.route.snapshot.paramMap.get('slug');
    const chapter = this.route.snapshot.paramMap.get('chapter');
    const lesson = this.route.snapshot.paramMap.get('lesson');
    try {
      this.data = await this.api.api(`/learn/${slug}/${chapter}/${lesson}`);
      this.notes = this.data.notes || '';
      const assignment = this.data.lesson.blocks.find((block: any) => block.type === 'assignment');
      this.assignmentBody = assignment?.assignment?.submission?.body || '';
      this.answers = {};
    } catch (err: any) {
      this.error = err.message;
      this.data = null;
    }
  }

  open(chapter: any, lessonNumber: any) {
    const slug = this.route.snapshot.paramMap.get('slug');
    this.router.navigateByUrl(`/courses/${slug}/learn/${chapter}-${lessonNumber}`);
  }

  async complete() {
    this.busy = true;
    try {
      const result = await this.api.api(`/lessons/${this.lesson.id}/complete`, { method: 'POST' });
      this.data.progress = result.progress;
      this.data.lesson.completed = true;
      this.api.toast('Progress saved.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async submitQuiz(quiz: any) {
    this.busy = true;
    try {
      const result = await this.api.api(`/quizzes/${quiz.id}/submit`, { method: 'POST', body: { answers: this.answers } });
      this.api.toast(
        result.passed ? `Passed · ${result.score}%` : `${result.score}% · passing mark ${result.passing_score}%`,
        result.passed ? 'ok' : 'warn',
      );
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async submitAssignment(assignment: any) {
    this.busy = true;
    try {
      await this.api.api(`/assignments/${assignment.id}/submit`, { method: 'POST', body: { body: this.assignmentBody } });
      this.api.toast('Assignment submitted.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async saveNotes() {
    if (!this.api.user) return;
    try {
      await this.api.api(`/notes/${this.lesson.id}`, { method: 'POST', body: { body: this.notes } });
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async discuss() {
    if (!this.api.user) {
      this.router.navigate(['/login'], { queryParams: { next: this.router.url } });
      return;
    }
    try {
      await this.api.api('/discussions', { method: 'POST', body: { lesson_id: this.lesson.id, body: this.comment } });
      this.comment = '';
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  choiceClass(quiz: any, question: any, index: number) {
    const attempt = quiz.last_attempt;
    if (!attempt) return { on: this.answers[question.id] === index, right: false, wrong: false };
    const chosen = attempt.answers?.[String(question.id)]?.chosen === index;
    return { right: index === question.answer_index, wrong: chosen && index !== question.answer_index, on: chosen };
  }
}
