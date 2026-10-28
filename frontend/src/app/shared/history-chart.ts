import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';
import { formatCents, formatCentsCompact, formatMonth, formatShortMonth } from '../core/money';
import { Icon } from './icon';

export interface MonthTotals {
  month: string;
  income: number;
  expenses: number;
}

/** Rounds up to 1, 2, 2.5 or 5 × a power of ten, for clean axis ticks. */
function niceCeil(value: number): number {
  if (value <= 0) {
    return 100;
  }
  const power = 10 ** Math.floor(Math.log10(value));
  const step = [1, 2, 2.5, 5, 10].find((s) => s * power >= value) ?? 10;
  return step * power;
}

/**
 * Income and expenses per month, side by side (one axis, two series), with a tooltip on
 * hover or focus and a table view.
 */
@Component({
  selector: 'app-history-chart',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './history-chart.html',
  styleUrl: './history-chart.scss',
})
export class HistoryChart {
  readonly months = input.required<MonthTotals[]>();

  protected readonly showTable = signal(false);
  protected readonly active = signal<number | null>(null);

  protected readonly max = computed(() =>
    niceCeil(Math.max(...this.months().flatMap((m) => [m.income, m.expenses]))),
  );
  protected readonly ticks = computed(() => {
    const max = this.max();
    return [0, max / 2, max].map((value) => ({ value, label: formatCentsCompact(value) }));
  });

  protected readonly formatCents = formatCents;
  protected readonly formatMonth = formatMonth;
  protected readonly formatShortMonth = formatShortMonth;

  protected height(value: number): string {
    return `${(value / this.max()) * 100}%`;
  }
}
