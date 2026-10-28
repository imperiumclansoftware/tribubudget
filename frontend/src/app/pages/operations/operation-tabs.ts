import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

/** Switches between the operations and the recurring operations. */
@Component({
  selector: 'app-operation-tabs',
  imports: [RouterLink, RouterLinkActive],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <nav class="segmented" aria-label="Type d’opérations">
      <a
        routerLink="/operations"
        routerLinkActive="active"
        [routerLinkActiveOptions]="{ exact: true }"
        ariaCurrentWhenActive="page"
        >Toutes</a
      >
      <a routerLink="/operations/recurrentes" routerLinkActive="active" ariaCurrentWhenActive="page"
        >Récurrentes</a
      >
    </nav>
  `,
})
export class OperationTabs {}
