import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Observable, firstValueFrom, of } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { MoneyRequest, RequestKind } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { BudgetApi } from '../../core/budget-api';
import { REQUEST_KIND_LABELS, REQUEST_STATUS_LABELS } from '../../core/labels';
import { parseEuros } from '../../core/money';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';

/**
 * Children ask for an advance on their pocket money or for a purchase; parents accept or
 * refuse, with a word of explanation.
 */
@Component({
  selector: 'app-requests-page',
  imports: [ReactiveFormsModule, Modal, Icon, EurosPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './requests.page.html',
  styleUrl: './requests.page.scss',
})
export class RequestsPage {
  private readonly api = inject(BudgetApi);
  private readonly fb = inject(NonNullableFormBuilder);
  protected readonly auth = inject(AuthService);

  protected readonly requests = rxResource({ stream: () => this.api.requests() });
  protected readonly accounts = rxResource({
    params: () => this.auth.isParent(),
    stream: ({ params: isParent }) => (isParent ? this.api.accounts() : of([])),
  });
  protected readonly pending = computed(() =>
    (this.requests.value() ?? []).filter((r) => r.status === 'pending'),
  );
  protected readonly answered = computed(() =>
    (this.requests.value() ?? []).filter((r) => r.status !== 'pending'),
  );
  protected readonly loadError = computed(() =>
    this.requests.error() ? apiErrorMessage(this.requests.error()) : null,
  );

  protected readonly kindLabels = REQUEST_KIND_LABELS;
  protected readonly statusLabels = REQUEST_STATUS_LABELS;
  protected readonly error = signal<string | null>(null);
  protected readonly busy = signal(false);

  // A child's new request

  protected readonly asking = signal(false);
  protected readonly askForm = this.fb.group({
    kind: ['advance' as RequestKind],
    amount: ['', Validators.required],
    reason: ['', Validators.required],
  });

  protected openAsk(): void {
    this.askForm.reset({ kind: 'advance', amount: '', reason: '' });
    this.error.set(null);
    this.asking.set(true);
  }

  protected async ask(): Promise<void> {
    const value = this.askForm.getRawValue();
    const amount = parseEuros(value.amount);
    if (this.askForm.invalid || amount === null || amount <= 0) {
      this.error.set('Indique le montant et explique ta demande.');
      return;
    }
    await this.run(this.api.ask({ ...value, amount }), () => this.asking.set(false));
  }

  protected cancel(request: MoneyRequest): Promise<void> {
    return this.run(this.api.cancel(request.id));
  }

  // A parent's answer

  protected readonly answering = signal<{ request: MoneyRequest; approve: boolean } | null>(null);
  protected readonly answerForm = this.fb.group({ answer: [''], accountId: [0] });

  protected openAnswer(request: MoneyRequest, approve: boolean): void {
    this.answerForm.reset({ answer: '', accountId: this.accounts.value()?.[0]?.id ?? 0 });
    this.error.set(null);
    this.answering.set({ request, approve });
  }

  protected async answer(): Promise<void> {
    const answering = this.answering();
    if (!answering) {
      return;
    }
    const { answer, accountId } = this.answerForm.getRawValue();
    const request = answering.approve
      ? this.api.approve(answering.request.id, answer || null, accountId || null)
      : this.api.reject(answering.request.id, answer || null);
    await this.run(request, () => {
      this.answering.set(null);
      void this.auth.refreshUser();
    });
  }

  private async run(request: Observable<unknown>, done?: () => void): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    try {
      await firstValueFrom(request);
      done?.();
      this.requests.reload();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.busy.set(false);
    }
  }
}
