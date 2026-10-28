import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return (await inject(AuthService).restoreSession()) || router.createUrlTree(['/connexion']);
};

export const guestGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return !(await inject(AuthService).restoreSession()) || router.createUrlTree(['/']);
};

/** The household budget: parents only. Children land on their piggy bank. */
export const parentGuard: CanActivateFn = () => {
  const router = inject(Router);
  return inject(AuthService).isParent() || router.createUrlTree(['/ma-tirelire']);
};

export const childGuard: CanActivateFn = () => {
  const router = inject(Router);
  return inject(AuthService).isChild() || router.createUrlTree(['/']);
};
