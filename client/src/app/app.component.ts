import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { ApiService } from './api.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: `
    <router-outlet />
    <div class="toasts" aria-live="polite">
      @for (item of api.notices; track item.id) {
        <p [attr.data-tone]="item.tone">{{ item.message }}</p>
      }
    </div>
  `,
})
export class AppComponent {
  api = inject(ApiService);
}
