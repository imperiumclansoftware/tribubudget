import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { formatCents } from '../core/money';
import { Icon } from './icon';

/**
 * Spending of a category against its monthly budget. Over budget, the bar turns critical and
 * says so in words and with an icon, never by color alone.
 */
@Component({
  selector: 'app-budget-meter',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="head">
      <span class="dot" [style.background]="color()" aria-hidden="true"></span>
      <span class="name grow ellipsis">{{ name() }}</span>
      <span class="amount">{{ format(spent()) }}</span>
      @if (budget(); as budget) {
        <span class="muted">/ {{ format(budget) }}</span>
      }
    </div>
    @if (budget(); as budget) {
      <div
        class="track"
        role="meter"
        [attr.aria-label]="name()"
        [attr.aria-valuenow]="spent() / 100"
        aria-valuemin="0"
        [attr.aria-valuemax]="budget / 100"
        [attr.aria-valuetext]="format(spent()) + ' sur ' + format(budget)"
      >
        <span class="fill" [class.over]="over()" [style.width.%]="percent()"></span>
      </div>
      @if (over()) {
        <p class="status over">
          <app-icon name="alert" [size]="15" /> Dépassé de {{ format(spent() - budget) }}
        </p>
      } @else {
        <p class="status">Reste {{ format(budget - spent()) }}</p>
      }
    }
  `,
  styles: `
    :host {
      display: grid;
      gap: 0.35rem;
    }
    .head {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      min-width: 0;
    }
    .name {
      font-weight: 700;
    }
    .track {
      height: 0.6rem;
      border-radius: 999px;
      background: var(--meter-track);
      overflow: hidden;
    }
    .fill {
      display: block;
      height: 100%;
      border-radius: 999px;
      background: var(--meter-fill);
    }
    .fill.over {
      background: var(--critical);
    }
    .status {
      font-size: 0.85rem;
      color: var(--text-secondary);
    }
    .status.over {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      color: var(--danger-text);
      font-weight: 700;
    }
  `,
})
export class BudgetMeter {
  readonly name = input.required<string>();
  readonly color = input.required<string>();
  readonly spent = input.required<number>();
  readonly budget = input<number | null>(null);

  protected readonly over = computed(() => {
    const budget = this.budget();
    return budget !== null && this.spent() > budget;
  });
  protected readonly percent = computed(() => {
    const budget = this.budget();
    return budget ? Math.min(100, Math.max(0, (this.spent() / budget) * 100)) : 0;
  });
  protected readonly format = formatCents;
}
