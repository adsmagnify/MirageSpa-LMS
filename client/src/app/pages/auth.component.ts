import { Component, OnInit, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-auth',
  imports: [FormsModule],
  template: `
    <div class="auth-wrap">
      @if (ready) {
        <div class="auth">
          <section class="auth-panel">
            <h1>{{ needsSetup ? 'Set up the administrator' : 'Sign in' }}</h1>
            @if (needsSetup) {
              <p class="dek">This first account manages the platform and assigns every other role.</p>
            } @else {
              <p class="dek">Sign in with the role an administrator assigned to you.</p>
            }
            @if (!needsSetup) {
              <ul class="role-list">
                @for (role of roles; track role[0]) {
                  <li><strong>{{ role[0] }}</strong> {{ role[1] }}</li>
                }
              </ul>
            }
            <form class="stack" (ngSubmit)="submit()">
              @if (needsSetup) {
                <label class="field">
                  <span>Name</span>
                  <input [(ngModel)]="form.full_name" name="full_name" autocomplete="name" required />
                </label>
              }
              <label class="field">
                <span>Email</span>
                <input [(ngModel)]="form.email" name="email" type="email" autocomplete="username" required />
              </label>
              <label class="field">
                <span>Password</span>
                <input [(ngModel)]="form.password" name="password" type="password" [autocomplete]="needsSetup ? 'new-password' : 'current-password'" required minlength="4" />
              </label>
              <button class="btn" type="submit" [disabled]="busy">{{ needsSetup ? 'Create administrator' : 'Sign in' }}</button>
            </form>
          </section>
        </div>
      }
    </div>
  `,
})
export class AuthComponent implements OnInit {
  api = inject(ApiService);
  router = inject(Router);
  route = inject(ActivatedRoute);
  needsSetup = false;
  ready = false;
  form: any = { full_name: '', email: '', password: '' };
  busy = false;
  roles = [
    ['Administrator', 'Manages users, roles, and the whole platform.'],
    ['Instructor', 'Uploads courses, lessons, and other materials.'],
    ['Learner', 'Opens the classes assigned to them.'],
  ];

  ngOnInit() {
    this.open();
  }

  homePath() {
    if (!this.api.user) return '/login';
    return '/';
  }

  go() {
    const next = this.route.snapshot.queryParamMap.get('next');
    this.router.navigateByUrl(next || this.homePath());
  }

  async open() {
    if (this.api.user && !this.route.snapshot.queryParamMap.get('next')) {
      this.router.navigateByUrl(this.homePath(), { replaceUrl: true });
      return;
    }
    try {
      const status = await this.api.api('/auth/status');
      this.needsSetup = status.needs_setup;
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    } finally {
      this.ready = true;
    }
  }

  async submit() {
    this.busy = true;
    try {
      if (this.needsSetup) await this.api.signup(this.form);
      else await this.api.login(this.form.email, this.form.password);
      this.go();
    } catch (error: any) {
      this.api.toast(error.message, 'warn');
    } finally {
      this.busy = false;
    }
  }
}
