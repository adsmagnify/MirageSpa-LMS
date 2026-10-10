import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-classes',
  imports: [RouterLink],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <h1>My Classes</h1>
          <p class="dek">These are the courses and batches assigned to you.</p>
        </div>
      </header>
      @if (error) {
        <p class="empty">{{ error }}</p>
      }
      <section>
        @for (course of data.courses; track course.id) {
          <article class="panel spread" style="padding: 14px; margin-bottom: 8px">
            <div>
              <p class="kicker">{{ course.instructor_name }}</p>
              <a [routerLink]="['/courses', course.slug]"><strong>{{ course.title }}</strong></a>
            </div>
            <span>{{ course.progress }}%</span>
          </article>
        }
        @for (batch of data.batches; track batch.id) {
          <article class="panel" style="padding: 14px; margin-bottom: 8px">
            <p class="kicker">Batch · {{ batch.course_title }}</p>
            <a [routerLink]="['/batches', batch.id]"><strong>{{ batch.title }}</strong></a>
          </article>
        }
        @if (!data.courses.length && !data.batches.length) {
          <p class="empty">No classes have been assigned to you yet.</p>
        }
      </section>
    </div>
  `,
})
export class ClassesComponent implements OnInit {
  api = inject(ApiService);
  data: any = { courses: [], batches: [] };
  error = '';

  async ngOnInit() {
    try {
      this.data = await this.api.api('/me/classes');
    } catch (err: any) {
      this.error = err.message;
    }
  }
}
