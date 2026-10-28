import { HttpErrorResponse } from '@angular/common/http';
import { ApiProblem } from './models';

/** Turns an API error into a message that can be shown to the user. */
export function apiErrorMessage(
  error: unknown,
  fallback = 'Une erreur est survenue, réessayez.',
): string {
  if (!(error instanceof HttpErrorResponse)) {
    return fallback;
  }
  if (error.status === 0) {
    return 'Impossible de joindre le serveur.';
  }

  const problem = error.error as ApiProblem | null;
  if (problem?.violations?.length) {
    return problem.violations.map((v) => v.title).join(' ');
  }
  if (error.status === 401 && problem?.message === 'Invalid credentials.') {
    return 'Identifiant ou code incorrect.';
  }
  if (error.status === 429) {
    return 'Trop de tentatives : patientez quelques minutes avant de réessayer.';
  }

  return problem?.detail ?? problem?.message ?? fallback;
}
