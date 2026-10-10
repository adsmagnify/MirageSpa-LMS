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
        <section class="kpis">
          <article class="panel kpi"><span class="muted">Signups, 14 days</span><strong>{{ data.signups_14d }}</strong></article>
          <article class="panel kpi"><span class="muted">Enrollments, 14 days</span><strong>{{ data.enrollments_14d }}</strong></article>
          <article class="panel kpi"><span class="muted">Quiz passes</span><strong>{{ data.quiz_passes }}/{{ data.quiz_attempts }}</strong></article>
          <article class="panel kpi"><span class="muted">Live joins</span><strong>{{ data.live_joins }}</strong></article>
        </section>

        <section class="panel" style="padding: 1rem; margin-top: 1rem">
          <div class="spread">
            <h3>Fourteen days</h3>
            <p class="legend"><span><i class="signup"></i>Signups</span><span><i class="enroll"></i>Enrollments</span></p>
          </div>
          <div class="chart" style="margin-top: 0.8rem">
            @for (point of data.series; track point.day) {
              <div class="col">
                <div class="pair">
                  <i class="signup" [style.height]="height(point.signups)" [title]="point.signups + ' signups'"></i>
                  <i class="enroll" [style.height]="height(point.enrollments)" [title]="point.enrollments + ' enrollments'"></i>
                </div>
                <small>{{ shortDay(point.day) }}</small>
              </div>
            }
          </div>
        </section>

        <section class="cards" style="margin-top: 1rem; align-items: start">
          <article class="panel table-wrap" style="padding: 0.6rem 1rem 1rem">
            <p class="kicker">Courses</p>
            <table>
              <thead><tr><th>Course</th><th>Enrolled</th><th>Avg progress</th></tr></thead>
              <tbody>
                @for (course of data.courses; track course.id) {
                  <tr>
                    <td><a [routerLink]="['/courses', course.slug]">{{ course.title }}</a><div class="muted">{{ course.status }}</div></td>
                    <td>{{ course.enrollments }}</td>
                    <td style="min-width: 120px"><div class="bar"><span [style.width]="course.avg_progress + '%'"></span></div></td>
                  </tr>
                }
              </tbody>
            </table>
          </article>
          <article class="panel" style="padding: 1rem">
            <p class="kicker">Just happened</p>
            @for (item of data.feed; track $index) {
              <p style="padding: 0.55rem 0; border-bottom: 1px solid var(--line)">
                <strong>{{ item.full_name || 'New account' }}</strong>
                {{ item.kind === 'signup' ? 'joined the academy' : 'enrolled in ' + item.course_title }}
                <span class="muted"> · {{ pretty(item.created_at) }}</span>
              </p>
            }
          </article>
        </section>

        @if (people.length) {
          <section class="panel table-wrap" style="padding: 0.6rem 1rem 1rem; margin-top: 1rem">
            <p class="kicker">Accounts</p>
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
