import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-profile',
  imports: [RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <p class="kicker">{{ data.profile.frappe_role }}</p>
        <h1>{{ data.profile.full_name }}</h1>
        <p class="dek">{{ data.profile.headline }}</p>
        @if (data.profile.email) {
          <p class="muted">{{ data.profile.email }}</p>
        }

        <h2 style="margin-top: 24px">Courses</h2>
        @for (course of data.courses; track course.slug) {
          <a class="panel spread" style="padding: 12px; margin-top: 8px" [routerLink]="['/courses', course.slug]">
            <span>{{ course.title }}</span><span>{{ course.progress }}%</span>
          </a>
        }
        @if (!data.courses.length) {
          <p class="empty">No enrollments.</p>
        }

        <h2 style="margin-top: 24px">Certificates</h2>
        @for (item of data.certificates; track item.slug) {
          <p>{{ item.title }} · {{ item.issue_date.slice(0, 10) }}</p>
        }
        @if (!data.certificates.length) {
          <p class="empty">None yet.</p>
        }

        @if (data.requests?.length) {
          <section style="margin-top: 24px">
            <h2>Evaluation requests</h2>
            @for (request of data.requests; track request.id) {
              <article class="panel" style="padding: 12px; margin-top: 8px">
                <strong>{{ request.student_name }}</strong> · {{ request.course_title }} · {{ request.status }}
                @if (request.status === 'scheduled' && api.user?.id === data.profile.id) {
                  <div class="actions" style="margin-top: 8px">
                    <button class="btn small" type="button" (click)="decide(request, true)">Pass</button>
                    <button class="btn small ghost" type="button" (click)="decide(request, false)">Fail</button>
                  </div>
                }
              </article>
            }
          </section>
        }
      </div>
    }
  `,
})
export class ProfileComponent implements OnInit, OnDestroy {
  api = inject(ApiService);
  route = inject(ActivatedRoute);
  data: any = null;
  private username = '';
  private paramsSub: any;

  ngOnInit() {
    this.load();
    this.paramsSub = this.route.paramMap.subscribe(() => {
      const username = this.route.snapshot.paramMap.get('username') || '';
      if (username !== this.username) this.load();
    });
  }

  ngOnDestroy() {
    this.paramsSub?.unsubscribe();
  }

  async load() {
    this.username = this.route.snapshot.paramMap.get('username') || '';
    this.data = await this.api.api(`/profiles/${this.username}`);
  }

  async decide(request: any, passed: boolean) {
    try {
      await this.api.api(`/certificates/${request.id}/decide`, { method: 'POST', body: { passed } });
      this.api.toast(passed ? 'Certificate issued.' : 'Marked as not passed.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
