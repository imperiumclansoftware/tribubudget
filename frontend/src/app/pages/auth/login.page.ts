import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { apiErrorMessage } from '../../core/api/api-error';
import { AuthService } from '../../core/auth/auth.service';
import { Icon } from '../../shared/icon';

@Component({
  selector: 'app-login-page',
  imports: [ReactiveFormsModule, RouterLink, Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './login.page.html',
  styleUrl: './auth-layout.scss',
})
export class LoginPage {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly form = inject(NonNullableFormBuilder).group({
    username: ['', Validators.required],
    password: ['', Validators.required],
  });
  protected readonly showPassword = signal(false);
  protected readonly pending = signal(false);
  protected readonly error = signal<string | null>(null);

  protected async submit(): Promise<void> {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('Indiquez votre identifiant et votre mot de passe (ou code).');
      return;
    }

    this.pending.set(true);
    this.error.set(null);
    try {
      const { username, password } = this.form.getRawValue();
      await firstValueFrom(this.auth.login(username.trim().toLowerCase(), password));
      await this.router.navigate(['/']);
    } catch (e) {
      this.error.set(apiErrorMessage(e));
    } finally {
      this.pending.set(false);
    }
  }
}
