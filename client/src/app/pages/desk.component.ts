import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';
import { CourseTableComponent } from '../components/course-table.component';

@Component({
  selector: 'app-desk',
  imports: [FormsModule, RouterLink, CourseTableComponent],
  template: `
    @if (data) {
      <div class="desk">
        <div class="desk-main">
          <h1>{{ data.school?.name || 'School' }}</h1>
          <section class="card">
            <header class="spread">
              <h2>Courses</h2>
            </header>
            <div class="segs">
              <button type="button" [class.on]="tab === 'teaching'" (click)="tab = 'teaching'">
                Teaching <span>{{ data.teaching.length }}</span>
              </button>
              <button type="button" [class.on]="tab === 'enrolled'" (click)="tab = 'enrolled'">
                Enrolled <span>{{ data.enrolled.length }}</span>
              </button>
            </div>
            <app-course-table [rows]="rows" (changed)="load()" />
          </section>
          <div class="widgets">
            <section class="card">
              <header class="spread"><h2>Groups</h2><a routerLink="/groups">Open</a></header>
              @if (!data.groups.length) {
                <p class="muted">You are not a member of any group.</p>
              }
              @for (group of data.groups; track group.id) {
                <a class="widget-row" [routerLink]="'/groups/' + group.id">
                  <strong>{{ group.name }}</strong>
                  <span class="muted">{{ group.members }} people</span>
                </a>
              }
              <a class="plus" routerLink="/groups">+</a>
            </section>
            <section class="card">
              <header class="spread"><h2>News</h2></header>
              @if (!data.news.length) {
                <p class="muted">The activity feed is ready for some news.</p>
              }
              @for (item of data.news; track item.id) {
                <article class="news-item">
                  <strong>{{ item.author_name }}</strong>
                  @if (item.group_name) {
                    <span class="muted"> · {{ item.group_name }}</span>
                  }
                  <p>{{ item.body }}</p>
                </article>
              }
              @if (api.canTeach()) {
                <form class="news-form" (ngSubmit)="post()">
                  <input [(ngModel)]="message" name="message" placeholder="Post a message" required />
                  <button class="btn small" type="submit">Post</button>
                </form>
              }
            </section>
          </div>
        </div>
        <aside class="rail">
          <section class="card mini-cal">
            <header class="spread">
              <strong>{{ month.toLocaleString(undefined, { month: 'long', year: 'numeric' }) }}</strong>
              <a routerLink="/calendar">full calendar</a>
            </header>
            <div class="cal-grid">
              @for (label of dows; track $index) {
                <span class="dow">{{ label }}</span>
              }
              @for (day of days; track $index) {
                <span [class.today]="day === month.getDate()">{{ day || '' }}</span>
              }
            </div>
          </section>
          <button class="promo" type="button" (click)="api.ui.assistant = true">
            <span class="promo-mark">&lt;/&gt;</span>
            <strong>See the Student Assistant in action</strong>
            <p>Get a direct answer for courses, groups, grading, resources, and trash.</p>
          </button>
          <section class="card promo-copy">
            <h3>What’s new</h3>
            <p>Copy a course when a new class starts, post news to a group, and restore anything you move to trash.</p>
            <a routerLink="/help">See all updates</a>
          </section>
          <section class="card promo-copy help-card">
            <h3>Need help?</h3>
            <p>Help walks through assigning a class, uploading a resource, and reading a report.</p>
            <a routerLink="/help">Open help</a>
          </section>
        </aside>
      </div>
    } @else {
      <p class="page muted">Loading the school desk…</p>
    }
  `,
})
export class DeskComponent implements OnInit {
  api = inject(ApiService);
  data: any = null;
  tab = 'teaching';
  message = '';
  month = new Date();
  dows = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

  ngOnInit() {
    this.load();
  }

  get rows() {
    if (!this.data) return [];
    return this.tab === 'enrolled' ? this.data.enrolled : this.data.teaching;
  }

  get days() {
    const year = this.month.getFullYear();
    const index = this.month.getMonth();
    const first = new Date(year, index, 1).getDay();
    const count = new Date(year, index + 1, 0).getDate();
    const cells: any[] = Array.from({ length: first }, () => null);
    for (let day = 1; day <= count; day += 1) cells.push(day);
    return cells;
  }

  async load() {
    this.data = await this.api.api('/desk');
    if (!this.api.canTeach()) this.tab = 'enrolled';
  }

  async post() {
    try {
      await this.api.api('/feed', { method: 'POST', body: { body: this.message } });
      this.message = '';
      this.api.toast('Posted to news.');
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
