import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';
import { pretty as formatPretty } from '../format';

@Component({
  selector: 'app-batch',
  imports: [RouterLink, FormsModule],
  template: `
    @if (data) {
      <div class="page">
        <header class="page-head">
          <div>
            <p class="kicker">{{ data.batch.course_title }}</p>
            <h1>{{ data.batch.title }}</h1>
            <p class="dek">{{ data.batch.description }}</p>
            <p class="muted" style="margin-top: 0.45rem">
              {{ data.batch.instructor_name }} · {{ data.batch.start_date }} – {{ data.batch.end_date }} · {{ data.batch.seats_taken }}/{{ data.batch.seat_limit }}
            </p>
          </div>
          <div class="actions">
            <a class="btn ghost" [routerLink]="['/courses', data.batch.course_slug]">Course</a>
            @if (!api.user) {
              <a class="btn" routerLink="/login" [queryParams]="{ next: router.url }">Sign in to join</a>
            } @else if (!data.batch.joined) {
              <button class="btn" type="button" [disabled]="busy" (click)="join()">Join batch</button>
            } @else if (data.batch.joined) {
              <a class="btn" [routerLink]="['/courses', data.batch.course_slug, 'learn', '1-1']">Continue course</a>
            }
          </div>
        </header>

        <nav class="tabs">
          <button type="button" [class.on]="tab === 'overview'" (click)="tab = 'overview'">Overview</button>
          <button type="button" [class.on]="tab === 'dashboard'" (click)="tab = 'dashboard'">Dashboard</button>
          <button type="button" [class.on]="tab === 'classes'" (click)="tab = 'classes'">Live Classes</button>
          <button type="button" [class.on]="tab === 'announcements'" (click)="tab = 'announcements'">Announcements</button>
          <button type="button" [class.on]="tab === 'discussions'" (click)="tab = 'discussions'">Discussions</button>
        </nav>

        @if (tab === 'overview' || tab === 'classes') {
          <section class="panel" style="padding: 1rem; margin-bottom: 1rem">
            <p class="kicker">Live classes</p>
            @if (!data.live_classes.length) {
              <p class="empty">No live class on this batch yet.</p>
            }
            @for (item of data.live_classes; track item.id) {
              <p class="spread" style="padding: 0.55rem 0; border-bottom: 1px solid var(--line)">
                <span>{{ item.title }}</span>
                <span class="muted">{{ pretty(item.starts_at) }} · {{ item.status }}</span>
              </p>
            }
          </section>
        }

        @if (roster && (tab === 'overview' || tab === 'dashboard')) {
          <section class="panel table-wrap" style="padding: 0.4rem 1rem 1rem">
            <p class="kicker" style="padding-top: 0.8rem">Engagement</p>
            <table>
              <thead>
                <tr>
                  <th>Learner</th>
                  <th>Lessons</th>
                  <th>Progress</th>
                  <th>Quizzes</th>
                  <th>Live</th>
                  <th>Engagement</th>
                  <th>Last active</th>
                </tr>
              </thead>
              <tbody>
                @for (student of data.students; track student.id) {
                  <tr>
                    <td>
                      <strong>{{ student.full_name }}</strong>
                      <div class="muted">{{ student.email }}</div>
                    </td>
                    <td>{{ student.lessons_done }}/{{ student.lessons_total }}</td>
                    <td style="min-width: 110px"><div class="bar"><span [style.width]="student.progress + '%'"></span></div></td>
                    <td>{{ student.quizzes_passed }}</td>
                    <td>{{ student.live_attended }}</td>
                    <td>{{ student.engagement }}</td>
                    <td>{{ pretty(student.last_active) }}</td>
                  </tr>
                }
              </tbody>
            </table>
          </section>
        } @else if (tab === 'overview' || tab === 'dashboard') {
          <p class="empty">Join the batch to see the roster and how the cohort is moving.</p>
        }

        @if (tab === 'announcements') {
          <section class="stack">
            @if (api.canTeach()) {
              <form class="panel stack" style="padding: 14px" (ngSubmit)="postAnnouncement()">
                <input [(ngModel)]="announce.title" name="title" placeholder="Title" required />
                <textarea [(ngModel)]="announce.body" name="body" placeholder="Tell the batch" required></textarea>
                <button class="btn small" type="submit">Post announcement</button>
              </form>
            }
            @for (item of announcements; track item.id) {
              <article class="panel" style="padding: 14px">
                <h3>{{ item.title }}</h3>
                <p class="muted">{{ item.full_name }}</p>
                <p>{{ item.body }}</p>
              </article>
            }
            @if (!announcements.length) {
              <p class="empty">No announcements yet.</p>
            }
          </section>
        }

        @if (tab === 'discussions') {
          <section class="stack">
            @if (api.user) {
              <form class="panel stack" style="padding: 14px" (ngSubmit)="postComment()">
                <textarea [(ngModel)]="comment" name="comment" placeholder="Reply to the batch" required></textarea>
                <button class="btn small" type="submit">Reply</button>
              </form>
            }
            @for (item of discussions; track item.id) {
              <article class="panel" style="padding: 14px">
                <strong>{{ item.full_name }}</strong>
                <p>{{ item.body }}</p>
              </article>
            }
            @if (!discussions.length) {
              <p class="empty">No discussion yet.</p>
            }
          </section>
        }
      </div>
    } @else {
      <div class="page"><p class="empty">{{ error || 'Opening the batch…' }}</p></div>
    }
  `,
})
export class BatchComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  route = inject(ActivatedRoute);
  data: any = null;
  error = '';
  busy = false;
  tab = 'overview';
  announcements: any[] = [];
  discussions: any[] = [];
  announce: any = { title: '', body: '' };
  comment = '';

  ngOnInit() {
    this.load();
  }

  get roster() {
    if (!this.data || !this.api.user) return false;
    return this.data.batch.joined || this.api.isStaff();
  }

  pretty(value: any) {
    return formatPretty(value);
  }

  async load() {
    const id = this.route.snapshot.paramMap.get('id');
    try {
      this.data = await this.api.api(`/batches/${id}`);
      this.announcements = await this.api.api(`/batches/${id}/announcements`);
      this.discussions = await this.api.api(`/batches/${id}/discussions`);
    } catch (err: any) {
      this.error = err.message;
    }
  }

  async postAnnouncement() {
    const id = this.route.snapshot.paramMap.get('id');
    try {
      await this.api.api(`/batches/${id}/announcements`, { method: 'POST', body: this.announce });
      this.announce = { title: '', body: '' };
      this.announcements = await this.api.api(`/batches/${id}/announcements`);
      this.api.toast('Announcement posted.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async postComment() {
    const id = this.route.snapshot.paramMap.get('id');
    try {
      await this.api.api('/discussions', { method: 'POST', body: { batch_id: Number(id), body: this.comment } });
      this.comment = '';
      this.discussions = await this.api.api(`/batches/${id}/discussions`);
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async join() {
    if (!this.api.user) return;
    const id = this.route.snapshot.paramMap.get('id');
    this.busy = true;
    try {
      this.data = await this.api.api(`/batches/${id}/join`, { method: 'POST' });
      this.api.toast('You are in the batch.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }
}
