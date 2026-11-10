import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, firstValueFrom, map, tap } from 'rxjs';
import { Me } from '../api/models';

export interface SetupPayload {
  code: string;
  familyName: string;
  displayName: string;
  username: string;
  password: string;
}

/**
 * The signed-in member. The session lives in an HttpOnly cookie set by the API: the app never sees
 * nor stores a token (docs/adr/0001-session-cookie.md); it only keeps the member in memory.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly _user = signal<Me | null>(null);

  readonly user = this._user.asReadonly();
  readonly isParent = computed(() => this._user()?.role === 'parent');
  readonly isChild = computed(() => this._user()?.role === 'child');
  readonly isTeen = computed(() => this._user()?.role === 'teen');
  /** Where the member lands: the dashboard, their accounts or their piggy bank */
  readonly home = computed(() => {
    const role = this._user()?.role;
    return role === 'parent'
      ? '/tableau-de-bord'
      : role === 'teen'
        ? '/mes-comptes'
        : '/ma-tirelire';
  });
  private _installed = false;

  /** Signs in: the API sets the session cookie and answers with the member. */
  login(username: string, password: string): Observable<Me> {
    return this.http
      .post<Me>('api/login', { username, password })
      .pipe(tap((user) => this._user.set(user)));
  }

  /** Whether the installation has run; known for good once it is true. */
  async installed(): Promise<boolean> {
    if (!this._installed) {
      try {
        this._installed = (
          await firstValueFrom(this.http.get<{ installed: boolean }>('api/setup'))
        ).installed;
      } catch {
        // API unreachable: let the pages show the error
        return true;
      }
    }
    return this._installed;
  }

  /** First step of the installation page: the code shown on the server. */
  verifySetupCode(code: string): Observable<void> {
    return noContent(this.http.post<null>('api/setup/verify', { code }));
  }

  /** Installs TribuBudget: creates the family and its first parent, signed in right away. */
  install(payload: SetupPayload): Observable<{ user: Me }> {
    return this.http.post<{ user: Me }>('api/setup', payload).pipe(
      tap((response) => {
        this._installed = true;
        this._user.set(response.user);
      }),
    );
  }

  changePassword(currentPassword: string, newPassword: string): Observable<void> {
    return noContent(this.http.post<null>('api/me/password', { currentPassword, newPassword }));
  }

  /** Loads the signed-in member; resolves to false when there is no valid session. */
  async restoreSession(): Promise<boolean> {
    if (this._user()) {
      return true;
    }
    try {
      this._user.set(await firstValueFrom(this.http.get<Me>('api/me')));
      return true;
    } catch (e) {
      // 401: no session (or an expired one). A network error keeps the member where they are.
      if (e instanceof HttpErrorResponse && (e.status === 401 || e.status === 403)) {
        this._user.set(null);
      }
      return false;
    }
  }

  /** Reloads the member, e.g. the count of pending requests after answering one. */
  async refreshUser(): Promise<void> {
    if (this._user()) {
      this._user.set(await firstValueFrom(this.http.get<Me>('api/me')));
    }
  }

  /** Ends the session on the server, then goes back to the sign-in page. */
  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.http.post<null>('api/logout', null));
    } catch {
      // Already signed out (expired session) or server unreachable: forget the member anyway
    }
    this.sessionEnded();
  }

  /** The session is over (signed out, expired, or the password changed elsewhere). */
  sessionEnded(): void {
    this._user.set(null);
    void this.router.navigate(['/connexion']);
  }
}

/** For the API calls that answer 204 No Content. */
function noContent(request: Observable<unknown>): Observable<void> {
  return request.pipe(map(() => undefined));
}
