import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Category, CategoryKind, Frequency, RecurringOperation } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { FREQUENCY_LABELS } from '../../core/labels';
import { centsToInput, formatLongDay, parseEuros, todayIso } from '../../core/money';
import { DayPipe, EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { OperationTabs } from './operation-tabs';

/**
 * Income and expenses coming back at fixed dates (rent, subscriptions, salary): each date is
 * recorded as an operation by the API, the missed ones included.
 */
@Component({
  selector: 'app-recurring-page',
  imports: [ReactiveFormsModule, Modal, Icon, EurosPipe, DayPipe, OperationTabs],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './recurring.page.html',
  styleUrl: './operations.page.scss',
})
export class RecurringPage {
  private readonly api = inject(BudgetApi);

  protected readonly recurring = rxResource({ stream: () => this.api.recurringOperations() });
  protected readonly accounts = rxResource({ stream: () => this.api.accounts() });
  protected readonly categories = rxResource({ stream: () => this.api.categories() });
  protected readonly loadError = computed(() =>
    this.recurring.error() ? apiErrorMessage(this.recurring.error()) : null,
  );

  protected readonly current = computed(() =>
    (this.recurring.value() ?? []).filter((r) => r.active && !r.finished),
  );
  protected readonly stopped = computed(() =>
    (this.recurring.value() ?? []).filter((r) => !r.active || r.finished),
  );
  /** What the active ones cost, brought back to one month */
  protected readonly monthlyTotals = computed(() => {
    const perMonth: Record<Frequency, number> = {
      weekly: 52 / 12,
      monthly: 1,
      quarterly: 1 / 3,
      yearly: 1 / 12,
    };
    const totals = { income: 0, expenses: 0 };
    for (const r of this.current()) {
      const monthly = Math.round(Math.abs(r.amount) * perMonth[r.frequency]);
      totals[r.amount < 0 ? 'expenses' : 'income'] += monthly;
    }
    return totals;
  });

  private readonly categoriesById = computed(
    () => new Map((this.categories.value() ?? []).map((c) => [c.id, c])),
  );
  private readonly accountsById = computed(
    () => new Map((this.accounts.value() ?? []).map((a) => [a.id, a])),
  );

  protected readonly frequencies = FREQUENCY_LABELS;
  protected readonly frequencyOptions = Object.entries(FREQUENCY_LABELS) as [Frequency, string][];
  protected readonly formatLongDay = formatLongDay;

  // Add / edit dialog

  protected readonly editing = signal<RecurringOperation | 'new' | null>(null);
  protected readonly pending = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    type: ['expense' as CategoryKind],
    amount: ['', Validators.required],
    label: ['', Validators.required],
    frequency: ['monthly' as Frequency],
    nextOn: [todayIso(), Validators.required],
    hasEnd: [false],
    endsOn: [''],
    accountId: [0, Validators.min(1)],
    categoryId: [0],
    active: [true],
  });
  private readonly formType = toSignal(this.form.controls.type.valueChanges, {
    initialValue: this.form.controls.type.value,
  });
  protected readonly hasEnd = toSignal(this.form.controls.hasEnd.valueChanges, {
    initialValue: this.form.controls.hasEnd.value,
  });
  private readonly nextOn = toSignal(this.form.controls.nextOn.valueChanges, {
    initialValue: this.form.controls.nextOn.value,
  });
  protected readonly formCategories = computed(() =>
    (this.categories.value() ?? []).filter((c) => c.kind === this.formType()),
  );
  protected readonly catchUpWarning = computed(() => (this.nextOn() || todayIso()) < todayIso());

  protected category(id: number | null): Category | undefined {
    return id === null ? undefined : this.categoriesById().get(id);
  }

  protected accountName(id: number): string {
    return this.accountsById().get(id)?.name ?? '';
  }

  protected openNew(): void {
    this.form.reset({
      type: 'expense',
      amount: '',
      label: '',
      frequency: 'monthly',
      nextOn: todayIso(),
      hasEnd: false,
      endsOn: '',
      accountId: this.accounts.value()?.[0]?.id ?? 0,
      categoryId: 0,
      active: true,
    });
    this.formError.set(null);
    this.editing.set('new');
  }

  protected openEdit(recurring: RecurringOperation): void {
    this.form.reset({
      type: recurring.amount < 0 ? 'expense' : 'income',
      amount: centsToInput(Math.abs(recurring.amount)),
      label: recurring.label,
      frequency: recurring.frequency,
      nextOn: recurring.nextOn,
      hasEnd: recurring.endsOn !== null,
      endsOn: recurring.endsOn ?? '',
      accountId: recurring.accountId,
      categoryId: recurring.categoryId ?? 0,
      active: recurring.active,
    });
    this.formError.set(null);
    this.editing.set(recurring);
  }

  protected async save(): Promise<void> {
    const value = this.form.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.form.invalid || amount === null || amount <= 0 || (value.hasEnd && !value.endsOn)) {
      this.formError.set(
        'Indiquez un montant positif, un libellé, la prochaine date et un compte.',
      );
      return;
    }
    const editing = this.editing();
    this.pending.set(true);
    this.formError.set(null);
    try {
      await firstValueFrom(
        this.api.saveRecurringOperation(this.isEditing(editing) ? editing.id : null, {
          type: value.type,
          amount,
          label: value.label,
          frequency: value.frequency,
          nextOn: value.nextOn,
          endsOn: value.hasEnd ? value.endsOn : null,
          accountId: value.accountId,
          categoryId: value.categoryId || null,
          active: value.active,
        }),
      );
      this.editing.set(null);
      this.recurring.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }

  protected async remove(recurring: RecurringOperation): Promise<void> {
    if (
      !confirm(`Supprimer « ${recurring.label} » ? Les opérations déjà enregistrées sont gardées.`)
    ) {
      return;
    }
    try {
      await firstValueFrom(this.api.deleteRecurringOperation(recurring.id));
      this.editing.set(null);
      this.recurring.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    }
  }

  protected isEditing(editing: RecurringOperation | 'new' | null): editing is RecurringOperation {
    return editing !== null && editing !== 'new';
  }
}
