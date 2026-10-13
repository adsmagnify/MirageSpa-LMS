import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-profile',
  imports: [RouterLink, FormsModule],
  template: `
    @if (data) {
      <div class="page">
        <p class="kicker">{{ data.profile.frappe_role }}</p>
        <h1>{{ data.profile.full_name }}</h1>
        <p class="dek">{{ data.profile.headline }}</p>
        @if (data.profile.email) {
          <p class="muted">{{ data.profile.email }}</p>
        }

        @if (own) {
          <form class="card stack" style="max-width: 520px; margin-top: 18px" (ngSubmit)="saveProfile()">
            <h2>Your details</h2>
            <label class="field"><span>Name</span><input [(ngModel)]="draft.full_name" name="full_name" required /></label>
            <label class="field"><span>Headline</span><input [(ngModel)]="draft.headline" name="headline" /></label>
            <button class="btn small" type="submit">Save</button>
          </form>
          <form class="card stack" style="max-width: 520px; margin-top: 14px" (ngSubmit)="changePassword()">
            <h2>Password</h2>
            <label class="field"><span>Current password</span><input [(ngModel)]="passwords.current" name="current" type="password" required /></label>
            <label class="field"><span>New password</span><input [(ngModel)]="passwords.next" name="next" type="password" minlength="4" required /></label>
            <button class="btn small" type="submit">Change password</button>
          </form>
        }

        @if (own && api.canTeach()) {
          <section class="card stack" style="max-width: 520px; margin-top: 14px">
            <h2>Evaluation hours</h2>
            <p class="dek">A learner can book a certificate only when an open time exists. This adds weekday hours for the next two weeks.</p>
            <button class="btn small" type="button" (click)="publishHours()">Publish hours</button>
          </section>
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
  draft = { full_name: '', headline: '' };
  passwords = { current: '', next: '' };
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
    this.draft = {
      full_name: this.data.profile.full_name || '',
      headline: this.data.profile.headline || '',
    };
  }

  get own() {
    return !!this.api.user && this.api.user.id === this.data?.profile?.id;
  }

  async saveProfile() {
    try {
      const updated = await this.api.api('/me', { method: 'PATCH', body: this.draft });
      this.api.user = updated;
      this.data.profile = { ...this.data.profile, ...updated };
      this.api.toast('Profile saved.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async changePassword() {
    try {
      await this.api.api('/me/password', { method: 'POST', body: { current_password: this.passwords.current, password: this.passwords.next } });
      this.passwords = { current: '', next: '' };
      this.api.toast('Password changed.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }

  async publishHours() {
    try {
      const result = await this.api.api('/evaluations/generate', { method: 'POST' });
      this.api.toast(result.created ? `Published ${result.created} evaluation times.` : 'Those hours are already on the calendar.');
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
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
