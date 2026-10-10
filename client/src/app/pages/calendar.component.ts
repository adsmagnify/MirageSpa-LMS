import { Component, OnInit, inject } from '@angular/core';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-calendar',
  imports: [],
  template: `
    <div class="page">
      <header class="spread">
        <h1>Calendar</h1>
        <div class="actions">
          <button class="btn small ghost" type="button" (click)="shift(-1)">Previous</button>
          <strong>{{ label }}</strong>
          <button class="btn small ghost" type="button" (click)="shift(1)">Next</button>
        </div>
      </header>
      <section class="card month">
        <div class="month-grid">
          @for (name of dows; track name) {
            <span class="dow">{{ name }}</span>
          }
          @for (day of cells; track $index) {
            <div class="month-day">
              <strong>{{ day || '' }}</strong>
              @for (item of eventsOn(day); track $index) {
                <small>{{ item }}</small>
              }
            </div>
          }
        </div>
      </section>
      @if (data) {
        <section class="card stack" style="margin-top: 16px">
          <h2>Coming up</h2>
          @for (item of data.live; track item.id) {
            <p><strong>{{ item.title }}</strong> <span class="muted">{{ item.starts_at?.replace('T', ' ').slice(0, 16) }}</span></p>
          }
          @for (item of data.batches; track item.id) {
            <p><strong>{{ item.title }}</strong> <span class="muted">{{ item.start_date || 'Dates not set' }} · {{ item.course_title }}</span></p>
          }
          @if (!data.live.length && !data.batches.length) {
            <p class="muted">Nothing is scheduled yet. Live classes and batches appear here.</p>
          }
        </section>
      }
    </div>
  `,
})
export class CalendarComponent implements OnInit {
  api = inject(ApiService);
  data: any = null;
  cursor = new Date();
  dows = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  ngOnInit() {
    this.load();
  }

  get label() {
    return this.cursor.toLocaleString(undefined, { month: 'long', year: 'numeric' });
  }

  get cells() {
    const year = this.cursor.getFullYear();
    const month = this.cursor.getMonth();
    const first = new Date(year, month, 1).getDay();
    const count = new Date(year, month + 1, 0).getDate();
    const days: any[] = Array.from({ length: first }, () => null);
    for (let day = 1; day <= count; day += 1) days.push(day);
    return days;
  }

  async load() {
    this.data = await this.api.api('/calendar');
  }

  eventsOn(day: any) {
    if (!this.data || !day) return [];
    const year = this.cursor.getFullYear();
    const month = this.cursor.getMonth();
    const key = new Date(year, month, day).toISOString().slice(0, 10);
    const items: any[] = [];
    for (const item of this.data.live) if ((item.starts_at || '').slice(0, 10) === key) items.push(item.title);
    for (const item of this.data.batches) if ((item.start_date || '').slice(0, 10) === key) items.push(item.title);
    for (const item of this.data.evaluations) if ((item.starts_at || '').slice(0, 10) === key) items.push('Evaluation');
    return items;
  }

  shift(amount: number) {
    this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + amount, 1);
  }
}
