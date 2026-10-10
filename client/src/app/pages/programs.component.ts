import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-programs',
  imports: [RouterLink],
  template: `
    <div class="page">
      <h1>Programs</h1>
      <p class="dek">A program is an ordered set of courses. Joining enrolls you in each course, and progress is the average.</p>
      @for (program of programs; track program.id) {
        <article class="panel" style="padding: 16px; margin-top: 16px">
          <div class="spread">
            <div>
              <h2>{{ program.title }}</h2>
              <p class="dek">{{ program.description }}</p>
            </div>
            @if (api.user && !program.joined) {
              <button class="btn" type="button" (click)="enroll(program)">Enroll</button>
            } @else if (program.joined) {
              <span class="pill good">{{ program.progress }}%</span>
            }
          </div>
          @for (course of program.courses; track course.id; let index = $index) {
            <p style="margin-top: 8px">
              <a [routerLink]="['/courses', course.slug]">{{ index + 1 }}. {{ course.title }}</a>
            </p>
          }
          <p class="muted" style="margin-top: 8px">{{ program.members }} members</p>
        </article>
      }
    </div>
  `,
})
export class ProgramsComponent implements OnInit {
  api = inject(ApiService);
  programs: any[] = [];

  ngOnInit() {
    this.load();
  }

  async load() {
    this.programs = await this.api.api('/programs');
  }

  async enroll(program: any) {
    if (!this.api.user) return;
    try {
      await this.api.api(`/programs/${program.id}/enroll`, { method: 'POST' });
      this.api.toast('Enrolled in every course in the program.');
      await this.load();
    } catch (err: any) {
      this.api.toast(err.message, 'warn');
    }
  }
}
