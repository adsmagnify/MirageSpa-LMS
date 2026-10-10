import { Injectable } from '@angular/core';

export interface Notice {
  id: string;
  message: string;
  tone: string;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  token = localStorage.getItem('mirage_token') || '';
  user: any = null;
  ready = false;
  notices: Notice[] = [];
  ui = { assistant: false, notices: false, account: false };
  private hydratePromise: Promise<void> | null = null;

  apiUrl(path: string) {
    const suffix = path.startsWith('/') ? path : `/${path}`;
    return `/api${suffix}`;
  }

  mediaUrl(url: string) {
    if (!url || !url.startsWith('/api/')) return url;
    if (!this.token || !url.startsWith('/api/library/')) return url;
    const join = url.includes('?') ? '&' : '?';
    return `${url}${join}token=${encodeURIComponent(this.token)}`;
  }

  async api(path: string, options: { method?: string; body?: any; form?: boolean } = {}) {
    const headers: Record<string, string> = {};
    if (this.token) headers['Authorization'] = `Bearer ${this.token}`;
    if (options.body && !options.form) headers['Content-Type'] = 'application/json';
    const response = await fetch(this.apiUrl(path), {
      method: options.method || 'GET',
      headers,
      body: options.form ? options.body : options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (response.status === 401 && !path.startsWith('/auth/')) this.logout();
    if (!response.ok) {
      const error = new Error(data.detail || 'Something went wrong.');
      throw error;
    }
    return data;
  }

  setSession(token: string, user: any) {
    this.token = token;
    this.user = user;
    localStorage.setItem('mirage_token', token);
  }

  async login(email: string, password: string) {
    const data = await this.api('/auth/login', { method: 'POST', body: { email, password } });
    this.setSession(data.token, data.user);
    return data.user;
  }

  async signup(payload: any) {
    const data = await this.api('/auth/signup', { method: 'POST', body: payload });
    this.setSession(data.token, data.user);
    return data.user;
  }

  logout() {
    this.token = '';
    this.user = null;
    localStorage.removeItem('mirage_token');
  }

  hydrate() {
    if (this.ready) return Promise.resolve();
    if (!this.hydratePromise) {
      this.hydratePromise = (async () => {
        if (!this.token) return;
        try {
          this.user = await this.api('/auth/me');
        } catch {
          this.logout();
        }
      })().finally(() => {
        this.ready = true;
      });
    }
    return this.hydratePromise;
  }

  toast(message: string, tone = 'ok') {
    const id = crypto.randomUUID();
    this.notices = [...this.notices, { id, message, tone }];
    setTimeout(() => {
      this.notices = this.notices.filter((item) => item.id !== id);
    }, 3800);
  }

  isStaff(user = this.user) {
    return ['admin', 'instructor', 'moderator'].includes(user?.role);
  }

  canTeach(user = this.user) {
    return ['admin', 'instructor'].includes(user?.role);
  }

  isAdmin(user = this.user) {
    return user?.role === 'admin';
  }
}
