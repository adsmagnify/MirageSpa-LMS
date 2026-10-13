import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';
import { CourseTableComponent } from '../components/course-table.component';

@Component({
  selector: 'app-courses',
  imports: [FormsModule, RouterLink, CourseTableComponent],
  template: `
    <div class="page">
      <header class="spread">
        <div>
          <h1>Courses</h1>
          <p class="dek">Open a published class and enroll. Created is the list of courses you teach.</p>
        </div>
        @if (api.canTeach()) {
          <a class="btn" routerLink="/courses/new">Create</a>
        }
      </header>
      <div class="filters" style="margin: 8px 0 16px">
        <div class="segs">
          <button type="button" [class.on]="tab === 'live'" (click)="tab = 'live'">Live</button>
          <button type="button" [class.on]="tab === 'enrolled'" (click)="tab = 'enrolled'">Enrolled <span>{{ enrolled.length }}</span></button>
          @if (api.canTeach()) {
            <button type="button" [class.on]="tab === 'created'" (click)="tab = 'created'">Created <span>{{ teaching.length }}</span></button>
          }
        </div>
        <input [(ngModel)]="q" name="q" placeholder="Search" aria-label="Search courses" />
        <select [(ngModel)]="category" name="category" aria-label="Category">
          <option value="">All categories</option>
          @for (name of categories; track name) {
            <option [value]="name">{{ name }}</option>
          }
        </select>
        <label class="check"><input type="checkbox" [(ngModel)]="certOnly" name="cert" /> Certification</label>
      </div>

      @if (tab === 'live') {
        <div class="cards">
          @for (course of visible(); track course.id) {
            <a class="course-card" [routerLink]="['/courses', course.slug]">
              @if (course.thumbnail) {
                <img class="course-thumb" [src]="'/api/courses/' + course.id + '/thumbnail?v=' + course.thumbnail" [alt]="course.title" [style.object-position]="(course.thumb_x || 50) + '% ' + (course.thumb_y || 50) + '%'" />
              } @else {
                <div class="swatch" [attr.data-cat]="course.category"></div>
              }
              <div class="course-body">
                <strong>{{ course.title }}</strong>
                <p>{{ course.summary || course.category }}</p>
                <div class="meta">
                  <span>{{ course.instructor_name }}</span>
                  <span>{{ course.lesson_count || 0 }} lessons</span>
                </div>
              </div>
            </a>
          }
        </div>
        @if (!visible().length) {
          <p class="empty">No published courses match.</p>
        }
      } @else {
        <section class="card">
          <app-course-table [rows]="tab === 'enrolled' ? enrolled : teaching" (changed)="load()" />
        </section>
      }
    </div>
  `,
})
export class CoursesComponent implements OnInit {
  api = inject(ApiService);
  catalog: any[] = [];
  enrolled: any[] = [];
  teaching: any[] = [];
  categories: string[] = [];
  tab = 'live';
  q = '';
  category = '';
  certOnly = false;

  ngOnInit() {
    if (!this.api.canTeach()) this.tab = 'live';
    this.load();
  }

  visible() {
    const needle = this.q.trim().toLowerCase();
    return this.catalog.filter((course) => {
      if (this.category && course.category !== this.category) return false;
      if (this.certOnly && !course.enable_certification) return false;
      if (!needle) return true;
      return `${course.title} ${course.summary} ${course.category} ${course.instructor_name}`.toLowerCase().includes(needle);
    });
  }

  async load() {
    const [catalog, categories, desk] = await Promise.all([
      this.api.api('/courses'),
      this.api.api('/categories').catch(() => []),
      this.api.api('/desk').catch(() => ({ enrolled: [], teaching: [] })),
    ]);
    this.catalog = catalog;
    this.categories = categories;
    this.enrolled = desk.enrolled || [];
    this.teaching = desk.teaching || [];
  }
}
