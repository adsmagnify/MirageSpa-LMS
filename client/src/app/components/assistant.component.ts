import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-assistant',
  imports: [FormsModule],
  template: `
    <div class="assistant-log">
      @for (message of messages; track $index) {
        <p [class]="message.role">{{ message.text }}</p>
      }
    </div>
    <form class="assistant-ask" (ngSubmit)="ask()">
      <input [(ngModel)]="question" name="question" placeholder="Ask about the school desk" />
      <button class="btn small" type="submit">Ask</button>
    </form>
  `,
})
export class AssistantComponent {
  question = '';
  messages = [
    { role: 'assistant', text: 'Ask how to copy a course, grade work, use groups, upload a resource, or restore something from trash.' },
  ];
  private guides = [
    { keys: ['copy', 'parent', 'duplicate'], text: 'On Home or Courses, open the row menu and choose Copy course. The copy is a draft with the same chapters, lessons, and quizzes. Learners stay on the original until you assign them.' },
    { keys: ['group'], text: 'Open Groups, create a group, and add people by email. Messages posted there also show in the News widget on Home.' },
    { keys: ['trash', 'delete', 'restore'], text: 'Move a course to Trash from its row menu. Open the trash icon in the top bar to restore it, or remove it for good. Resources you remove go to the same trash.' },
    { keys: ['resource', 'upload', 'video', 'library', 'document'], text: 'Resources holds videos, documents, and quizzes. Upload a file or write a quiz, then place it into a chapter. There is no file size cap.' },
    { keys: ['grade', 'score', 'assignment'], text: 'To score counts assignments that are still waiting for a grade. Open Assignments, enter the score, and the count drops.' },
    { keys: ['assign', 'learner', 'enroll', 'class'], text: 'Open the course and assign a learner by email. Learners only see classes assigned to them. You can deactivate someone on that course without deleting their work.' },
    { keys: ['report', 'progress', 'complete'], text: 'Reports lists each course with learners, how many finished every lesson, average progress, and work still to score.' },
    { keys: ['calendar', 'live'], text: 'The calendar collects live classes, batches, and evaluation times. Open the calendar icon in the top bar for the full month.' },
    { keys: ['school', 'user', 'role'], text: 'Administrators create accounts and assign Administrator, Instructor, or Learner. Schools shows the school name, instructors, and how many learners and courses you have.' },
  ];

  ask() {
    const text = this.question.trim();
    if (!text) return;
    this.messages = [...this.messages, { role: 'you', text }];
    const lower = text.toLowerCase();
    const match = this.guides.find((guide) => guide.keys.some((key) => lower.includes(key)));
    this.messages = [...this.messages, {
      role: 'assistant',
      text: match ? match.text : 'I can walk through courses, copying a class, groups, resources, grading, reports, the calendar, and trash. Name one of those.',
    }];
    this.question = '';
  }
}
