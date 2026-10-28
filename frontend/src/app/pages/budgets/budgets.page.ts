import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Observable, firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Category } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { centsToInput, currentMonth, parseEuros } from '../../core/money';
import { BudgetMeter } from '../../shared/budget-meter';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { MonthPicker } from '../../shared/month-picker';

/** In the category list of a new budget: create the category along with its budget */
const NEW_CATEGORY = -1;

/**
 * A monthly spending limit per expense category, and how the chosen month compares. A budget is
 * created on an existing category or on a new one; deleting a budget keeps its category.
 */
@Component({
  selector: 'app-budgets-page',
  imports: [ReactiveFormsModule, MonthPicker, BudgetMeter, Modal, Icon, EurosPipe],
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

  // Dialog: a new budget, or the amount of an existing one

  protected readonly NEW_CATEGORY = NEW_CATEGORY;
  protected readonly editing = signal<Category | 'new' | null>(null);
  protected readonly pending = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    categoryId: [NEW_CATEGORY],
    name: [''],
    color: ['#5b8e7d'],
    amount: ['', Validators.required],
  });
  private readonly categoryId = toSignal(this.form.controls.categoryId.valueChanges, {
    initialValue: this.form.controls.categoryId.value,
  });
  protected readonly heading = computed(() => {
    const editing = this.editing();
    return this.isEditing(editing) ? `Budget : ${editing.name}` : 'Nouveau budget';
  });
  protected readonly creatingCategory = computed(
    () => this.editing() === 'new' && this.categoryId() === NEW_CATEGORY,
  );

  /** A new budget, on the given category without budget, or else on the first one (or a new category) */
  protected openNew(category?: Category): void {
    this.form.reset({
      categoryId: category?.id ?? this.withoutBudget()[0]?.category.id ?? NEW_CATEGORY,
      name: '',
      color: '#5b8e7d',
      amount: '',
    });
    this.formError.set(null);
    this.editing.set('new');
  }

  protected openEdit(category: Category): void {
    this.form.reset({
      categoryId: category.id,
      name: category.name,
      color: category.color,
      amount: category.monthlyBudget === null ? '' : centsToInput(category.monthlyBudget),
    });
    this.formError.set(null);
    this.editing.set(category);
  }

  protected close(): void {
    this.editing.set(null);
    this.formError.set(null);
  }

  protected async save(): Promise<void> {
    const value = this.form.getRawValue();
    const budget = parseEuros(value.amount);
    if (budget === null || budget <= 0) {
      this.formError.set('Indiquez un plafond positif, par exemple 400.');
      return;
    }
    if (this.creatingCategory()) {
      if (!value.name.trim()) {
        this.formError.set('Donnez un nom à la nouvelle catégorie.');
        return;
      }
      await this.run(
        this.api.saveCategory(null, {
          name: value.name,
          kind: 'expense',
          color: value.color,
          monthlyBudget: budget,
        }),
      );
      return;
    }
    const editing = this.editing();
    const category =
      editing === 'new'
        ? this.withoutBudget().find((r) => r.category.id === value.categoryId)?.category
        : editing;
    if (!category) {
      this.formError.set('Choisissez une catégorie.');
      return;
    }
    await this.run(this.withBudgetOf(category, budget));
  }

  /** Deletes the budget, not the category: its operations stay classified */
  protected async remove(category: Category): Promise<void> {
    if (
      confirm(
        `Supprimer le budget « ${category.name} » ? La catégorie et ses opérations sont gardées.`,
      )
    ) {
      await this.run(this.withBudgetOf(category, null));
    }
  }

  private withBudgetOf(category: Category, monthlyBudget: number | null): Observable<Category> {
    return this.api.saveCategory(category.id, {
      name: category.name,
      kind: category.kind,
      color: category.color,
      monthlyBudget,
    });
  }

  private async run(request: Observable<unknown>): Promise<void> {
    this.pending.set(true);
    this.formError.set(null);
    try {
      await firstValueFrom(request);
      this.close();
      this.categories.reload();
      this.dashboard.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }

  protected isEditing(editing: Category | 'new' | null): editing is Category {
    return editing !== null && editing !== 'new';
  }
}
