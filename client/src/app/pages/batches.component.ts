import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-batches',
  imports: [RouterLink, FormsModule],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <p class="kicker">Cohorts</p>
          <h1>Batches</h1>
          <p class="dek">A batch is a course with names, dates, and a shared live room. Progress is counted per person.</p>
        </div>
        @if (api.canTeach()) {
          <button class="btn" type="button" (click)="open = !open">New batch</button>
        }
      </header>
      @if (open) {
        <form class="panel stack" style="padding: 1rem; margin-bottom: 1rem" (ngSubmit)="create()">
          <div class="row-2">
            <label class="field"><span>Name</span><input [(ngModel)]="form.title" name="title" required /></label>
            <label class="field">
              <span>Course</span>
              <select [(ngModel)]="form.course_id" name="course_id" required>
                <option value="" disabled>Choose</option>
                @for (course of courses; track course.id) {
                  <option [value]="course.id">{{ course.title }}</option>
                }
              </select>
            </label>
          </div>
          <div class="row-3">
            <label class="field"><span>Starts</span><input [(ngModel)]="form.start_date" name="start_date" type="date" /></label>
            <label class="field"><span>Ends</span><input [(ngModel)]="form.end_date" name="end_date" type="date" /></label>
            <label class="field"><span>Seats</span><input [(ngModel)]="form.seat_limit" name="seat_limit" type="number" min="1" /></label>
          </div>
          <label class="field"><span>Note</span><textarea [(ngModel)]="form.description" name="description"></textarea></label>
          <button class="btn" [disabled]="busy" type="submit">Open batch</button>
        </form>
      }
      @if (error) {
        <p class="empty">{{ error }}</p>
      } @else {
        <div class="cards">
          @for (batch of batches; track batch.id) {
            <a class="panel" style="padding: 1rem; text-decoration: none" [routerLink]="['/batches', batch.id]">
              <p class="kicker">{{ batch.course_title }}</p>
              <h3>{{ batch.title }}</h3>
              <p class="muted" style="margin: 0.35rem 0 0.8rem">{{ batch.instructor_name }} · {{ batch.start_date }} to {{ batch.end_date }}</p>
              <div class="spread">
                <span>{{ batch.seats_taken }}/{{ batch.seat_limit }} seated</span>
                <span class="pill" [class.good]="batch.joined">{{ batch.joined ? 'You are in' : batch.status }}</span>
              </div>
              @if (batch.joined) {
                <div class="bar" style="margin-top: 0.7rem"><span [style.width]="(batch.progress || 0) + '%'"></span></div>
              }
            </a>
          }
        </div>
      }
    </div>
  `,
})
export class BatchesComponent implements OnInit {
  api = inject(ApiService);
  batches: any[] = [];
  courses: any[] = [];
  error = '';
  open = false;
  busy = false;
  form: any = { title: '', course_id: '', start_date: '', end_date: '', seat_limit: 12, description: '' };

  ngOnInit() {
    this.load();
  }

  async load() {
    try {
      this.batches = await this.api.api('/batches');
      if (this.api.canTeach()) this.courses = await this.api.api('/studio/courses');
    } catch (err: any) {
      this.error = err.message;
    }
  }

  async create() {
    this.busy = true;
    try {
      await this.api.api('/batches', {
        method: 'POST',
        body: { ...this.form, course_id: Number(this.form.course_id), seat_limit: Number(this.form.seat_limit) },
      });
      this.api.toast('Batch opened.');
      this.open = false;
      this.form.title = '';
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    } finally {
      this.busy = false;
    }
  }
}
