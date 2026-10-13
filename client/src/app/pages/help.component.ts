import { Component } from '@angular/core';

@Component({
  selector: 'app-help',
  imports: [],
  template: `
    <div class="page">
      <header class="page-head">
        <div>
          <h1>Help</h1>
          <p class="dek">How this school desk is organized, in the same places as a CIMA instructor home.</p>
        </div>
      </header>
      <section class="stack">
        @for (topic of topics; track topic.title) {
          <article class="card">
            <h2>{{ topic.title }}</h2>
            <p class="dek">{{ topic.body }}</p>
          </article>
        }
      </section>
    </div>
  `,
})
export class HelpComponent {
  topics = [
    { title: 'Home', body: 'Home is the school desk. Teaching lists classes you run. Enrolled lists classes assigned to you. To score is ungraded work. Completed is learners who finished every lesson. Deactivated is learners paused in that class.' },
    { title: 'Copy a course', body: 'Use the row menu and choose Copy course when a new class needs the same chapters. The copy is a draft. Assign learners to the copy when the class starts.' },
    { title: 'Groups and news', body: 'Create a group for a class team or the staff. Add people by email. Messages show on the group page and in News on Home.' },
    { title: 'Resources', body: 'Upload videos and documents, or write a quiz, in Resources. Place the item into a chapter so it becomes the next lesson. Files have no size cap.' },
    { title: 'People and roles', body: 'An administrator creates every account and sets Administrator, Instructor, or Learner. Share the password yourself. The site does not email it. Each person can change their password from their profile, and an administrator can set a new one on Users.' },
    { title: 'Live classes', body: 'Open a batch and schedule a class with a Zoom or Meet link. Learners open that link and mark themselves present. Zoom is not connected on this server, so the pasted link is the room.' },
    { title: 'Certificates', body: 'Turn certification on for a course. An instructor publishes evaluation hours from their profile. When a learner finishes every lesson, they book a time. A pass can be printed from the certificate page.' },
    { title: 'Reports', body: 'Reports totals learners, completions, and work left to score. Download spreadsheet saves that table as a file you can open in Excel.' },
    { title: 'Trash', body: 'Moving a course or resource to trash hides it from the desk. Restore it from the trash icon, or remove it permanently.' },
    { title: 'Calendar', body: 'The calendar lists live classes, batch dates, and evaluation times.' },
  ];
}
