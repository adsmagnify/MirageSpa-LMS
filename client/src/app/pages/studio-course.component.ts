import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-studio-course',
  imports: [FormsModule, RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <header class="page-head">
          <div>
            <p class="kicker">Studio · {{ data.course.status }}</p>
            <h2>{{ data.course.title }}</h2>
          </div>
          <div class="actions">
            <a class="btn ghost" [routerLink]="'/learn/' + data.course.slug">Preview</a>
            <button class="btn ghost" type="button" (click)="share()">Share</button>
            @if (data.course.status !== 'published') {
              <button class="btn" type="button" (click)="publish('published')">Publish</button>
            } @else {
              <button class="btn ghost" type="button" (click)="publish('draft')">Unpublish</button>
            }
          </div>
        </header>

        <div class="cards" style="align-items: start">
          <form class="panel stack" style="padding: 1rem" (ngSubmit)="saveMeta()">
            <h3>The course</h3>
            <label class="field"><span>Title</span><input [(ngModel)]="meta.title" name="title" required /></label>
            <label class="field"><span>Summary</span><input [(ngModel)]="meta.summary" name="summary" /></label>
            <label class="field"><span>Description</span><textarea [(ngModel)]="meta.description" name="description"></textarea></label>
            <div class="row-2">
              <label class="field"><span>Category</span><input [(ngModel)]="meta.category" name="category" /></label>
              <label class="field"><span>Level</span><input [(ngModel)]="meta.level" name="level" /></label>
            </div>
            <button class="btn small" type="submit" [disabled]="busy">Save</button>
          </form>

          <div class="stack">
            <article class="panel" style="padding: 1rem">
              <h3>Outline</h3>
              @for (chapter of data.chapters; track chapter.id) {
                <div style="margin-top: 0.7rem">
                  <strong>{{ chapter.title }}</strong>
                  @for (item of chapter.lessons; track item.id) {
                    <p class="spread muted">
                      <span>{{ item.title }}</span><span>{{ item.kind }}</span>
                    </p>
                  }
                  @if (!chapter.lessons.length) {
                    <p class="muted">No lessons yet.</p>
                  }
                </div>
              }
              @if (!data.chapters.length) {
                <p class="empty">Add a chapter before the lessons.</p>
              }
              <form class="spread" style="margin-top: 0.8rem" (ngSubmit)="addChapter()">
                <input [(ngModel)]="chapterTitle" name="chapterTitle" placeholder="Chapter title" aria-label="Chapter title" required />
                <button class="btn small" type="submit">Add</button>
              </form>
            </article>
          </div>
        </div>

        <form class="panel stack" style="padding: 1rem; margin-top: 1rem" (ngSubmit)="addLesson()">
          <h3>Add a lesson</h3>
          <div class="row-3">
            <label class="field">
              <span>Chapter</span>
              <select [(ngModel)]="lesson.chapter_id" name="chapter" required>
                @for (chapter of data.chapters; track chapter.id) {
                  <option [ngValue]="chapter.id">{{ chapter.title }}</option>
                }
              </select>
            </label>
            <label class="field">
              <span>Type</span>
              <select [(ngModel)]="lesson.kind" name="kind">
                <option value="text">Reading</option>
                <option value="video">Film</option>
                <option value="quiz">Quiz</option>
                <option value="assignment">Assignment</option>
              </select>
            </label>
            <label class="field"><span>Minutes</span><input [(ngModel)]="lesson.minutes" name="minutes" type="number" min="1" /></label>
          </div>
          <label class="field"><span>Title</span><input [(ngModel)]="lesson.title" name="lessonTitle" required /></label>
          <label class="field"><span>Body</span><textarea [(ngModel)]="lesson.body" name="body"></textarea></label>
          @if (lesson.kind === 'video') {
            <label class="field"><span>Video URL</span><input [(ngModel)]="lesson.video_url" name="videoUrl" placeholder="https://" /></label>
          }
          @if (lesson.kind === 'quiz') {
            <label class="field"><span>Passing score</span><input [(ngModel)]="lesson.passing_score" name="passingScore" type="number" min="1" max="100" /></label>
            @for (question of lesson.questions; track $index; let qIndex = $index) {
              <article class="stack">
                <label class="field"><span>Question {{ qIndex + 1 }}</span><input [(ngModel)]="question.prompt" name="prompt-{{ qIndex }}" required /></label>
                @for (option of question.options; track $index; let oIndex = $index) {
                  <div class="choice-row">
                    <input type="radio" name="answer-{{ qIndex }}" [checked]="question.answer_index === oIndex" (change)="question.answer_index = oIndex" [attr.aria-label]="'Correct choice ' + (oIndex + 1)" />
                    <input [(ngModel)]="question.options[oIndex]" name="option-{{ qIndex }}-{{ oIndex }}" [placeholder]="'Choice ' + (oIndex + 1)" />
                  </div>
                }
                <button class="btn small ghost" type="button" (click)="addChoice(question)">Add a choice</button>
                <label class="field"><span>Why this answer</span><input [(ngModel)]="question.explanation" name="explanation-{{ qIndex }}" /></label>
              </article>
            }
            <button class="btn small ghost" type="button" (click)="addQuestion()">Add a question</button>
          }
          @if (lesson.kind === 'assignment') {
            <label class="field"><span>Instructions</span><textarea [(ngModel)]="lesson.instructions" name="instructions"></textarea></label>
            <label class="field"><span>Max score</span><input [(ngModel)]="lesson.max_score" name="maxScore" type="number" min="1" /></label>
          }
          <button class="btn" type="submit" [disabled]="busy || !data.chapters.length">Add lesson</button>
        </form>
      </div>
    } @else {
      <div class="page"><p class="empty">{{ error || 'Opening the studio…' }}</p></div>
    }
  `,
})
export class StudioCourseComponent implements OnInit {
  api = inject(ApiService);
  route = inject(ActivatedRoute);
  data: any = null;
  error = '';
  busy = false;
  meta: any = { title: '', summary: '', description: '', category: '', level: '' };
  chapterTitle = '';
  lesson: any = {
    chapter_id: '',
    title: '',
    kind: 'text',
    minutes: 8,
    body: '',
    video_url: '',
    passing_score: 70,
    instructions: '',
    max_score: 100,
    questions: [{ prompt: '', options: ['', ''], answer_index: 0, explanation: '' }],
  };

  ngOnInit() {
    this.load();
  }

  async load() {
    try {
      this.data = await this.api.api(`/studio/courses/${this.route.snapshot.paramMap.get('id')}`);
      Object.assign(this.meta, this.data.course);
      if (!this.lesson.chapter_id && this.data.chapters[0]) this.lesson.chapter_id = this.data.chapters[0].id;
    } catch (err: any) {
      this.error = err.message;
    }
  }

  async saveMeta() {
    this.busy = true;
    try {
      this.data.course = await this.api.api(`/studio/courses/${this.route.snapshot.paramMap.get('id')}`, { method: 'PATCH', body: { ...this.meta } });
      this.api.toast('Course saved.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async publish(status: string) {
    try {
      this.data.course = await this.api.api(`/studio/courses/${this.route.snapshot.paramMap.get('id')}`, { method: 'PATCH', body: { status } });
      this.api.toast(status === 'published' ? 'Published. The link is live.' : 'Moved back to draft.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async share() {
    if (this.data.course.status !== 'published') {
      this.api.toast('Publish the course before sharing.', 'warn');
      return;
    }
    const url = `${location.origin}/courses/${this.data.course.slug}`;
    try {
      await navigator.clipboard.writeText(url);
      this.api.toast('Link copied.');
    } catch {
      this.api.toast(url);
    }
  }

  async addChapter() {
    try {
      await this.api.api('/studio/chapters', {
        method: 'POST',
        body: { course_id: Number(this.route.snapshot.paramMap.get('id')), title: this.chapterTitle },
      });
      this.chapterTitle = '';
      this.api.toast('Chapter added.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async addLesson() {
    const body: any = {
      chapter_id: Number(this.lesson.chapter_id),
      title: this.lesson.title,
      kind: this.lesson.kind,
      minutes: Number(this.lesson.minutes),
      body: this.lesson.body,
      video_url: this.lesson.video_url,
    };
    if (this.lesson.kind === 'quiz') {
      body.quiz = {
        title: this.lesson.title,
        passing_score: Number(this.lesson.passing_score),
        questions: this.lesson.questions.map((question: any) => ({
          ...question,
          options: question.options.map((option: string) => option.trim()).filter(Boolean),
        })),
      };
    }
    if (this.lesson.kind === 'assignment') {
      body.assignment = { title: this.lesson.title, instructions: this.lesson.instructions || this.lesson.body, max_score: Number(this.lesson.max_score) };
    }
    this.busy = true;
    try {
      await this.api.api('/studio/lessons', { method: 'POST', body });
      this.lesson.title = '';
      this.lesson.body = '';
      this.api.toast('Lesson added.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  addChoice(question: any) {
    question.options.push('');
  }

  addQuestion() {
    this.lesson.questions.push({ prompt: '', options: ['', ''], answer_index: 0, explanation: '' });
  }
}
