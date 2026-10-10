import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-users',
  imports: [FormsModule],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <h1>Users</h1>
          <p class="dek">Create each account and assign a role. People sign in with that role.</p>
        </div>
      </header>

      <form class="panel stack" style="padding: 16px; margin-bottom: 16px" (ngSubmit)="create()">
        <h2>New account</h2>
        <div class="row-2">
          <label class="field"><span>Name</span><input [(ngModel)]="form.full_name" name="fullName" required /></label>
          <label class="field"><span>Email</span><input [(ngModel)]="form.email" name="email" type="email" required /></label>
        </div>
        <div class="row-2">
          <label class="field"><span>Password</span><input [(ngModel)]="form.password" name="password" type="text" minlength="4" required /></label>
          <label class="field">
            <span>Role</span>
            <select [(ngModel)]="form.role" name="role">
              <option value="student">Learner</option>
              <option value="instructor">Instructor</option>
              <option value="admin">Administrator</option>
            </select>
          </label>
        </div>
        <button class="btn" type="submit" [disabled]="busy">Create account</button>
      </form>

      <table>
        <thead>
          <tr><th>Name</th><th>Email</th><th>Role</th></tr>
        </thead>
        <tbody>
          @for (person of people; track person.id) {
            <tr>
              <td>{{ person.full_name }}</td>
              <td>{{ person.email }}</td>
              <td>
                <select [value]="person.role" aria-label="Role" (change)="changeRole(person, $any($event.target).value)">
                  <option value="student">Learner</option>
                  <option value="instructor">Instructor</option>
                  <option value="admin">Administrator</option>
                </select>
              </td>
            </tr>
          }
        </tbody>
      </table>
    </div>
  `,
})
export class UsersComponent implements OnInit {
  api = inject(ApiService);
  people: any[] = [];
  busy = false;
  form: any = { full_name: '', email: '', password: '', role: 'student' };
  roleName: any = {
    admin: 'Administrator',
    instructor: 'Instructor',
    student: 'Learner',
    moderator: 'Moderator',
  };

  ngOnInit() {
    this.load();
  }

  async load() {
    this.people = await this.api.api('/users');
  }

  async create() {
    this.busy = true;
    try {
      await this.api.api('/users', { method: 'POST', body: this.form });
      this.api.toast('Account created.');
      this.form = { full_name: '', email: '', password: '', role: 'student' };
      await this.load();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    } finally {
      this.busy = false;
    }
  }

  async changeRole(person: any, role: string) {
    try {
      const updated = await this.api.api(`/users/${person.id}`, { method: 'PATCH', body: { role } });
      Object.assign(person, updated);
      this.api.toast(`${person.full_name} is now ${this.roleName[role]}.`);
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
      await this.load();
    }
  }
}
