import { LowerCasePipe, NgTemplateOutlet } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  linkedSignal,
  signal,
} from '@angular/core';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Observable, firstValueFrom, of } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AllowanceFrequency, CategoryKind, Operation } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { BudgetApi } from '../../core/budget-api';
import { ACCOUNT_TYPE_LABELS, FREQUENCY_LABELS, REQUEST_STATUS_LABELS } from '../../core/labels';
import { centsToInput, currentMonth, formatLongDay, parseEuros, todayIso } from '../../core/money';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';
import { MonthPicker } from '../../shared/month-picker';
import { ReconcileCheck } from '../../shared/reconcile-check';

/**
 * A teenager's accounts, outside the household budget. The teenager sees their own
 * (/mes-comptes): they record their operations, move money between their accounts and ask to be
 * paid back for an expense. A parent oversees any teenager's (/famille/ado/:teenId): they record
 * operations too, transfer money from the household and set the allowance.
 */
@Component({
  selector: 'app-teen-accounts-page',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    NgTemplateOutlet,
    LowerCasePipe,
    MonthPicker,
    Modal,
    Icon,
    EurosPipe,
    ReconcileCheck,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './teen-accounts.page.html',
  styleUrl: './teen-accounts.page.scss',
})
export class TeenAccountsPage {
  private readonly api = inject(BudgetApi);
  private readonly fb = inject(NonNullableFormBuilder);
  protected readonly auth = inject(AuthService);

  /** Route parameter on the parents' page; absent on the teenager's own page */
  readonly teenId = input<string>();

