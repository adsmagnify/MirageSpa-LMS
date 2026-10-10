import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-schools',
  imports: [FormsModule],
  template: `
    @if (data) {
      <div class="page">
        <header class="page-head">
          <div>
            <h1>Schools</h1>
            <p class="dek">One school holds the courses, instructors, and learners on this desk.</p>
          </div>
        </header>
        <section class="card stack" style="max-width: 640px">
          <h2>{{ data.school.name }}</h2>
          <p class="muted">{{ data.courses }} courses · {{ data.learners }} learners · {{ data.instructors.length }} instructors</p>
          @if (api.isAdmin()) {
            <form class="stack" (ngSubmit)="save()">
              <label class="field"><span>School name</span><input [(ngModel)]="name" name="name" required /></label>
              <button class="btn small" type="submit">Save</button>
            </form>
          }
          <h3>Instructors</h3>
          @for (person of data.instructors; track person.id) {
            <p>{{ person.full_name }} <span class="muted">{{ person.email }}</span></p>
          }
        </section>
      </div>
    }
  `,
})
export class SchoolsComponent implements OnInit {
  api = inject(ApiService);
  data: any = null;
  name = '';

  ngOnInit() {
    this.load();
  }

  async load() {
    this.data = await this.api.api('/school');
    this.name = this.data.school.name;
  }

  async save() {
    try {
      const school = await this.api.api('/school', { method: 'PATCH', body: { name: this.name } });
      this.data.school = school;
      this.api.toast('School name saved.');
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
