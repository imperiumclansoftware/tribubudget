import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Category } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { centsToInput, currentMonth, formatCents, parseEuros } from '../../core/money';
import { BudgetMeter } from '../../shared/budget-meter';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { MonthPicker } from '../../shared/month-picker';

/** A monthly spending limit per expense category, and how the chosen month compares. */
@Component({
  selector: 'app-budgets-page',
  imports: [FormsModule, MonthPicker, BudgetMeter, Modal, Icon, EurosPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './budgets.page.html',
  styleUrl: './budgets.page.scss',
})
export class BudgetsPage {
  private readonly api = inject(BudgetApi);

  protected readonly month = signal(currentMonth());
  private readonly categories = rxResource({ stream: () => this.api.categories() });
  private readonly dashboard = rxResource({
    params: () => this.month(),
    stream: ({ params }) => this.api.dashboard(params),
  });
  protected readonly loading = computed(
    () => this.categories.isLoading() || this.dashboard.isLoading(),
  );
  protected readonly loadError = computed(() => {
    const error = this.categories.error() ?? this.dashboard.error();
    return error ? apiErrorMessage(error) : null;
  });

  protected readonly rows = computed(() => {
    const spent = new Map((this.dashboard.value()?.categories ?? []).map((c) => [c.id, c.spent]));
    return (this.categories.value() ?? [])
      .filter((c) => c.kind === 'expense')
      .map((category) => ({ category, spent: spent.get(category.id) ?? 0 }));
  });
  protected readonly withBudget = computed(() =>
    this.rows().filter((r) => r.category.monthlyBudget !== null),
  );
  protected readonly withoutBudget = computed(() =>
    this.rows().filter((r) => r.category.monthlyBudget === null),
  );
  protected readonly totals = computed(() => {
    const rows = this.withBudget();
    return {
      budget: rows.reduce((sum, r) => sum + (r.category.monthlyBudget ?? 0), 0),
      spent: rows.reduce((sum, r) => sum + r.spent, 0),
    };
  });

  protected readonly editing = signal<Category | null>(null);
  protected readonly amount = signal('');
  protected readonly pending = signal(false);
  protected readonly formError = signal<string | null>(null);

  protected edit(category: Category): void {
    this.amount.set(category.monthlyBudget === null ? '' : centsToInput(category.monthlyBudget));
    this.formError.set(null);
    this.editing.set(category);
  }

  protected async save(remove = false): Promise<void> {
    const category = this.editing();
    if (!category) {
      return;
    }
    const budget = remove ? null : parseEuros(this.amount());
    if (!remove && (budget === null || budget <= 0)) {
      this.formError.set('Indiquez un montant positif, par exemple 400.');
      return;
    }
    this.pending.set(true);
    try {
      await firstValueFrom(
        this.api.saveCategory(category.id, {
          name: category.name,
          kind: category.kind,
          color: category.color,
          monthlyBudget: budget,
        }),
      );
      this.editing.set(null);
      this.categories.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }

  protected readonly format = formatCents;
}
