import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-course-table',
  imports: [RouterLink, FormsModule],
  template: `
    <div class="course-table">
      <div class="table-head">
        <span>#</span>
        <span class="name-head">Name <input [(ngModel)]="q" (ngModelChange)="page = 1" aria-label="Filter courses" placeholder="Search" /></span>
        <span>To score</span>
        <span>Learners</span>
        <span>Completed</span>
        <span>Deactivated</span>
      </div>
      @for (row of visible(); track row.id; let index = $index) {
        <article class="course-row">
          <span class="row-no">{{ (currentPage() - 1) * pageSize + index + 1 }}</span>
          <a class="course-name" [routerLink]="['/courses', row.slug]">
            <span class="thumb" [style.background]="tone(row.title)">{{ letter(row.title) }}</span>
            <span>
              <strong>{{ row.title }}</strong>
              <small>{{ when(row) }}@if (row.product_code) { · {{ row.product_code }} }</small>
              @if (row.category) {
                <small>{{ row.category }}@if (row.instructor_name) { · {{ row.instructor_name }} }@if (row.progress != null) { · {{ row.progress }}% }</small>
              }
            </span>
          </a>
          <a class="metric" data-label="To score" routerLink="/assignments">{{ dash(row.to_score) }}</a>
          <span class="metric" data-label="Learners">{{ dash(row.learners) }}</span>
          <span class="metric" data-label="Completed">{{ dash(row.completed) }}</span>
          <span class="metric" data-label="Deactivated">{{ dash(row.deactivated) }}</span>
          @if (row.can_edit) {
            <div class="row-menu">
              <button type="button" aria-label="Course actions" (click)="openMenu = openMenu === row.id ? null : row.id">⋯</button>
              @if (openMenu === row.id) {
                <div class="pop menu">
                  <button type="button" (click)="copy(row)">Copy course</button>
                  <button type="button" (click)="trash(row)">Move to trash</button>
                </div>
              }
            </div>
          }
        </article>
      }
      @if (!filtered().length) {
        <p class="empty">No courses in this list yet.</p>
      }
      @if (pageCount() > 1) {
        <div class="pager">
          @for (n of pages(); track n) {
            <button type="button" [class.on]="n === currentPage()" (click)="page = n">{{ n }}</button>
          }
          @if (page < pageCount()) {
            <button type="button" (click)="page = page + 1">Next</button>
          }
        </div>
      }
    </div>
  `,
})
export class CourseTableComponent {
  @Input() rows: any[] = [];
  @Output() changed = new EventEmitter<void>();
  private api = inject(ApiService);
  q = '';
  page = 1;
  openMenu: number | null = null;
  pageSize = 6;

  filtered() {
    const needle = this.q.trim().toLowerCase();
    if (!needle) return this.rows || [];
    return (this.rows || []).filter((row) => `${row.title} ${row.category} ${row.instructor_name}`.toLowerCase().includes(needle));
  }

  pageCount() {
    return Math.max(1, Math.ceil(this.filtered().length / this.pageSize));
  }

  currentPage() {
    return Math.min(this.page, this.pageCount());
  }

  visible() {
    const current = this.currentPage();
    return this.filtered().slice((current - 1) * this.pageSize, current * this.pageSize);
  }

  pages() {
    return Array.from({ length: this.pageCount() }, (_, index) => index + 1);
  }

  dash(value: any) {
    return value ? value : '—';
  }

  when(row: any) {
    const fmt = (value: string) => {
      if (!value) return '';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    };
    const start = fmt(row.starts_on || row.created_at);
    const end = fmt(row.ends_on || row.created_at);
    if (!start) return '';
    return start === end ? start : `${start} – ${end}`;
  }

  letter(title: string) {
    return (title || '?').trim().charAt(0).toUpperCase();
  }

  tone(title: string) {
    const tones = ['#dbeafe', '#fce7f3', '#dcfce7', '#fef3c7', '#e0e7ff', '#ffedd5'];
    let n = 0;
    for (const ch of title || '') n += ch.charCodeAt(0);
    return tones[n % tones.length];
  }

  async copy(row: any) {
    this.openMenu = null;
    try {
      const result = await this.api.api(`/courses/${row.id}/copy`, { method: 'POST' });
      this.api.toast(`${result.course.title} is ready as a draft.`);
      this.changed.emit();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }

  async trash(row: any) {
    this.openMenu = null;
    try {
      await this.api.api(`/courses/${row.id}/trash`, { method: 'POST' });
      this.api.toast('Moved to trash.');
      this.changed.emit();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
