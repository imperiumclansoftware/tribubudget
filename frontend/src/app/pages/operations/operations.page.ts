import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  input,
  linkedSignal,
  signal,
  untracked,
} from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import {
  FormsModule,
  NonNullableFormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router } from '@angular/router';
import { Observable, firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Account, Category, CategoryKind, Operation } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { centsToInput, currentMonth, formatLongDay, parseEuros, todayIso } from '../../core/money';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { MonthPicker } from '../../shared/month-picker';
import { ReconcileCheck } from '../../shared/reconcile-check';
import { OperationTabs } from './operation-tabs';

/** Income and expenses of a month, grouped by day, with filters and an add/edit dialog. */
@Component({
  selector: 'app-operations-page',
  imports: [
    ReactiveFormsModule,
    FormsModule,
    NgTemplateOutlet,
    MonthPicker,
    Modal,
    Icon,
    EurosPipe,
    OperationTabs,
    ReconcileCheck,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './operations.page.html',
  styleUrl: './operations.page.scss',
})
export class OperationsPage {
  private readonly api = inject(BudgetApi);
  private readonly router = inject(Router);

  protected readonly month = signal(currentMonth());
  protected readonly accountFilter = signal<number | null>(null);
  protected readonly categoryFilter = signal<number | null>(null);

  /** With the teenagers': a transfer may go to them, and the history names them */
  private readonly allAccounts = rxResource({ stream: () => this.api.accounts(true) });
  /** The accounts of the household budget, the only ones its operations use */
  protected readonly accounts = computed(() =>
    (this.allAccounts.value() ?? []).filter((a) => !a.owner),
  );
  /** The teenagers' accounts, grouped by teenager, as transfer targets */
  protected readonly teenAccounts = computed(() => {
    const groups = new Map<number, { name: string; accounts: Account[] }>();
    for (const account of this.allAccounts.value() ?? []) {
      if (account.owner) {
        const group = groups.get(account.owner.id) ?? {
          name: account.owner.displayName,
          accounts: [],
        };
        group.accounts.push(account);
        groups.set(account.owner.id, group);
      }
    }
    return [...groups.values()];
  });
  protected readonly categories = rxResource({ stream: () => this.api.categories() });
  private readonly operations = rxResource({
    params: () => ({
      month: this.month(),
      accountId: this.accountFilter() ?? undefined,
      categoryId: this.categoryFilter() ?? undefined,
    }),
    stream: ({ params: { month, ...filters } }) => this.api.operations(month, filters),
  });
  protected readonly list = linkedSignal<Operation[] | undefined, Operation[] | undefined>({
    source: () => this.operations.value(),
    computation: (value, previous) => value ?? previous?.value,
  });
  protected readonly loading = this.operations.isLoading;
  protected readonly loadError = computed(() =>
    this.operations.error() ? apiErrorMessage(this.operations.error()) : null,
  );
  protected readonly actionError = signal<string | null>(null);
  /** The account filtered on, with its reconciled balance to compare with the bank statement */
  protected readonly filteredAccount = computed(() => {
    const id = this.accountFilter();
    return id === null ? undefined : this.accountsById().get(id);
  });

  /** An operation ticked off or unticked: its balance changes the account's reconciled one */
  protected reconciled(operation: Operation): void {
    this.actionError.set(null);
    this.list.update((list) =>
      list?.map((o) => (o.id === operation.id ? { ...o, reconciled: operation.reconciled } : o)),
    );
    this.allAccounts.reload();
  }

  private readonly categoriesById = computed(
    () => new Map((this.categories.value() ?? []).map((c) => [c.id, c])),
  );
  private readonly accountsById = computed(
    () => new Map((this.allAccounts.value() ?? []).map((a) => [a.id, a])),
  );

  protected readonly days = computed(() => {
    const days = new Map<string, Operation[]>();
    for (const operation of this.list() ?? []) {
      days.set(operation.date, [...(days.get(operation.date) ?? []), operation]);
    }
    return [...days].map(([date, operations]) => ({
      date,
      label: formatLongDay(date),
      operations,
    }));
  });
  protected readonly totals = computed(() => {
    const operations = this.list() ?? [];
    return {
      income: operations.filter((o) => o.amount > 0).reduce((sum, o) => sum + o.amount, 0),
      expenses: operations.filter((o) => o.amount < 0).reduce((sum, o) => sum - o.amount, 0),
    };
  });

  // Add / edit dialog

  protected readonly editing = signal<Operation | 'new' | null>(null);
  protected readonly pending = signal(false);
  protected readonly formError = signal<string | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    type: ['expense' as CategoryKind],
    amount: ['', Validators.required],
    label: ['', Validators.required],
    date: [todayIso(), Validators.required],
    accountId: [0, Validators.min(1)],
    categoryId: [0],
  });
  /** Categories matching the chosen type, for the dialog */
  private readonly formType = toSignal(this.form.controls.type.valueChanges, {
    initialValue: this.form.controls.type.value,
  });
  protected readonly formCategories = computed(() =>
    (this.categories.value() ?? []).filter((c) => c.kind === this.formType()),
  );

  protected category(id: number | null): Category | undefined {
    return id === null ? undefined : this.categoriesById().get(id);
  }

  protected accountName(id: number): string {
    const account = this.accountsById().get(id);
    return account?.owner
      ? `${account.name} (${account.owner.displayName})`
      : (account?.name ?? '');
  }

  protected openNew(): void {
    const type: CategoryKind = 'expense';
    this.form.reset({
      type,
      amount: '',
      label: '',
      date: this.month() === currentMonth() ? todayIso() : `${this.month()}-01`,
      accountId: this.accountFilter() ?? this.accounts()[0]?.id ?? 0,
      categoryId: this.categoryFilter() ?? 0,
    });
    this.formError.set(null);
    this.editing.set('new');
  }

  protected openEdit(operation: Operation): void {
    const type: CategoryKind = operation.amount < 0 ? 'expense' : 'income';
    this.form.reset({
      type,
      amount: centsToInput(Math.abs(operation.amount)),
      label: operation.label,
      date: operation.date,
      accountId: operation.accountId,
      categoryId: operation.categoryId ?? 0,
    });
    this.formError.set(null);
    this.editing.set(operation);
  }

  protected async save(): Promise<void> {
    const value = this.form.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.form.invalid || amount === null || amount <= 0) {
      this.formError.set('Indiquez un montant positif, un libellé, une date et un compte.');
      return;
    }
    const editing = this.editing();
    this.pending.set(true);
    this.formError.set(null);
    try {
      await firstValueFrom(
        this.api.saveOperation(editing === 'new' || editing === null ? null : editing.id, {
          type: value.type,
          amount,
          label: value.label,
          date: value.date,
          accountId: value.accountId,
          categoryId: value.categoryId || null,
        }),
      );
      this.editing.set(null);
      this.operations.reload();
      this.allAccounts.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }

  protected async remove(operation: Operation): Promise<void> {
    if (!confirm(`Supprimer l’opération « ${operation.label} » ?`)) {
      return;
    }
    try {
      await firstValueFrom(this.api.deleteOperation(operation.id));
      this.editing.set(null);
      this.operations.reload();
      this.allAccounts.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    }
  }

  // Transfer dialog: { id: null } for a new transfer

  /** ?virement=1 (from the accounts page) opens the transfer dialog */
  readonly virement = input<string>();
  protected readonly transferEditing = signal<{ id: number | null } | null>(null);
  protected readonly transferForm = inject(NonNullableFormBuilder).group({
    fromAccountId: [0, Validators.min(1)],
    toAccountId: [0, Validators.min(1)],
    amount: ['', Validators.required],
    date: [todayIso(), Validators.required],
    label: [''],
  });
  private readonly transferValue = toSignal(this.transferForm.valueChanges, {
    initialValue: this.transferForm.getRawValue(),
  });
  protected readonly transferFrom = computed(() => this.transferValue().fromAccountId);
  /** Balance of the debited account before and after, as the parent types */
  protected readonly transferPreview = computed(() => {
    const value = this.transferValue();
    const from = this.accountsById().get(value.fromAccountId ?? 0);
    const to = this.accountsById().get(value.toAccountId ?? 0);
    const amount = parseEuros(value.amount ?? '');
    if (!from || !to || amount === null || amount <= 0) {
      return null;
    }
    // When editing, the balance already includes the transfer being changed
    const editing = this.transferEditing();
    const already = editing?.id ? this.editedTransferEffect(from.id) : 0;
    const before = from.balance + already;
    return {
      fromName: this.accountName(from.id),
      fromBefore: before,
      fromAfter: before - amount,
      toName: this.accountName(to.id),
      // Only the household's savings feed the projects
      toSavings: to.type === 'savings' && !to.owner,
    };
  });
  private editedTransfer: { fromAccountId: number; toAccountId: number; amount: number } | null =
    null;

  constructor() {
    // Once only: then the parameter leaves the URL, so that reloading the accounts after the
    // transfer, or the page, does not open the dialog again
    effect(() => {
      if (this.virement() && this.accounts().length) {
        untracked(() => {
          this.openTransfer();
          void this.router.navigate([], { queryParams: { virement: null }, replaceUrl: true });
        });
      }
    });
  }

  protected openTransfer(operation?: Operation): void {
    const accounts = this.accounts();
    if (operation?.transfer) {
      const out = operation.amount < 0;
      const fromAccountId = out ? operation.accountId : operation.transfer.counterpartAccountId;
      const toAccountId = out ? operation.transfer.counterpartAccountId : operation.accountId;
      this.editedTransfer = { fromAccountId, toAccountId, amount: Math.abs(operation.amount) };
      this.transferForm.reset({
        fromAccountId,
        toAccountId,
        amount: centsToInput(Math.abs(operation.amount)),
        date: operation.date,
        label: operation.label,
      });
      this.transferEditing.set({ id: operation.transfer.id });
    } else {
      // By default: from the current account to the first savings account
      const from = accounts.find((a) => a.type === 'checking') ?? accounts[0];
      const to =
        accounts.find((a) => a.type === 'savings' && a.id !== from?.id) ??
        accounts.find((a) => a.id !== from?.id);
      this.editedTransfer = null;
      this.transferForm.reset({
        fromAccountId: from?.id ?? 0,
        toAccountId: to?.id ?? 0,
        amount: '',
        date: this.month() === currentMonth() ? todayIso() : `${this.month()}-01`,
        label: '',
      });
      this.transferEditing.set({ id: null });
    }
    this.formError.set(null);
  }

  protected closeTransfer(): void {
    this.transferEditing.set(null);
    this.formError.set(null);
  }

  protected async saveTransfer(): Promise<void> {
    const value = this.transferForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.transferForm.invalid || amount === null || amount <= 0) {
      this.formError.set('Choisissez les deux comptes et indiquez un montant positif.');
      return;
    }
    if (value.fromAccountId === value.toAccountId) {
      this.formError.set('Choisissez deux comptes différents.');
      return;
    }
    await this.runTransfer(
      this.api.saveTransfer(this.transferEditing()?.id ?? null, { ...value, amount }),
    );
  }

  protected async removeTransfer(): Promise<void> {
    const id = this.transferEditing()?.id;
    if (id && confirm('Supprimer ce virement ? Les deux comptes retrouvent leur solde d’avant.')) {
      await this.runTransfer(this.api.deleteTransfer(id));
    }
  }

  /** What the transfer being changed already did to an account's balance, to undo in the preview */
  private editedTransferEffect(accountId: number): number {
    const edited = this.editedTransfer;
    if (edited?.fromAccountId === accountId) {
      return edited.amount;
    }
    return edited?.toAccountId === accountId ? -edited.amount : 0;
  }

  private async runTransfer(request: Observable<unknown>): Promise<void> {
    this.pending.set(true);
    this.formError.set(null);
    try {
      await firstValueFrom(request);
      this.closeTransfer();
      this.operations.reload();
      this.allAccounts.reload();
    } catch (e) {
      this.formError.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }

  protected isEditing(editing: Operation | 'new' | null): editing is Operation {
    return editing !== null && editing !== 'new';
  }
}
