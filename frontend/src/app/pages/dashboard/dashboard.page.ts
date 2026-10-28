import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  linkedSignal,
  signal,
} from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../core/api/api-error';
import { Dashboard } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { ACCOUNT_TYPE_LABELS } from '../../core/labels';
import { currentMonth } from '../../core/money';
import { BudgetMeter } from '../../shared/budget-meter';
import { DayPipe, EurosPipe, MonthPipe } from '../../shared/euros.pipe';
import { HistoryChart } from '../../shared/history-chart';
import { Icon } from '../../shared/icon';
import { MonthPicker } from '../../shared/month-picker';

@Component({
  selector: 'app-dashboard-page',
  imports: [
    RouterLink,
    MonthPicker,
    HistoryChart,
    BudgetMeter,
    EurosPipe,
    MonthPipe,
    DayPipe,
    Icon,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dashboard.page.html',
  styleUrl: './dashboard.page.scss',
})
export class DashboardPage {
  private readonly api = inject(BudgetApi);

  protected readonly month = signal(currentMonth());
  private readonly resource = rxResource({
    params: () => this.month(),
    stream: ({ params }) => this.api.dashboard(params),
  });
  /** The previous month stays on screen (dimmed) while the next one loads */
  protected readonly data = linkedSignal<Dashboard | undefined, Dashboard | undefined>({
    source: () => this.resource.value(),
    computation: (value, previous) => value ?? previous?.value,
  });
  protected readonly loading = this.resource.isLoading;
  protected readonly error = computed(() =>
    this.resource.error() ? apiErrorMessage(this.resource.error()) : null,
  );

  protected readonly overBudget = computed(
    () =>
      this.data()?.categories.filter((c) => c.budget !== null && c.spent > c.budget).length ?? 0,
  );
  protected readonly accountTypes = ACCOUNT_TYPE_LABELS;
}