  private readonly id = computed(() => Number(this.teenId() ?? this.auth.user()?.id));
  private readonly resource = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.teen(params),
  });
  /** Reloaded with the teenager; replaced by the answer of each change */
  protected readonly teen = linkedSignal(() => this.resource.value());
  protected readonly loadError = computed(() =>
    this.resource.error() ? apiErrorMessage(this.resource.error()) : null,
  );
  /** Parents only: the household's accounts, where transfers and the allowance come from */
  protected readonly household = rxResource({
    params: () => this.auth.isParent(),
    stream: ({ params: isParent }) => (isParent ? this.api.accounts() : of([])),
  });
  protected readonly accounts = computed(() => this.teen()?.accounts ?? []);
  /** Accounts a transfer may use: the teenager's, and the household's for a parent */
  protected readonly transferAccounts = computed(() => [
    ...this.accounts(),
    ...(this.household.value() ?? []),
  ]);
  private readonly accountsById = computed(
    () => new Map(this.transferAccounts().map((a) => [a.id, a])),
  );

  protected readonly month = signal(currentMonth());
  protected readonly accountFilter = signal<number | null>(null);
  private readonly operations = rxResource({
    params: () => ({
      month: this.month(),
      accountId: this.accountFilter() ?? undefined,
      ownerId: this.id(),
    }),
    stream: ({ params: { month, ...filters } }) => this.api.operations(month, filters),
  });
  protected readonly list = linkedSignal<Operation[] | undefined, Operation[] | undefined>({
    source: () => this.operations.value(),
    computation: (value, previous) => value ?? previous?.value,
  });
  protected readonly loading = this.operations.isLoading;
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

  protected readonly accountTypes = ACCOUNT_TYPE_LABELS;
  protected readonly frequencies = FREQUENCY_LABELS;
  protected readonly statusLabels = REQUEST_STATUS_LABELS;
  protected readonly formatLongDay = formatLongDay;
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);

  protected accountName(id: number): string | null {
    return this.accountsById().get(id)?.name ?? null;
  }

  /** An expense of the teenager's that the parents have not paid back yet, nor been asked to */
  protected canAskReimbursement(operation: Operation): boolean {
    return (
      this.auth.isTeen() &&
      operation.amount < 0 &&
      !operation.transfer &&
      (!operation.reimbursement ||
        ['rejected', 'cancelled'].includes(operation.reimbursement.status))
    );
  }

  /** An operation ticked off or unticked: its balance changes the account's reconciled one */
  protected reconciled(operation: Operation): void {
    this.error.set(null);
    this.list.update((list) =>
      list?.map((o) => (o.id === operation.id ? { ...o, reconciled: operation.reconciled } : o)),
    );
    this.resource.reload();
  }

  // Operation dialog

  protected readonly editing = signal<Operation | 'new' | null>(null);
  protected readonly operationForm = this.fb.group({
    type: ['expense' as CategoryKind],
    amount: ['', Validators.required],
    label: ['', Validators.required],
    date: [todayIso(), Validators.required],
    accountId: [0, Validators.min(1)],
    askReimbursement: [false],
  });
  protected readonly operationType = toSignal(this.operationForm.controls.type.valueChanges, {
    initialValue: this.operationForm.controls.type.value,
  });

  protected openNew(): void {
    this.operationForm.reset({
      type: 'expense',
      amount: '',
      label: '',
      date: this.month() === currentMonth() ? todayIso() : `${this.month()}-01`,
      accountId: this.accountFilter() ?? this.accounts()[0]?.id ?? 0,
      askReimbursement: false,
    });
    this.error.set(null);
    this.editing.set('new');
  }

  protected openEdit(operation: Operation): void {
    if (operation.transfer) {
      this.openTransfer(operation);
      return;
    }
    this.operationForm.reset({
      type: operation.amount < 0 ? 'expense' : 'income',
      amount: centsToInput(Math.abs(operation.amount)),
      label: operation.label,
      date: operation.date,
      accountId: operation.accountId,
      askReimbursement: false,
    });
    this.error.set(null);
    this.editing.set(operation);
  }

  protected async saveOperation(): Promise<void> {
    const value = this.operationForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.operationForm.invalid || amount === null || amount <= 0) {
      this.error.set(
        this.auth.isTeen()
          ? 'Indique un montant positif, un libellé, une date et un compte.'
          : 'Indiquez un montant positif, un libellé, une date et un compte.',
      );
      return;
    }
    const editing = this.editing();
    const existing = editing === 'new' || editing === null ? null : editing;
    await this.run(async () => {
      const saved = await firstValueFrom(
        this.api.saveOperation(existing?.id ?? null, {
          type: value.type,
          amount,
          label: value.label,
          date: value.date,
          accountId: value.accountId,
          categoryId: existing?.categoryId ?? null,
        }),
      );
      if (!existing && value.type === 'expense' && value.askReimbursement) {
        await firstValueFrom(this.api.askReimbursement(saved.id));
      }
      this.editing.set(null);
    });
  }

  protected async removeOperation(operation: Operation): Promise<void> {
    if (confirm(`Supprimer « ${operation.label} » ?`)) {
      await this.run(async () => {
        await firstValueFrom(this.api.deleteOperation(operation.id));
        this.editing.set(null);
      });
    }
  }

  protected async askReimbursement(operation: Operation): Promise<void> {
    if (confirm(`Demander à tes parents de te rembourser « ${operation.label} » ?`)) {
      await this.run(() => firstValueFrom(this.api.askReimbursement(operation.id)));
    }
  }

  // Transfer dialog: { id: null } for a new transfer

  protected readonly transferEditing = signal<{ id: number | null } | null>(null);
  protected readonly transferForm = this.fb.group({
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

  protected openTransfer(operation?: Operation): void {
    if (operation?.transfer) {
      const out = operation.amount < 0;
      this.transferForm.reset({
        fromAccountId: out ? operation.accountId : operation.transfer.counterpartAccountId,
        toAccountId: out ? operation.transfer.counterpartAccountId : operation.accountId,
        amount: centsToInput(Math.abs(operation.amount)),
        date: operation.date,
        label: operation.label,
      });
      this.transferEditing.set({ id: operation.transfer.id });
    } else {
      // A parent gives from the household to the current account; a teenager saves
      const own = this.accounts();
      const checking = own.find((a) => a.type === 'checking') ?? own[0];
      const from = this.auth.isParent() ? (this.household.value() ?? [])[0] : checking;
      const to = this.auth.isParent()
        ? checking
        : (own.find((a) => a.type === 'savings' && a.id !== from?.id) ??
          own.find((a) => a.id !== from?.id));
      this.transferForm.reset({
        fromAccountId: from?.id ?? 0,
        toAccountId: to?.id ?? 0,
        amount: '',
        date: this.month() === currentMonth() ? todayIso() : `${this.month()}-01`,
        label: '',
      });
      this.transferEditing.set({ id: null });
    }
    this.error.set(null);
  }

  protected async saveTransfer(): Promise<void> {
    const value = this.transferForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.transferForm.invalid || amount === null || amount <= 0) {
      this.error.set(
        this.auth.isTeen()
          ? 'Choisis les deux comptes et indique un montant positif.'
          : 'Choisissez les deux comptes et indiquez un montant positif.',
      );
      return;
    }
    if (value.fromAccountId === value.toAccountId) {
      this.error.set(
        this.auth.isTeen()
          ? 'Choisis deux comptes différents.'
          : 'Choisissez deux comptes différents.',
      );
      return;
    }
    await this.run(async () => {
      await firstValueFrom(
        this.api.saveTransfer(this.transferEditing()?.id ?? null, { ...value, amount }),
      );
      this.transferEditing.set(null);
    });
  }

  protected async removeTransfer(): Promise<void> {
    const id = this.transferEditing()?.id;
    if (id && confirm('Supprimer ce virement ? Les deux comptes retrouvent leur solde d’avant.')) {
      await this.run(async () => {
        await firstValueFrom(this.api.deleteTransfer(id));
        this.transferEditing.set(null);
      });
    }
  }

  // Allowance dialog (parents)

  protected readonly allowanceOpen = signal(false);
  protected readonly allowanceForm = this.fb.group({
    amount: ['', Validators.required],
    frequency: ['monthly' as AllowanceFrequency],
    nextPaymentOn: [todayIso(), Validators.required],
    accountId: [0],
    active: [true],
  });

  protected openAllowance(): void {
    const allowance = this.teen()?.allowance;
    this.allowanceForm.reset(
      allowance
        ? {
            ...allowance,
            amount: centsToInput(allowance.amount),
            accountId: allowance.accountId ?? 0,
          }
        : {
            amount: '',
            frequency: 'monthly',
            nextPaymentOn: todayIso(),
            accountId: this.household.value()?.[0]?.id ?? 0,
            active: true,
          },
    );
    this.error.set(null);
    this.allowanceOpen.set(true);
  }

  protected async saveAllowance(): Promise<void> {
    const value = this.allowanceForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.allowanceForm.invalid || amount === null || amount <= 0) {
      this.error.set('Indiquez un montant positif et la date du prochain versement.');
      return;
    }
    await this.saveTeen(
      this.api.setTeenAllowance(this.id(), {
        ...value,
        amount,
        accountId: value.accountId || null,
      }),
    );
  }

  protected async removeAllowance(): Promise<void> {
    if (confirm('Arrêter l’argent de poche automatique ?')) {
      await this.saveTeen(this.api.removeTeenAllowance(this.id()));
    }
  }

  protected closeDialogs(): void {
    this.editing.set(null);
    this.transferEditing.set(null);
    this.allowanceOpen.set(false);
    this.error.set(null);
  }

  private async saveTeen(request: Observable<unknown>): Promise<void> {
    await this.run(async () => {
      await firstValueFrom(request);
      this.allowanceOpen.set(false);
    });
  }

  /** Runs a change, then reloads the balances and the operations */
  private async run(action: () => Promise<unknown>): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      await action();
      this.resource.reload();
      this.operations.reload();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
      // A step may have gone through (the operation saved, its request refused)
      this.operations.reload();
    } finally {
      this.pending.set(false);
    }
  }

  protected isEditing(editing: Operation | 'new' | null): editing is Operation {
    return editing !== null && editing !== 'new';
  }
}
