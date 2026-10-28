import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { Icon } from '../../shared/icon';

/** A parent creates the family; they add the other parent and the children afterwards. */
@Component({
  selector: 'app-register-page',
  imports: [ReactiveFormsModule, RouterLink, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './register.page.html',
  styleUrl: './auth-layout.scss',
})
export class RegisterPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly form = inject(NonNullableFormBuilder).group({
    familyName: ['', Validators.required],
    displayName: ['', Validators.required],
    username: ['', [Validators.required, Validators.pattern(/^[a-zA-Z0-9._-]{3,40}$/)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });
  protected readonly pending = signal(false);
  protected readonly error = signal<string | null>(null);

  protected async submit(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set(
        'Remplissez tous les champs : identifiant de 3 caractères ou plus, mot de passe de 8 caractères ou plus.',
      );
      return;
    }

    this.pending.set(true);
    this.error.set(null);
    try {
      const value = this.form.getRawValue();
      await firstValueFrom(
        this.auth.register({ ...value, username: value.username.trim().toLowerCase() }),
      );
      await this.router.navigate(['/famille'], { queryParams: { bienvenue: 1 } });
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
