import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  linkedSignal,
  signal,
} from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Observable, firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { Projects, SavingsAccount, SavingsProject } from '../../core/api/models';
import { BudgetApi } from '../../core/budget-api';
import { centsToInput, parseEuros } from '../../core/money';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';

/**
 * Projects saved up for on the savings accounts. The money above an account's minimum goes to its
 * projects in the order of their priority: each one is filled up before the next gets anything.
 */
@Component({
  selector: 'app-projects-page',
  imports: [ReactiveFormsModule, RouterLink, Modal, Icon, EurosPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './projects.page.html',
  styleUrl: './projects.page.scss',
})
export class ProjectsPage {
  private readonly api = inject(BudgetApi);
  private readonly fb = inject(NonNullableFormBuilder);

  private readonly resource = rxResource({ stream: () => this.api.projects() });
  /** Kept on screen while reloading after a change */
  protected readonly data = linkedSignal<Projects | undefined, Projects | undefined>({
    source: () => this.resource.value(),
    computation: (value, previous) => value ?? previous?.value,
  });
  protected readonly categories = rxResource({ stream: () => this.api.categories() });
  protected readonly expenseCategories = computed(() =>
    (this.categories.value() ?? []).filter((c) => c.kind === 'expense'),
  );
  protected readonly loadError = computed(() =>
    this.resource.error() ? apiErrorMessage(this.resource.error()) : null,
  );
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);

  // Add / edit

  protected readonly editing = signal<{
    project: SavingsProject | null;
    account: SavingsAccount;
  } | null>(null);
  protected readonly form = this.fb.group({
    accountId: [0, Validators.min(1)],
    name: ['', Validators.required],
    target: ['', Validators.required],
  });

  protected openNew(account: SavingsAccount): void {
    this.form.reset({ accountId: account.id, name: '', target: '' });
    this.error.set(null);
    this.editing.set({ project: null, account });
  }

  protected openEdit(project: SavingsProject, account: SavingsAccount): void {
    this.form.reset({
      accountId: account.id,
      name: project.name,
      target: centsToInput(project.target),
    });
    this.error.set(null);
    this.editing.set({ project, account });
  }

  protected async save(): Promise<void> {
    const value = this.form.getRawValue();
    const target = parseEuros(value.target);
    if (this.form.invalid || target === null || target <= 0) {
      this.error.set('Donnez un nom et un montant positif au projet.');
      return;
    }
    const project = this.editing()?.project ?? null;
    await this.run(this.api.saveProject(project?.id ?? null, { ...value, target }), () =>
      this.editing.set(null),
    );
  }

  protected async remove(project: SavingsProject): Promise<void> {
    if (confirm(`Supprimer le projet « ${project.name} » ?`)) {
      await this.run(this.api.deleteProject(project.id), () => this.editing.set(null));
    }
  }

  /** Swaps the project with its neighbour: -1 to raise its priority, +1 to lower it */
  protected move(account: SavingsAccount, index: number, delta: -1 | 1): Promise<void> {
    const ids = account.projects.map((p) => p.id);
    [ids[index], ids[index + delta]] = [ids[index + delta], ids[index]];
    return this.run(this.api.prioritizeProjects(account.id, ids));
  }

  // Carrying out

  protected readonly achieving = signal<{
    project: SavingsProject;
    account: SavingsAccount;
  } | null>(null);
  protected readonly achieveForm = this.fb.group({ recordWithdrawal: [true], categoryId: [0] });

  protected openAchieve(project: SavingsProject, account: SavingsAccount): void {
    this.achieveForm.reset({ recordWithdrawal: true, categoryId: 0 });
    this.error.set(null);
    this.achieving.set({ project, account });
  }

  protected async achieve(): Promise<void> {
    const achieving = this.achieving();
    if (!achieving) {
      return;
    }
    const { recordWithdrawal, categoryId } = this.achieveForm.getRawValue();
    await this.run(
      this.api.achieveProject(achieving.project.id, recordWithdrawal, categoryId || null),
      () => this.achieving.set(null),
    );
  }

  protected percent(project: SavingsProject): number {
    return Math.min(100, (project.allocated / project.target) * 100);
  }

  protected close(): void {
    this.editing.set(null);
    this.achieving.set(null);
    this.error.set(null);
  }

  private async run(request: Observable<void>, done?: () => void): Promise<void> {
    this.pending.set(true);
    this.error.set(null);
    try {
      await firstValueFrom(request);
      done?.();
      this.resource.reload();
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
