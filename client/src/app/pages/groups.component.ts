import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-groups',
  imports: [FormsModule, RouterLink],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <h1>Groups</h1>
          <p class="dek">School groups keep a class, a clinic team, or a staff circle in one conversation.</p>
        </div>
      </header>
      <div class="cards">
        @if (api.canTeach()) {
          <form class="card stack" (ngSubmit)="create()">
            <h2>New group</h2>
            <label class="field"><span>Name</span><input [(ngModel)]="form.name" name="name" required /></label>
            <label class="field"><span>Description</span><textarea [(ngModel)]="form.description" name="description" rows="3"></textarea></label>
            <button class="btn" type="submit">Create group</button>
          </form>
        }
        <section class="stack">
          @for (group of groups; track group.id) {
            <a class="card widget-row" [routerLink]="'/groups/' + group.id">
              <span>
                <strong>{{ group.name }}</strong>
                <small class="muted">{{ group.owner_name }} · {{ group.members || 0 }} people</small>
              </span>
            </a>
          }
          @if (!groups.length) {
            <p class="empty">You are not a member of any group.</p>
          }
        </section>
      </div>
    </div>
  `,
})
export class GroupsComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  groups: any[] = [];
  form: any = { name: '', description: '' };

  ngOnInit() {
    this.load();
  }

  async load() {
    this.groups = await this.api.api('/groups');
  }

  async create() {
    try {
      const group = await this.api.api('/groups', { method: 'POST', body: this.form });
      this.api.toast('Group created.');
      this.router.navigateByUrl(`/groups/${group.id}`);
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
