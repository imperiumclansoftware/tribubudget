import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Account, AccountType, Category, CategoryKind } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { ACCOUNT_TYPE_LABELS } from '../../core/labels';
import { centsToInput, parseEuros } from '../../core/money';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';

/** Accounts of the household and categories of the operations. */
@Component({
  selector: 'app-settings-page',
  imports: [ReactiveFormsModule, RouterLink, Modal, Icon, EurosPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './settings.page.html',
})
export class SettingsPage {
  private readonly api = inject(BudgetApi);
  private readonly fb = inject(NonNullableFormBuilder);

  protected readonly accounts = rxResource({ stream: () => this.api.accounts() });
  protected readonly categories = rxResource({ stream: () => this.api.categories() });
  protected readonly categoryGroups = computed(() => {
    const categories = this.categories.value() ?? [];
    return [
      {
        title: 'Catégories de dépenses',
        kind: 'expense' as const,
        items: categories.filter((c) => c.kind === 'expense'),
      },
      {
        title: 'Catégories de revenus',
        kind: 'income' as const,
        items: categories.filter((c) => c.kind === 'income'),
      },
    ];
  });
  protected readonly accountTypes = ACCOUNT_TYPE_LABELS;
  protected readonly accountTypeOptions = Object.entries(ACCOUNT_TYPE_LABELS) as [
    AccountType,
    string,
  ][];
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);

  // Accounts

  protected readonly editingAccount = signal<Account | 'new' | null>(null);
  protected readonly accountForm = this.fb.group({
    name: ['', Validators.required],
    type: ['checking' as AccountType],
    initialBalance: ['0'],
    minimumBalance: [''],
  });
  protected readonly accountType = toSignal(this.accountForm.controls.type.valueChanges, {
    initialValue: this.accountForm.controls.type.value,
  });

  protected openAccount(account: Account | 'new'): void {
    this.accountForm.reset(
      account === 'new'
        ? { name: '', type: 'checking', initialBalance: '0' }
        : {
            name: account.name,
            type: account.type,
            initialBalance: centsToInput(account.initialBalance),
          },
    );
    this.error.set(null);
    this.editingAccount.set(account);
  }

  protected async saveAccount(): Promise<void> {
    const value = this.accountForm.getRawValue();
    const initialBalance = parseEuros(value.initialBalance || '0');
    const minimumBalance =
      value.type === 'savings' && value.minimumBalance.trim()
        ? parseEuros(value.minimumBalance)
        : null;
    if (this.accountForm.invalid || initialBalance === null) {
      this.error.set('Indiquez un nom et un solde de départ (0 si vous ne savez pas).');
      return;
    }
    if (
      value.type === 'savings' &&
      value.minimumBalance.trim() &&
      (minimumBalance === null || minimumBalance < 0)
    ) {
      this.error.set('Le plancher est un montant positif, ou vide pour aucun.');
      return;
    }
    const editing = this.editingAccount();
    await this.run(async () => {
      await firstValueFrom(
        this.api.saveAccount(editing === 'new' || !editing ? null : editing.id, {
          name: value.name,
          type: value.type,
          initialBalance,
          minimumBalance,
        }),
      );
      this.editingAccount.set(null);
      this.accounts.reload();
    });
  }

  protected async deleteAccount(account: Account): Promise<void> {
    if (confirm(`Supprimer le compte « ${account.name} » ?`)) {
      await this.run(async () => {
        await firstValueFrom(this.api.deleteAccount(account.id));
        this.editingAccount.set(null);
        this.accounts.reload();
      });
    }
  }

  // Categories

  protected readonly editingCategory = signal<Category | 'new' | null>(null);
  protected readonly categoryForm = this.fb.group({
    name: ['', Validators.required],
    kind: ['expense' as CategoryKind],
    color: ['#5b8e7d'],
  });

  protected openCategory(category: Category | 'new', kind: CategoryKind = 'expense'): void {
    this.categoryForm.reset(
      category === 'new'
        ? { name: '', kind, color: '#5b8e7d' }
        : { name: category.name, kind: category.kind, color: category.color },
    );
    this.error.set(null);
    this.editingCategory.set(category);
  }

  protected async saveCategory(): Promise<void> {
    if (this.categoryForm.invalid) {
      this.error.set('Donnez un nom à la catégorie.');
      return;
    }
    const editing = this.editingCategory();
    const existing = editing === 'new' || !editing ? null : editing;
    await this.run(async () => {
      await firstValueFrom(
        this.api.saveCategory(existing?.id ?? null, {
          ...this.categoryForm.getRawValue(),
          monthlyBudget: existing?.monthlyBudget ?? null,
        }),
      );
      this.editingCategory.set(null);
      this.categories.reload();
    });
  }

  protected async deleteCategory(category: Category): Promise<void> {
    if (
      confirm(
        `Supprimer la catégorie « ${category.name} » ? Ses opérations seront gardées, sans catégorie.`,
      )
    ) {
      await this.run(async () => {
        await firstValueFrom(this.api.deleteCategory(category.id));
        this.editingCategory.set(null);
        this.categories.reload();
      });
    }
  }

  protected isExisting<T>(value: T | 'new' | null): value is T {
    return value !== null && value !== 'new';
  }

  private async run(action: () => Promise<void>): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      await action();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
