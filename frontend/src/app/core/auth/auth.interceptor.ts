import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

/** API calls are relative (`api/…`), so that the app works under any base href. */
const isApiCall = (url: string): boolean => url.startsWith('api/');

/**
 * - sends the session cookie to the API only, never to another site;
 * - goes back to the sign-in page when the session has expired (401).
 * No token here: the session cookie is HttpOnly, and Angular's XSRF support sends the CSRF header
 * (app.config.ts).
 */
export const authInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isApiCall(request.url)) {
    return next(request);
  }
  const auth = inject(AuthService);
  // Signing in, out, or checking the session: their failures are handled where they are made
  const sessionCall = /^api\/(login|logout|me)$/.test(request.url);

  return next(request.clone({ withCredentials: true })).pipe(
    catchError((error: unknown) => {
      if (
        error instanceof HttpErrorResponse &&
        error.status === 401 &&
        !sessionCall &&
        auth.user()
      ) {
        auth.sessionEnded();
      }
      return throwError(() => error);
    }),
  );
};
