import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-library',
  imports: [FormsModule],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <h1>Resources</h1>
          <p class="dek">Upload videos, documents, and quizzes here. Place a material into a course when a class needs it.</p>
        </div>
      </header>

      <div class="filters" style="margin-bottom: 16px">
        <button class="chip" [class.on]="filter === 'all'" type="button" (click)="filter = 'all'">All</button>
        <button class="chip" [class.on]="filter === 'video'" type="button" (click)="filter = 'video'">Videos</button>
        <button class="chip" [class.on]="filter === 'document'" type="button" (click)="filter = 'document'">Documents</button>
        <button class="chip" [class.on]="filter === 'quiz'" type="button" (click)="filter = 'quiz'">Quizzes</button>
      </div>

      <div class="cards" style="align-items: start">
        @if (filter !== 'quiz') {
          <form class="panel stack" style="padding: 16px" (ngSubmit)="sendFile()">
            <h2>Upload a file</h2>
            <label class="field">
              <span>Kind</span>
              <select [(ngModel)]="upload.kind" name="kind">
                <option value="document">Document</option>
                <option value="video">Video</option>
              </select>
            </label>
            <label class="field"><span>Title</span><input [(ngModel)]="upload.title" name="title" placeholder="What learners will see" /></label>
            <label class="field">
              <span>{{ upload.kind === 'video' ? 'Video file' : 'Document' }}</span>
              @for (slot of [upload.kind + '-' + fileKey]; track slot) {
                <input type="file" [accept]="upload.kind === 'video' ? '.mp4,.webm,.mov,.m4v' : '.pdf,.txt,.doc,.docx,.ppt,.pptx'" required (change)="onFile($event)" />
              }
            </label>
            <button class="btn" type="submit" [disabled]="busy">Add to library</button>
          </form>
        } @else {
          <form class="panel stack" style="padding: 16px" (ngSubmit)="sendQuiz()">
            <h2>Write a quiz</h2>
            <label class="field"><span>Title</span><input [(ngModel)]="quiz.title" name="quizTitle" required /></label>
            <label class="field"><span>Passing score</span><input [(ngModel)]="quiz.passing_score" name="passingScore" type="number" min="1" max="100" /></label>
            @for (question of quiz.questions; track $index; let qIndex = $index) {
              <article class="stack">
                <label class="field"><span>Question {{ qIndex + 1 }}</span><input [(ngModel)]="question.prompt" name="prompt-{{ qIndex }}" required /></label>
                @for (option of question.options; track $index; let oIndex = $index) {
                  <div class="choice-row">
                    <input type="radio" name="lib-{{ qIndex }}" [checked]="question.answer_index === oIndex" (change)="question.answer_index = oIndex" [attr.aria-label]="'Correct choice ' + (oIndex + 1)" />
                    <input [(ngModel)]="question.options[oIndex]" name="option-{{ qIndex }}-{{ oIndex }}" [placeholder]="'Choice ' + (oIndex + 1)" />
                  </div>
                }
                <button class="btn small ghost" type="button" (click)="addChoice(question)">Add a choice</button>
              </article>
            }
            <button class="btn small ghost" type="button" (click)="addQuestion()">Add a question</button>
            <button class="btn" type="submit" [disabled]="busy">Add quiz</button>
          </form>
        }

        <section class="stack">
          @for (item of visible; track item.id) {
            <article class="panel stack" style="padding: 14px">
              <div class="spread">
                <div>
                  <p class="kicker">{{ labels[item.kind] }}@if (item.original_name) { · {{ item.original_name }} }@if (item.question_count) { · {{ item.question_count }} questions }</p>
                  <h3>{{ item.title }}</h3>
                  <p class="muted">{{ item.owner_name }}@if (item.size) { · {{ bytes(item.size) }} }</p>
                </div>
                @if (api.isAdmin() || api.user?.id === item.owner_id) {
                  <button class="btn small ghost" type="button" (click)="remove(item)">Remove</button>
                }
              </div>
              @if (placing !== item.id) {
                <button class="btn small" type="button" (click)="placing = item.id">Add to a course</button>
              } @else {
                <form class="stack" (ngSubmit)="place(item)">
                  <label class="field">
                    <span>Course</span>
                    <select [(ngModel)]="target.course_id" name="course" required (ngModelChange)="chooseCourse($event)">
                      <option [ngValue]="''" disabled>Choose a course</option>
                      @for (course of courses; track course.id) {
                        <option [ngValue]="course.id">{{ course.title }}</option>
                      }
                    </select>
                  </label>
                  <label class="field">
                    <span>Chapter</span>
                    <select [(ngModel)]="target.chapter_id" name="chapter" required>
                      @for (chapter of chapters; track chapter.id) {
                        <option [ngValue]="chapter.id">{{ chapter.title }}</option>
                      }
                    </select>
                  </label>
                  @if (target.course_id && !chapters.length) {
                    <p class="muted">Add a chapter in the course studio first.</p>
                  }
                  <div class="actions">
                    <button class="btn small" type="submit" [disabled]="!target.chapter_id">Place in chapter</button>
                    <button class="btn small ghost" type="button" (click)="placing = null">Cancel</button>
                  </div>
                </form>
              }
            </article>
          }
          @if (!visible.length) {
            <p class="empty">Nothing in this part of the library yet.</p>
          }
        </section>
      </div>
    </div>
  `,
})
export class LibraryComponent implements OnInit {
  api = inject(ApiService);
  items: any[] = [];
  courses: any[] = [];
  chapters: any[] = [];
  filter = 'all';
  busy = false;
  file: any = null;
  fileKey = 0;
  upload: any = { title: '', kind: 'document' };
  quiz: any = {
    title: '',
    passing_score: 70,
    questions: [{ prompt: '', options: ['', ''], answer_index: 0, explanation: '' }],
  };
  placing: any = null;
  target: any = { course_id: '', chapter_id: '' };
  labels: any = { video: 'Video', document: 'Document', quiz: 'Quiz' };

  get visible() {
    return this.filter === 'all' ? this.items : this.items.filter((item) => item.kind === this.filter);
  }

  ngOnInit() {
    this.load();
  }

  bytes(size: number) {
    if (!size) return '';
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${Math.round(size / 1024)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }

  async load() {
    this.items = await this.api.api('/library');
    this.courses = await this.api.api('/studio/courses');
  }

  onFile(event: any) {
    this.file = event.target.files?.[0] || null;
    if (this.file && !this.upload.title) this.upload.title = this.file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
  }

  async sendFile() {
    if (!this.file) {
      this.api.toast('Choose a file.', 'warn');
      return;
    }
    const body = new FormData();
    body.append('title', this.upload.title);
    body.append('kind', this.upload.kind);
    body.append('file', this.file);
    this.busy = true;
    try {
      await this.api.api('/library', { method: 'POST', body, form: true });
      this.upload = { title: '', kind: this.upload.kind };
      this.file = null;
      this.fileKey += 1;
      this.api.toast('Added to the library.');
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async sendQuiz() {
    this.busy = true;
    try {
      await this.api.api('/library/quizzes', {
        method: 'POST',
        body: {
          title: this.quiz.title,
          passing_score: Number(this.quiz.passing_score),
          questions: this.quiz.questions.map((question: any) => ({
            ...question,
            options: question.options.map((option: string) => option.trim()).filter(Boolean),
          })),
        },
      });
      this.quiz = { title: '', passing_score: 70, questions: [{ prompt: '', options: ['', ''], answer_index: 0, explanation: '' }] };
      this.api.toast('Quiz added to the library.');
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  addChoice(question: any) {
    question.options.push('');
  }

  addQuestion() {
    this.quiz.questions.push({ prompt: '', options: ['', ''], answer_index: 0, explanation: '' });
  }

  async remove(item: any) {
    try {
      await this.api.api(`/library/${item.id}`, { method: 'DELETE' });
      this.api.toast('Removed from the library.');
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }

  async chooseCourse(courseId = this.target.course_id) {
    this.chapters = [];
    this.target.chapter_id = '';
    if (!courseId) return;
    const data = await this.api.api(`/studio/courses/${courseId}`);
    this.chapters = data.chapters;
    if (this.chapters[0]) this.target.chapter_id = this.chapters[0].id;
  }

  async place(item: any) {
    try {
      const result = await this.api.api(`/library/${item.id}/place`, { method: 'POST', body: { chapter_id: Number(this.target.chapter_id) } });
      this.api.toast(`Added to ${result.course.title}.`);
      this.placing = null;
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
