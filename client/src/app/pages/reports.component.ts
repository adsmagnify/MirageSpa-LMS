import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-reports',
  imports: [RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <header class="page-head">
          <div>
            <h1>Reports</h1>
            <p class="dek">Progress across the classes you teach. Completed means every lesson in that course is finished.</p>
          </div>
        </header>
        <div class="stat-row">
          <article class="card"><span>Courses</span><strong>{{ data.totals.courses }}</strong></article>
          <article class="card"><span>Learners</span><strong>{{ data.totals.learners }}</strong></article>
          <article class="card"><span>Completed</span><strong>{{ data.totals.completed }}</strong></article>
          <article class="card"><span>To score</span><strong>{{ data.totals.to_score }}</strong></article>
        </div>
        <section class="card">
          <div class="table-head report-head">
            <span>Course</span><span>Learners</span><span>Completed</span><span>Average</span><span>To score</span>
          </div>
          @for (row of data.courses; track row.id) {
            <div class="course-row report-row">
              <a [routerLink]="'/courses/' + row.slug"><strong>{{ row.title }}</strong></a>
              <span>{{ row.learners }}</span>
              <span>{{ row.completed }}</span>
              <span>{{ row.average_progress }}%</span>
              <span>{{ row.to_score }}</span>
            </div>
          }
          @if (!data.courses.length) {
            <p class="empty">No courses to report yet.</p>
          }
        </section>
      </div>
    }
  `,
})
export class ReportsComponent implements OnInit {
  api = inject(ApiService);
  data: any = null;

  ngOnInit() {
    this.load();
  }

  async load() {
    this.data = await this.api.api('/reports');
  }
}
