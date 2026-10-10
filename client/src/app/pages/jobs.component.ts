import { Component, OnDestroy, OnInit, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-jobs',
  imports: [RouterLink, FormsModule],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <p class="kicker">Job board</p>
          <h1>Job Openings</h1>
          <p class="dek">Posted to people already studying, not to the open internet first.</p>
        </div>
        <div class="actions">
          <input [(ngModel)]="query" name="query" class="search" type="search" placeholder="Search roles" aria-label="Search roles" (ngModelChange)="onQuery()" />
          @if (api.isStaff()) {
            <button class="btn" type="button" (click)="open = !open">Post a role</button>
          }
        </div>
      </header>

      @if (open) {
        <form class="panel stack" style="padding: 1rem; margin-bottom: 1rem" (ngSubmit)="post()">
          <div class="row-2">
            <label class="field"><span>Title</span><input [(ngModel)]="form.title" name="title" required /></label>
            <label class="field"><span>Company</span><input [(ngModel)]="form.company" name="company" required /></label>
          </div>
          <div class="row-2">
            <label class="field"><span>Location</span><input [(ngModel)]="form.location" name="location" /></label>
            <label class="field">
              <span>Type</span>
              <select [(ngModel)]="form.job_type" name="job_type">
                <option>Full-time</option>
                <option>Part-time</option>
                <option>Contract</option>
              </select>
            </label>
          </div>
          <label class="field"><span>The work</span><textarea [(ngModel)]="form.description" name="description"></textarea></label>
          <button class="btn" type="submit" [disabled]="posting">Publish</button>
        </form>
      }

      @for (job of jobs; track job.id) {
        <article class="panel" style="padding: 1rem; margin-bottom: 0.7rem">
          <div class="spread">
            <div>
              <p class="kicker">{{ job.company }} · {{ job.job_type }}</p>
              <a [routerLink]="['/job-openings', job.id]"><h3>{{ job.title }}</h3></a>
              <p class="muted">{{ job.location }} · {{ job.applicants }} applied</p>
            </div>
            @if (applied.includes(job.id)) {
              <span class="pill good">Applied</span>
            }
          </div>
          <p style="margin: 0.7rem 0">{{ job.description }}</p>
          @if (!applied.includes(job.id)) {
            <div class="stack">
              @if (noteFor === job.id) {
                <textarea [(ngModel)]="note" name="note" placeholder="A short note with your name"></textarea>
              }
              <div class="actions">
                @if (noteFor !== job.id) {
                  <button class="btn small" type="button" (click)="noteFor = job.id">Put your name forward</button>
                } @else {
                  <button class="btn small" type="button" (click)="apply(job)">Send</button>
                }
              </div>
            </div>
          }
        </article>
      }
      @if (!jobs.length) {
        <p class="empty">No open roles.</p>
      }
    </div>
  `,
})
export class JobsComponent implements OnInit, OnDestroy {
  api = inject(ApiService);
  router = inject(Router);
  jobs: any[] = [];
  applied: any[] = [];
  query = '';
  open = false;
  posting = false;
  noteFor: any = null;
  note = '';
  form: any = { title: '', company: '', location: '', job_type: 'Full-time', description: '' };
  private queryTimer: any;

  ngOnInit() {
    this.load();
  }

  ngOnDestroy() {
    clearTimeout(this.queryTimer);
  }

  onQuery() {
    clearTimeout(this.queryTimer);
    this.queryTimer = setTimeout(() => this.load(), 180);
  }

  async load() {
    const params = this.query ? `?q=${encodeURIComponent(this.query)}` : '';
    this.jobs = await this.api.api(`/jobs${params}`);
    if (this.api.user) this.applied = await this.api.api('/me/applications');
  }

  async post() {
    this.posting = true;
    try {
      await this.api.api('/jobs', { method: 'POST', body: this.form });
      this.api.toast('Role posted.');
      this.form = { title: '', company: '', location: '', job_type: 'Full-time', description: '' };
      this.open = false;
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.posting = false;
    }
  }

  async apply(job: any) {
    if (!this.api.user) {
      await this.router.navigate(['/login'], { queryParams: { next: '/job-openings' } });
      return;
    }
    try {
      await this.api.api(`/jobs/${job.id}/apply`, { method: 'POST', body: { note: this.note } });
      this.api.toast('Your name is on the list.');
      this.note = '';
      this.noteFor = null;
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
