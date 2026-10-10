import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';
import { CourseTableComponent } from '../components/course-table.component';

@Component({
  selector: 'app-courses',
  imports: [RouterLink, CourseTableComponent],
  template: `
    @if (data) {
      <div class="page desk-page">
        <header class="spread">
          <div>
            <h1>Courses</h1>
            <p class="dek">Teaching is every class you run. Enrolled is every class assigned to you.</p>
          </div>
          @if (api.canTeach()) {
            <a class="btn" routerLink="/courses/new">New course</a>
          }
        </header>
        <section class="card">
          <div class="segs">
            <button type="button" [class.on]="tab === 'teaching'" (click)="tab = 'teaching'">Teaching <span>{{ data.teaching.length }}</span></button>
            <button type="button" [class.on]="tab === 'enrolled'" (click)="tab = 'enrolled'">Enrolled <span>{{ data.enrolled.length }}</span></button>
          </div>
          <app-course-table [rows]="tab === 'enrolled' ? data.enrolled : data.teaching" (changed)="load()" />
        </section>
      </div>
    }
  `,
})
export class CoursesComponent implements OnInit {
  api = inject(ApiService);
  data: any = null;
  tab = 'teaching';

  ngOnInit() {
    this.load();
  }

  async load() {
    this.data = await this.api.api('/desk');
    if (!this.api.canTeach()) this.tab = 'enrolled';
  }
}
