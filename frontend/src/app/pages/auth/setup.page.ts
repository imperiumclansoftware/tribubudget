import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { Icon } from '../../shared/icon';

/**
 * Installation, at the first visit: the code shown on the server, then the family and its first
 * parent. The other members are added afterwards by a parent.
 */
@Component({
  selector: 'app-setup-page',
  imports: [ReactiveFormsModule, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './setup.page.html',
  styleUrl: './auth-layout.scss',
})
export class SetupPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly fb = inject(NonNullableFormBuilder);

  protected readonly step = signal<'code' | 'family'>('code');
  protected readonly pending = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly codeForm = this.fb.group({ code: ['', Validators.required] });
  protected readonly form = this.fb.group({
    familyName: ['', Validators.required],
    displayName: ['', Validators.required],
    username: ['', [Validators.required, Validators.pattern(/^[a-zA-Z0-9._-]{3,40}$/)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });

  protected async verify(): Promise<void> {
    if (this.codeForm.invalid) {
      this.error.set('Saisissez le code d’installation.');
      return;
    }
    await this.run(async () => {
      await firstValueFrom(this.auth.verifySetupCode(this.codeForm.getRawValue().code));
      this.step.set('family');
    });
  }

  protected async install(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set(
        'Remplissez tous les champs : identifiant de 3 caractères ou plus, mot de passe de 8 caractères ou plus.',
      );
      return;
    }
    await this.run(async () => {
      const value = this.form.getRawValue();
      await firstValueFrom(
        this.auth.install({
          ...value,
          code: this.codeForm.getRawValue().code,
          username: value.username.trim().toLowerCase(),
        }),
      );
      await this.router.navigate(['/famille'], { queryParams: { bienvenue: 1 } });
    });
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
