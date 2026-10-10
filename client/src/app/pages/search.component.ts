import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../api.service';

@Component({
  selector: 'app-search',
  imports: [RouterLink],
  template: `
    @if (data) {
      <div class="page">
        <h1>Search</h1>
        <p class="dek">Results for “{{ route.snapshot.queryParamMap.get('q') || '' }}”.</p>
        <section class="card stack">
          <h2>Courses</h2>
          @for (course of data.courses; track course.id) {
            <a [routerLink]="'/courses/' + course.slug">{{ course.title }}</a>
          }
          @if (!data.courses.length) {
            <p class="muted">No courses.</p>
          }
          <h2>People</h2>
          @for (person of data.people; track person.id) {
            <p>{{ person.full_name }} <span class="muted">{{ person.email }}</span></p>
          }
          @if (!data.people.length) {
            <p class="muted">No people.</p>
          }
          <h2>Resources</h2>
          @for (item of data.resources; track item.id) {
            <a routerLink="/library">{{ item.title }} <span class="muted">{{ item.kind }}</span></a>
          }
          @if (!data.resources.length) {
            <p class="muted">No resources.</p>
          }
          <h2>Groups</h2>
          @for (group of data.groups; track group.id) {
            <a [routerLink]="'/groups/' + group.id">{{ group.name }}</a>
          }
          @if (!data.groups.length) {
            <p class="muted">No groups.</p>
          }
        </section>
      </div>
    }
  `,
})
export class SearchComponent implements OnInit {
  api = inject(ApiService);
  route = inject(ActivatedRoute);
  private destroyRef = inject(DestroyRef);
  data: any = null;

  ngOnInit() {
    this.route.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => this.load());
  }

  async load() {
    const q = this.route.snapshot.queryParamMap.get('q') || '';
    this.data = await this.api.api(`/search?q=${encodeURIComponent(q)}`);
  }
}
