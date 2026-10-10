import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-studio',
  imports: [FormsModule, RouterLink],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <p class="kicker">Course studio</p>
          <h2>Make something teachable</h2>
          <p class="dek">A draft stays private. Publish it, then share the course link.</p>
        </div>
      </header>
      <div class="cards" style="align-items: start">
        <form class="panel stack" style="padding: 1rem" (ngSubmit)="create()">
          <h3>New course</h3>
          <label class="field"><span>Title</span><input [(ngModel)]="form.title" name="title" required /></label>
          <label class="field"><span>Summary</span><input [(ngModel)]="form.summary" name="summary" /></label>
          <label class="field"><span>Description</span><textarea [(ngModel)]="form.description" name="description"></textarea></label>
          <div class="row-2">
            <label class="field"><span>Category</span><input [(ngModel)]="form.category" name="category" /></label>
            <label class="field"><span>Level</span><input [(ngModel)]="form.level" name="level" /></label>
          </div>
          <button class="btn" type="submit" [disabled]="busy">Create draft</button>
        </form>
        <div class="stack">
          @for (course of courses; track course.id) {
            <a class="panel" style="padding: 0.9rem; text-decoration: none" [routerLink]="['/studio', course.id]">
              <div class="spread">
                <h3>{{ course.title }}</h3>
                <span class="pill" [class.good]="course.status === 'published'" [class.warn]="course.status !== 'published'">{{ course.status }}</span>
              </div>
              <p class="muted">{{ course.lesson_count }} lessons · {{ course.learners }} enrolled</p>
            </a>
          }
          @if (!courses.length) {
            <p class="empty">No courses in your studio yet.</p>
          }
        </div>
      </div>
    </div>
  `,
})
export class StudioComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  courses: any[] = [];
  busy = false;
  form: any = {
    title: '',
    summary: '',
    description: '',
    category: 'General',
    level: 'Foundation',
  };

  ngOnInit() {
    this.load();
  }

  async load() {
    this.courses = await this.api.api('/studio/courses');
  }

  async create() {
    this.busy = true;
    try {
      const course = await this.api.api('/studio/courses', { method: 'POST', body: this.form });
      this.api.toast('Draft opened.');
      this.router.navigateByUrl(`/studio/${course.id}`);
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }
}
