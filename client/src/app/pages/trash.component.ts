import { Component, OnInit, inject } from '@angular/core';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-trash',
  imports: [],
  template: `
    @if (data) {
      <div class="page">
        <header class="page-head">
          <div>
            <h1>Trash</h1>
            <p class="dek">Restore a course or resource, or remove it permanently.</p>
          </div>
        </header>
        <section class="card stack">
          <h2>Courses</h2>
          @for (course of data.courses; track course.id) {
            <article class="spread">
              <span><strong>{{ course.title }}</strong><br /><span class="muted">{{ course.deleted_at?.slice(0, 10) }}</span></span>
              <span class="actions">
                <button class="btn small" type="button" (click)="run('/trash/courses/' + course.id + '/restore', 'POST', 'Course restored.')">Restore</button>
                <button class="btn small ghost" type="button" (click)="run('/trash/courses/' + course.id, 'DELETE', 'Course removed.')">Remove</button>
              </span>
            </article>
          }
          @if (!data.courses.length) {
            <p class="muted">No courses in the trash.</p>
          }
        </section>
        <section class="card stack" style="margin-top: 16px">
          <h2>Resources</h2>
          @for (item of data.resources; track item.id) {
            <article class="spread">
              <span><strong>{{ item.title }}</strong> <span class="muted">{{ item.kind }}</span></span>
              <span class="actions">
                <button class="btn small" type="button" (click)="run('/trash/resources/' + item.id + '/restore', 'POST', 'Resource restored.')">Restore</button>
                <button class="btn small ghost" type="button" (click)="run('/trash/resources/' + item.id, 'DELETE', 'Resource removed.')">Remove</button>
              </span>
            </article>
          }
          @if (!data.resources.length) {
            <p class="muted">No resources in the trash.</p>
          }
        </section>
      </div>
    }
  `,
})
export class TrashComponent implements OnInit {
  api = inject(ApiService);
  data: any = null;

  ngOnInit() {
    this.load();
  }

  async load() {
    this.data = await this.api.api('/trash');
  }

  async run(path: string, method: string, ok: string) {
    try {
      await this.api.api(path, { method });
      this.api.toast(ok);
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
