import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-admin',
  imports: [RouterLink],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <h1>Admin</h1>
          <p class="dek">Administrators run the school: accounts, the school name, and anything sitting in trash.</p>
        </div>
      </header>
      <div class="widgets">
        <a class="card" routerLink="/users"><h2>Users</h2><p class="dek">Create accounts and assign Administrator, Instructor, or Learner.</p></a>
        <a class="card" routerLink="/schools"><h2>School</h2><p class="dek">Set the name that appears on the home desk.</p></a>
        <a class="card" routerLink="/trash"><h2>Trash</h2><p class="dek">Restore or permanently remove courses and resources.</p></a>
        <a class="card" routerLink="/reports"><h2>Reports</h2><p class="dek">See learners, completions, and work left to score.</p></a>
      </div>
    </div>
  `,
})
export class AdminComponent {}
