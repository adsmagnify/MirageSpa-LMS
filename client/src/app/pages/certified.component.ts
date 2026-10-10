import { Component, OnInit, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-certified',
  imports: [RouterLink],
  template: `
    <div class="page">
      <h1>Certified Participants</h1>
      <p class="dek">Members who passed an evaluation and hold a certificate.</p>
      <table style="margin-top: 16px">
        <thead><tr><th>Member</th><th>Course</th><th>Issued</th></tr></thead>
        <tbody>
          @for (row of rows; track row.id) {
            <tr>
              <td><a [routerLink]="['/user', row.username]">{{ row.full_name }}</a></td>
              <td><a [routerLink]="['/courses', row.slug]">{{ row.course_title }}</a></td>
              <td>{{ row.issue_date.slice(0, 10) }}</td>
            </tr>
          }
        </tbody>
      </table>
      @if (!rows.length) {
        <p class="empty">No certificates yet.</p>
      }
    </div>
  `,
})
export class CertifiedComponent implements OnInit {
  api = inject(ApiService);
  rows: any[] = [];

  ngOnInit() {
    this.load();
  }

  async load() {
    this.rows = await this.api.api('/certificates');
  }
}
