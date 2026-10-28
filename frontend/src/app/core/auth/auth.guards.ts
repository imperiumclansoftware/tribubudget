import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return (await inject(AuthService).restoreSession()) || router.createUrlTree(['/connexion']);
};

/** Before the installation, the sign-in page leads to the installation page. */
export const installedGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return (await inject(AuthService).installed()) || router.createUrlTree(['/installation']);
};

/** The installation page, only before the installation. */
export const setupGuard: CanActivateFn = async () => {
  const router = inject(Router);
  return !(await inject(AuthService).installed()) || router.createUrlTree(['/connexion']);
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
