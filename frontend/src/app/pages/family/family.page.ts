import { ChangeDetectionStrategy, Component, computed, inject, input, signal } from '@angular/core';
import { rxResource, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { FamilyRole, Member } from '../../core/api/models';
import { AuthService } from '../../core/auth/auth.service';
import { BudgetApi } from '../../core/budget-api';
import { FREQUENCY_LABELS } from '../../core/labels';
import { EurosPipe } from '../../shared/euros.pipe';
import { Icon } from '../../shared/icon';
import { Modal } from '../../shared/modal';

/** Members of the family: the parents add the others and reset forgotten codes. */
@Component({
  selector: 'app-family-page',
  imports: [ReactiveFormsModule, RouterLink, Modal, Icon, EurosPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './family.page.html',
  styleUrl: './family.page.scss',
})
export class FamilyPage {
  private readonly api = inject(BudgetApi);
  private readonly fb = inject(NonNullableFormBuilder);
  protected readonly auth = inject(AuthService);

  /** ?bienvenue=1 right after creating the family */
  readonly bienvenue = input<string>();

  protected readonly members = rxResource({ stream: () => this.api.members() });
  protected readonly children = rxResource({ stream: () => this.api.children() });
  protected readonly parents = computed(() =>
    (this.members.value() ?? []).filter((m) => m.role === 'parent'),
  );
  protected readonly frequencies = FREQUENCY_LABELS;
  protected readonly error = signal<string | null>(null);
  protected readonly pending = signal(false);

  // Adding a member

  protected readonly adding = signal(false);
  protected readonly addForm = this.fb.group({
    role: ['child' as FamilyRole],
    displayName: ['', Validators.required],
    username: ['', [Validators.required, Validators.pattern(/^[a-zA-Z0-9._-]{3,40}$/)]],
    password: ['', Validators.required],
  });
  protected readonly addRole = toSignal(this.addForm.controls.role.valueChanges, {
    initialValue: this.addForm.controls.role.value,
  });

  protected openAdd(): void {
    this.addForm.reset({ role: 'child', displayName: '', username: '', password: '' });
    this.error.set(null);
    this.adding.set(true);
  }

  protected async add(): Promise<void> {
    if (this.addForm.invalid) {
      this.error.set('Remplissez le prénom, un identifiant (3 caractères ou plus) et le code.');
      return;
    }
    const value = this.addForm.getRawValue();
    await this.run(async () => {
      await firstValueFrom(
        this.api.addMember({ ...value, username: value.username.trim().toLowerCase() }),
      );
      this.adding.set(false);
      this.reload();
    });
  }

  // Editing a member

  protected readonly editing = signal<Member | null>(null);
  protected readonly editForm = this.fb.group({
    displayName: ['', Validators.required],
    password: [''],
  });

  protected openEdit(member: Member): void {
    this.editForm.reset({ displayName: member.displayName, password: '' });
    this.error.set(null);
    this.editing.set(member);
  }

  protected async saveEdit(): Promise<void> {
    const member = this.editing();
    if (!member || this.editForm.invalid) {
      this.error.set('Indiquez le prénom.');
      return;
    }
    const { displayName, password } = this.editForm.getRawValue();
    await this.run(async () => {
      if (displayName !== member.displayName) {
        await firstValueFrom(this.api.renameMember(member.id, displayName));
      }
      if (password) {
        await firstValueFrom(this.api.resetPassword(member.id, password));
      }
      this.editing.set(null);
      this.reload();
    });
  }

  protected async remove(member: Member): Promise<void> {
    const warning =
      member.role === 'child'
        ? `Supprimer ${member.displayName} ? Sa tirelire et ses demandes seront effacées.`
        : `Retirer ${member.displayName} de la famille ?`;
    if (confirm(warning)) {
      await this.run(async () => {
        await firstValueFrom(this.api.deleteMember(member.id));
        this.editing.set(null);
        this.reload();
      });
    }
  }

  private reload(): void {
    this.members.reload();
    this.children.reload();
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
