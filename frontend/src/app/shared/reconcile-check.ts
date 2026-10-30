import { ChangeDetectionStrategy, Component, inject, input, output, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../core/api/api-error';
import { Operation } from '../core/api/models';
import { BudgetApi } from '../core/budget-api';

/**
 * Ticks an operation off against the bank statement. Emits the operation as saved, or the
 * message of the error.
 */
@Component({
  selector: 'app-reconcile-check',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <label class="reconcile" [title]="operation().reconciled ? 'Pointée' : 'À pointer'">
      <input
        type="checkbox"
        [checked]="operation().reconciled"
        [disabled]="pending()"
        (change)="toggle($any($event.target).checked)"
      />
      <span class="visually-hidden">Pointée : {{ operation().label }}</span>
    </label>
  `,
  styles: `
    .reconcile {
      display: grid;
      place-items: center;
      flex: none;
      width: var(--touch-target);
      height: var(--touch-target);
      margin: -8px 0 -8px -12px;
      cursor: pointer;
    }
  `,
})
export class ReconcileCheck {
  private readonly api = inject(BudgetApi);

  readonly operation = input.required<Operation>();
  readonly saved = output<Operation>();
  readonly failed = output<string>();
  protected readonly pending = signal(false);

  protected async toggle(reconciled: boolean): Promise<void> {
    this.pending.set(true);
    try {
      this.saved.emit(await firstValueFrom(this.api.reconcile(this.operation().id, reconciled)));
    } catch (e) {
      this.failed.emit(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
