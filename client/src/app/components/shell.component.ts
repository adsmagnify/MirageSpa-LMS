import { Component, OnInit, inject } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { filter } from 'rxjs';
import { ApiService } from '../api.service';
import { AssistantComponent } from './assistant.component';

@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, FormsModule, AssistantComponent],
  template: `
    <div class="app" [class.nav-open]="menuOpen">
      @if (menuOpen && !lesson) {
        <button class="nav-backdrop" type="button" aria-label="Close menu" (click)="menuOpen = false"></button>
      }
      @if (!lesson) {
        <aside class="side">
          <a class="logo" routerLink="/">
            <span class="logo-mark">M</span>
            <span><strong>MIRAGE</strong><small>SPA ACADEMY</small></span>
          </a>
          <nav>
            @for (item of items(); track item.to) {
              <a class="item" [routerLink]="item.to" routerLinkActive="on" [routerLinkActiveOptions]="{ exact: item.to === '/' }" (click)="menuOpen = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path [attr.d]="icons[item.icon]" />
                </svg>
                {{ item.label }}
              </a>
            }
          </nav>
          @if (api.user) {
            <div class="who">
              <span class="avatar">{{ initials() }}</span>
              <span>
                <strong>{{ api.user.full_name }}</strong><br />
                <small style="color: #9aa0a8">{{ api.user.frappe_role }}</small>
              </span>
            </div>
          }
        </aside>
      }
      <div class="workspace">
        @if (!lesson) {
          <header class="topbar">
            <button class="menu-btn" type="button" aria-label="Open menu" (click)="menuOpen = !menuOpen">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            </button>
            <form class="top-search" (ngSubmit)="goSearch()">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
              <input [(ngModel)]="query" name="query" type="search" placeholder="Search" aria-label="Search" />
            </form>
            <div class="top-tools">
              <button class="icon-btn" type="button" aria-label="Notifications" (click)="openNotes()">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 9a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9" /><path d="M10 20a2 2 0 0 0 4 0" /></svg>
                @if (unread()) { <span class="dot">{{ unread() }}</span> }
              </button>
              <a class="icon-btn" routerLink="/calendar" aria-label="Calendar">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2" /><path d="M8 3.5V7M16 3.5V7M4 10h16" /></svg>
              </a>
              @if (api.canTeach()) {
                <a class="icon-btn" routerLink="/trash" aria-label="Trash">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14M9 7V5h6v2M8 7l1 13h6l1-13" /></svg>
                </a>
              }
              <button class="who-btn" type="button" (click)="api.ui.account = !api.ui.account; api.ui.notices = false">
                <span class="who-name">{{ api.user?.full_name }}</span>
                <span class="avatar sm">{{ initials() }}</span>
              </button>
              @if (api.ui.notices) {
                <div class="pop">
                  @if (!notes.length) { <p class="muted">No notifications yet.</p> }
                  @for (item of notes; track item.id) {
                    <a class="pop-row" [routerLink]="item.href || '/'" (click)="api.ui.notices = false">{{ item.body }}</a>
                  }
                </div>
              }
              @if (api.ui.account) {
                <div class="pop account">
                  <a [routerLink]="['/user', api.user?.username]" (click)="api.ui.account = false">Profile</a>
                  <button type="button" (click)="signOut()">Sign out</button>
                </div>
              }
            </div>
          </header>
        }
        <div class="main-stage">
          <router-outlet />
        </div>
      </div>
      @if (api.ui.assistant) {
        <aside class="assistant-panel">
          <header class="spread">
            <strong>Student Assistant</strong>
            <button class="btn small ghost" type="button" (click)="api.ui.assistant = false">Close</button>
          </header>
          <app-assistant />
        </aside>
      }
    </div>
  `,
})
export class ShellComponent implements OnInit {
  api = inject(ApiService);
  private router = inject(Router);
  query = '';
  notes: any[] = [];
  menuOpen = false;
  lesson = false;
  icons: Record<string, string> = {
    home: 'M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1V10.5z',
    courses: 'M4 6.5h16M4 12h16M4 17.5h10',
    groups: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm8 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM3 19c0-2.5 2.2-4 5-4s5 1.5 5 4M13 15.2c1.6.3 3.8 1.2 4.5 3.3',
    users: 'M12 12a3.2 3.2 0 1 0 0-6.4A3.2 3.2 0 0 0 12 12zm-6.5 7c.6-2.6 3-4 6.5-4s5.9 1.4 6.5 4',
    schools: 'M4 20V9l8-5 8 5v11M9 20v-6h6v6',
    resources: 'M6 4.5h8l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1zm8 0v4h4',
    reports: 'M5 19V10M12 19V5M19 19v-7',
    admin: 'M12 3.5 5 6.5v5.2c0 4.2 2.9 7.2 7 8.8 4.1-1.6 7-4.6 7-8.8V6.5L12 3.5z',
    help: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-6.2v.2M9.6 9.2a2.4 2.4 0 1 1 3.2 2.3c-.7.3-1.2.9-1.2 1.6',
  };

  constructor() {
    this.router.events.pipe(filter((event) => event instanceof NavigationEnd)).subscribe(() => {
      this.menuOpen = false;
      let route = this.router.routerState.root;
      while (route.firstChild) route = route.firstChild;
      this.lesson = !!route.snapshot.data['lesson'];
    });
  }

  ngOnInit() {
    this.loadNotes();
  }

  items() {
    const rows: { to: string; label: string; icon: string }[] = [
      { to: '/', label: 'Home', icon: 'home' },
      { to: '/courses', label: 'Courses', icon: 'courses' },
      { to: '/groups', label: 'Groups', icon: 'groups' },
    ];
    if (this.api.isAdmin()) rows.push({ to: '/users', label: 'Users', icon: 'users' });
    if (this.api.canTeach()) {
      rows.push(
        { to: '/schools', label: 'Schools', icon: 'schools' },
        { to: '/library', label: 'Resources', icon: 'resources' },
        { to: '/reports', label: 'Reports', icon: 'reports' },
      );
    }
    if (this.api.isAdmin()) rows.push({ to: '/admin', label: 'Admin', icon: 'admin' });
    rows.push({ to: '/help', label: 'Help', icon: 'help' });
    return rows;
  }

  initials() {
    return (this.api.user?.full_name || '?')
      .split(' ')
      .slice(0, 2)
      .map((part: string) => part[0])
      .join('')
      .toUpperCase();
  }

  unread() {
    return this.notes.filter((item) => !item.read).length;
  }

  async loadNotes() {
    if (!this.api.user) return;
    try {
      this.notes = await this.api.api('/notifications');
    } catch {
      this.notes = [];
    }
  }

  goSearch() {
    const q = this.query.trim();
    if (q) this.router.navigate(['/search'], { queryParams: { q } });
  }

  async openNotes() {
    this.api.ui.notices = !this.api.ui.notices;
    this.api.ui.account = false;
    if (this.api.ui.notices && this.unread()) {
      await this.api.api('/notifications/read', { method: 'POST' });
      this.notes = this.notes.map((item) => ({ ...item, read: 1 }));
    }
  }

  signOut() {
    this.api.logout();
    this.router.navigateByUrl('/login');
  }
}
