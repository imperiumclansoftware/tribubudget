import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  linkedSignal,
  signal,
} from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Observable, firstValueFrom, of } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import {
  AllowanceFrequency,
  PocketMoney,
  PocketMoneyEntry,
  PocketMoneyKind,
} from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { BudgetApi } from '../../core/budget-api';
import { FREQUENCY_LABELS, POCKET_MONEY_KIND_LABELS } from '../../core/labels';
import { centsToInput, formatLongDay, parseEuros, todayIso } from '../../core/money';
import { DayPipe, EurosPipe } from '../../shared/euros.pipe';
import { Icon, IconName } from '../../shared/icon';
import { Modal } from '../../shared/modal';

const KIND_ICONS: Record<PocketMoneyKind, IconName> = {
  allowance: 'calendar',
  expense: 'cart',
  advance: 'inbox',
  repayment: 'minus',
  adjustment: 'gift',
};

/**
 * A child's piggy bank. The child sees their own (/ma-tirelire) and records their expenses;
 * a parent sees any child's (/famille/:childId), gives or takes money and sets the allowance.
 */
@Component({
  selector: 'app-pocket-money-page',
  imports: [ReactiveFormsModule, RouterLink, Modal, Icon, EurosPipe, DayPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './pocket-money.page.html',
  styleUrl: './pocket-money.page.scss',
})
export class PocketMoneyPage {
  private readonly api = inject(BudgetApi);
  private readonly fb = inject(NonNullableFormBuilder);
  protected readonly auth = inject(AuthService);

  /** Route parameter on the parents' page; absent on the child's own page */
  readonly childId = input<string>();

  private readonly id = computed(() => Number(this.childId() ?? this.auth.user()?.id));
  private readonly resource = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.pocketMoney(params),
  });
  protected readonly accounts = rxResource({
    params: () => this.auth.isParent(),
    stream: ({ params: isParent }) => (isParent ? this.api.accounts() : of([])),
  });
  /** Reloaded with the child; replaced by the answer of each change */
  protected readonly data = linkedSignal(() => this.resource.value());
  protected readonly loadError = computed(() =>
    this.resource.error() ? apiErrorMessage(this.resource.error()) : null,
  );

  protected readonly kindLabels = POCKET_MONEY_KIND_LABELS;
  protected readonly kindIcons = KIND_ICONS;
  protected readonly frequencies = FREQUENCY_LABELS;
  protected readonly formatLongDay = formatLongDay;
  protected readonly today = todayIso();
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);
  protected readonly dialog = signal<'expense' | 'adjust' | 'allowance' | null>(null);

  protected readonly expenseForm = this.fb.group({
    amount: ['', Validators.required],
    label: ['', Validators.required],
    date: [todayIso(), Validators.required],
  });

  protected readonly adjustForm = this.fb.group({
    direction: ['give' as 'give' | 'take'],
    amount: ['', Validators.required],
    label: ['', Validators.required],
  });

  protected readonly allowanceForm = this.fb.group({
    amount: ['', Validators.required],
    frequency: ['weekly' as AllowanceFrequency],
    nextPaymentOn: [todayIso(), Validators.required],
    accountId: [0],
    active: [true],
  });

  protected open(dialog: 'expense' | 'adjust' | 'allowance'): void {
    this.error.set(null);
    if (dialog === 'expense') {
      this.expenseForm.reset({ amount: '', label: '', date: todayIso() });
    } else if (dialog === 'adjust') {
      this.adjustForm.reset({ direction: 'give', amount: '', label: '' });
    } else {
      const allowance = this.data()?.allowance;
      this.allowanceForm.reset(
        allowance
          ? {
              ...allowance,
              amount: centsToInput(allowance.amount),
              accountId: allowance.accountId ?? 0,
            }
          : {
              amount: '',
              frequency: 'weekly',
              nextPaymentOn: todayIso(),
              accountId: this.accounts.value()?.[0]?.id ?? 0,
              active: true,
            },
      );
    }
    this.dialog.set(dialog);
  }

  protected async spend(): Promise<void> {
    const value = this.expenseForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.expenseForm.invalid || amount === null || amount <= 0) {
      this.error.set(
        this.auth.isChild()
          ? 'Indique combien tu as dépensé et pour quoi.'
          : 'Indiquez le montant et le libellé.',
      );
      return;
    }
    await this.save(this.api.spend(this.id(), { ...value, amount }));
  }

  protected async adjust(): Promise<void> {
    const value = this.adjustForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.adjustForm.invalid || amount === null || amount <= 0) {
      this.error.set('Indiquez un montant positif et un libellé.');
      return;
    }
    await this.save(
      this.api.adjust(this.id(), {
        amount: value.direction === 'give' ? amount : -amount,
        label: value.label,
      }),
    );
  }

  protected async saveAllowance(): Promise<void> {
    const value = this.allowanceForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.allowanceForm.invalid || amount === null || amount <= 0) {
      this.error.set('Indiquez un montant positif et la date du prochain versement.');
      return;
    }
    await this.save(
      this.api.setAllowance(this.id(), { ...value, amount, accountId: value.accountId || null }),
    );
  }

  protected async removeAllowance(): Promise<void> {
    if (confirm('Arrêter l’argent de poche automatique ?')) {
      await this.save(this.api.removeAllowance(this.id()));
    }
  }

  protected async remove(entry: PocketMoneyEntry): Promise<void> {
    const question = this.auth.isChild()
      ? `Effacer « ${entry.label} » ?`
      : `Supprimer le mouvement « ${entry.label} » ?`;
    if (confirm(question)) {
      await this.save(this.api.deleteEntry(entry.id));
    }
  }

  private async save(request: Observable<PocketMoney>): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      this.data.set(await firstValueFrom(request));
      this.dialog.set(null);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
