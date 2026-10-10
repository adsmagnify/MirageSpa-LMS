import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-certification',
  imports: [RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <p class="crumbs">
          <a routerLink="/courses">Courses</a><span>/</span>
          <a [routerLink]="['/courses', data.course.slug]">{{ data.course.title }}</a><span>/</span>
          <span>Certification</span>
        </p>
        <h1>Certification</h1>
        @if (data.certificate) {
          <p class="dek">Issued {{ data.certificate.issue_date.slice(0, 10) }} for {{ data.certificate.member_name || api.user.full_name }}.</p>
        } @else if (!data.course.enable_certification) {
          <p class="dek">This course does not issue a certificate.</p>
        } @else if (!data.eligible) {
          <p class="dek">Finish every lesson ({{ data.progress }}%) before you can book an evaluation.</p>
        } @else {
          <p class="dek">You are eligible. Booking places you in the evaluator's next open slot.</p>
        }

        @if (data.certificate) {
          <article class="certificate">
            <p class="kicker">LMS Certificate</p>
            <h2>{{ data.certificate.member_name || api.user.full_name }}</h2>
            <p>{{ data.course.title }}</p>
          </article>
        } @else if (data.request) {
          <article class="panel" style="padding: 14px; margin-top: 16px">
            <p class="kicker">Evaluation</p>
            <p>Status: {{ data.request.status }}</p>
            @if (data.request.starts_at) {
              <p class="muted">{{ data.request.starts_at }}</p>
            }
            @if (api.user.role !== 'student' && data.request.status === 'scheduled') {
              <div class="actions" style="margin-top: 10px">
                <button class="btn small" type="button" (click)="decide(true)">Pass and issue</button>
                <button class="btn small ghost" type="button" (click)="decide(false)">Do not pass</button>
              </div>
            }
          </article>
        } @else if (data.eligible) {
          <button class="btn" style="margin-top: 16px" type="button" (click)="request()">Book evaluation</button>
        }
      </div>
    }
  `,
})
export class CertificationComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  route = inject(ActivatedRoute);
  data: any = null;

  ngOnInit() {
    this.load();
  }

  async load() {
    if (!this.api.user) {
      await this.router.navigate(['/login'], { queryParams: { next: this.router.url } });
      return;
    }
    this.data = await this.api.api(`/courses/${this.route.snapshot.paramMap.get('slug')}/certification`);
  }

  async request() {
    try {
      await this.api.api('/certificates/request', { method: 'POST', body: { course_id: this.data.course.id } });
      this.api.toast('Evaluation booked. The next open slot is yours.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async decide(passed: boolean) {
    try {
      await this.api.api(`/certificates/${this.data.request.id}/decide`, { method: 'POST', body: { passed } });
      this.api.toast(passed ? 'Certificate issued.' : 'Marked as not passed.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
