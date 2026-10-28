import { ChangeDetectionStrategy, Component, computed, model } from '@angular/core';
import { currentMonth, formatMonth, shiftMonth } from '../core/money';
import { Icon } from './icon';

/** ‹ octobre 2026 › */
@Component({
  selector: 'app-month-picker',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button type="button" class="btn btn-icon" (click)="move(-1)" aria-label="Mois précédent">
      <app-icon name="left" />
    </button>
    <span class="label" aria-live="polite">{{ label() }}</span>
    <button
      type="button"
      class="btn btn-icon"
      (click)="move(1)"
      [disabled]="isCurrent()"
      aria-label="Mois suivant"
    >
      <app-icon name="right" />
    </button>
  `,
  styles: `
    :host {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 4px;
      background: var(--bg-surface);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
    }
    button {
      flex: none;
    }
    .label {
      flex: 1;
      min-width: 9.5rem;
      text-align: center;
      font-weight: 700;
      text-transform: capitalize;
    }
  `,
})
export class MonthPicker {
  readonly month = model.required<string>();

  protected readonly label = computed(() => formatMonth(this.month()));
  protected readonly isCurrent = computed(() => this.month() >= currentMonth());

  protected move(delta: number): void {
    this.month.set(shiftMonth(this.month(), delta));
  }
}
