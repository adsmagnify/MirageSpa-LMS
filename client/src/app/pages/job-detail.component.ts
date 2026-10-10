import { Component, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-job-detail',
  imports: [RouterLink, FormsModule],
  template: `
    @if (data) {
      <div class="page">
        <p class="crumbs"><a routerLink="/job-openings">Job Openings</a><span>/</span><span>{{ data.job.title }}</span></p>
        <p class="kicker">{{ data.job.company }} · {{ data.job.job_type }}</p>
        <h1>{{ data.job.title }}</h1>
        <p class="dek">{{ data.job.location }} · {{ data.job.applicants }} applications</p>
        <p style="margin: 16px 0">{{ data.job.description }}</p>
        @if (data.applied) {
          <p class="pill good">Applied</p>
        } @else {
          <form class="stack" style="max-width: 560px" (ngSubmit)="apply()">
            <label class="field"><span>Note</span><textarea [(ngModel)]="note" name="note"></textarea></label>
            <button class="btn" type="submit">Apply</button>
          </form>
        }
        @if (api.isStaff()) {
          <section style="margin-top: 24px">
            <h2>Applications</h2>
            @for (item of data.applications; track item.id) {
              <article class="panel" style="padding: 12px; margin-top: 8px">
                <strong>{{ item.full_name }}</strong>
                <p class="muted">{{ item.email }}</p>
                <p>{{ item.note }}</p>
              </article>
            }
            @if (!data.applications.length) {
              <p class="empty">No applications yet.</p>
            }
          </section>
        }
      </div>
    }
  `,
})
export class JobDetailComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  route = inject(ActivatedRoute);
  data: any = null;
  note = '';

  ngOnInit() {
    this.load();
  }

  async load() {
    this.data = await this.api.api(`/jobs/${this.route.snapshot.paramMap.get('id')}`);
  }

  async apply() {
    if (!this.api.user) {
      await this.router.navigate(['/login'], { queryParams: { next: this.router.url } });
      return;
    }
    try {
      await this.api.api(`/jobs/${this.route.snapshot.paramMap.get('id')}/apply`, { method: 'POST', body: { note: this.note } });
      this.api.toast('Application sent.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
