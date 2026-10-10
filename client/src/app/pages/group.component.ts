import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-group',
  imports: [FormsModule, RouterLink],
  template: `
    @if (group) {
      <div class="page">
        <p class="crumbs"><a routerLink="/groups">Groups</a><span>/</span><span>{{ group.name }}</span></p>
        <header class="page-head">
          <div>
            <h1>{{ group.name }}</h1>
            <p class="dek">{{ group.description || 'No description yet.' }}</p>
          </div>
        </header>
        <div class="cards">
          <section class="card stack">
            <h2>People</h2>
            @for (member of group.members_list; track member.id) {
              <p>{{ member.full_name }} <span class="muted">{{ member.email }}</span></p>
            }
            @if (api.canTeach()) {
              <form class="stack" (ngSubmit)="add()">
                <label class="field"><span>Add by email</span><input [(ngModel)]="email" name="email" type="email" required /></label>
                <button class="btn small" type="submit">Add</button>
              </form>
            }
          </section>
          <section class="card stack">
            <h2>Messages</h2>
            @for (item of group.posts; track item.id) {
              <article>
                <strong>{{ item.author_name }}</strong>
                <p>{{ item.body }}</p>
              </article>
            }
            @if (!group.posts.length) {
              <p class="muted">No messages in this group yet.</p>
            }
            <form class="news-form" (ngSubmit)="post()">
              <input [(ngModel)]="body" name="body" placeholder="Write a message" required />
              <button class="btn small" type="submit">Post</button>
            </form>
          </section>
        </div>
      </div>
    }
  `,
})
export class GroupComponent implements OnInit {
  api = inject(ApiService);
  route = inject(ActivatedRoute);
  group: any = null;
  email = '';
  body = '';

  ngOnInit() {
    this.load();
  }

  async load() {
    this.group = await this.api.api(`/groups/${this.route.snapshot.paramMap.get('id')}`);
  }

  async add() {
    try {
      this.group = await this.api.api(`/groups/${this.route.snapshot.paramMap.get('id')}/members`, { method: 'POST', body: { email: this.email } });
      this.email = '';
      this.api.toast('Added to the group.');
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }

  async post() {
    try {
      await this.api.api('/feed', { method: 'POST', body: { body: this.body, group_id: Number(this.route.snapshot.paramMap.get('id')) } });
      this.body = '';
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    }
  }
}
