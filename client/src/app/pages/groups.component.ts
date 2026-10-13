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
      <div class="maker">
        @if (api.canTeach()) {
          <form class="maker-form" (ngSubmit)="create()">
            <div>
              <h2>New group</h2>
              <p class="dek">A group is a conversation for a class, a clinic team, or the staff.</p>
            </div>
            <div class="maker-grid">
              <label class="field wide"><span>Name</span><input [(ngModel)]="form.name" name="name" required /></label>
              <label class="field wide"><span>Description</span><textarea [(ngModel)]="form.description" name="description" rows="3"></textarea></label>
            </div>
            <div class="maker-actions">
              <button class="btn" type="submit">Create group</button>
            </div>
          </form>
        }
        <section>
          <h2>Groups</h2>
          @if (!groups.length) {
            <p class="empty">You are not a member of any group.</p>
          }
          <div class="maker-list">
            @for (group of groups; track group.id) {
              <a class="card" style="padding: 16px" [routerLink]="'/groups/' + group.id">
                <h3>{{ group.name }}</h3>
                <p class="muted">{{ group.owner_name }} · {{ group.members || 0 }} people</p>
              </a>
            }
          </div>
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
