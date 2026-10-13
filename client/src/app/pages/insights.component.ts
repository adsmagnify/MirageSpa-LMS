import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';
import { pretty as formatPretty, shortDay as formatShortDay } from '../format';

@Component({
  selector: 'app-insights',
  imports: [RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <header class="page-head">
          <div>
            <p class="kicker">Insights · live</p>
            <h1>Statistics</h1>
            <p class="dek">Refreshed every few seconds. Last count {{ pretty(data.generated_at) }}.</p>
          </div>
        </header>
        <section class="stat-kpis">
          <article>
            <span>Signups</span>
            <strong>{{ data.signups_14d }}</strong>
            <small>Last 14 days</small>
          </article>
          <article>
            <span>Enrollments</span>
            <strong>{{ data.enrollments_14d }}</strong>
            <small>Last 14 days</small>
          </article>
          <article>
            <span>Quiz passes</span>
            <strong>{{ data.quiz_passes }}<em>/ {{ data.quiz_attempts }}</em></strong>
            <small>Passed of attempted</small>
          </article>
          <article>
            <span>Live joins</span>
            <strong>{{ data.live_joins }}</strong>
            <small>People who entered a class</small>
          </article>
        </section>

        <section class="panel stat-chart">
          <div class="spread">
            <h2>Fourteen days</h2>
            <p class="stat-legend"><span><i class="signup"></i>Signups</span><span><i class="enroll"></i>Enrollments</span></p>
          </div>
          <div class="stat-bars">
            @for (point of data.series; track point.day) {
              <div class="stat-col">
                <div class="stat-pair">
                  <i class="signup" [style.height]="height(point.signups)" [title]="point.signups + ' signups'"></i>
                  <i class="enroll" [style.height]="height(point.enrollments)" [title]="point.enrollments + ' enrollments'"></i>
                </div>
                <small>{{ shortDay(point.day) }}</small>
              </div>
            }
          </div>
        </section>

        <section class="stat-split">
          <article class="panel stat-table">
            <p class="kicker">Courses</p>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Course</th><th>Enrolled</th><th>Average progress</th></tr></thead>
                <tbody>
                  @for (course of data.courses; track course.id) {
                    <tr>
                      <td><a [routerLink]="['/courses', course.slug]">{{ course.title }}</a><div class="muted">{{ course.status }}</div></td>
                      <td>{{ course.enrollments }}</td>
                      <td>
                        <div class="stat-progress">
                          <div class="bar"><span [style.width]="course.avg_progress + '%'"></span></div>
                          <span>{{ course.avg_progress }}%</span>
                        </div>
                      </td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          </article>
          <article class="panel stat-feed">
            <p class="kicker">Just happened</p>
            @for (item of data.feed; track $index) {
              <p class="happened">
                <strong>{{ item.full_name || 'New account' }}</strong>
                <span>{{ item.kind === 'signup' ? 'joined the academy' : 'enrolled in ' + item.course_title }}</span>
                <small>{{ pretty(item.created_at) }}</small>
              </p>
            }
            @if (!data.feed.length) {
              <p class="empty">Nothing recorded yet.</p>
            }
          </article>
        </section>

        @if (people.length) {
          <section class="panel stat-table stat-accounts">
            <p class="kicker">Accounts</p>
            <div class="table-wrap">
            <table>
              <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
              <tbody>
                @for (person of people; track person.id) {
                  <tr>
                    <td>{{ person.full_name }}</td>
                    <td>{{ person.email }}</td>
                    <td>
                      <select [value]="person.role" [disabled]="person.id === api.user.id" (change)="setRole(person, $any($event.target).value)">
                        <option>student</option>
                        <option>instructor</option>
                        <option>moderator</option>
                        <option>admin</option>
                      </select>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
            </div>
          </section>
        }
      </div>
    } @else {
      <div class="page"><p class="empty">{{ error || 'Counting…' }}</p></div>
    }
  `,
})
export class InsightsComponent implements OnInit, OnDestroy {
  api = inject(ApiService);
  data: any = null;
  people: any[] = [];
  error = '';
  private timer: any;

  ngOnInit() {
    this.load();
    this.timer = setInterval(() => this.load(), 8000);
  }

  ngOnDestroy() {
    clearInterval(this.timer);
  }

  get max() {
    const series = this.data?.series || [];
    return Math.max(1, ...series.map((point: any) => Math.max(point.signups, point.enrollments)));
  }

  height(value: number) {
    return `${Math.max(value ? 6 : 0, (value / this.max) * 120)}px`;
  }

  pretty(value: any) {
    return formatPretty(value);
  }

  shortDay(value: any) {
    return formatShortDay(value).replace(/.*, /, '');
  }

  async load() {
    try {
      this.data = await this.api.api('/analytics');
      if (this.api.isAdmin()) this.people = await this.api.api('/users');
    } catch (err: any) {
      this.error = err.message;
    }
  }

  async setRole(person: any, role: string) {
    try {
      await this.api.api(`/users/${person.id}`, { method: 'PATCH', body: { role } });
      person.role = role;
      this.api.toast(`${person.full_name} is now ${role}.`);
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
