import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { Icon } from '../../shared/icon';

/** The signed-in member changes their own password (or PIN), and signs out. */
@Component({
  selector: 'app-account-page',
  imports: [ReactiveFormsModule, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './account.page.html',
})
export class AccountPage {
  protected readonly auth = inject(AuthService);

  protected readonly form = inject(NonNullableFormBuilder).group({
    currentPassword: ['', Validators.required],
    newPassword: ['', Validators.required],
  });
  protected readonly pending = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly done = signal(false);

  protected async submit(): Promise<void> {
    if (this.form.invalid) {
      this.error.set(
        this.auth.isChild() ? 'Remplis les deux champs.' : 'Remplissez les deux champs.',
      );
      return;
    }
    this.pending.set(true);
    this.error.set(null);
    this.done.set(false);
    try {
      const { currentPassword, newPassword } = this.form.getRawValue();
      await firstValueFrom(this.auth.changePassword(currentPassword, newPassword));
      this.form.reset();
      this.done.set(true);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
