import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, firstValueFrom, tap } from 'rxjs';
import { AuthResponse, Me } from '../api/models';

const TOKEN_KEY = 'tribubudget.token';

export interface SetupPayload {
  code: string;
  familyName: string;
  displayName: string;
  username: string;
  password: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly _token = signal<string | null>(readToken());
  private readonly _user = signal<Me | null>(null);

  readonly token = this._token.asReadonly();
  readonly user = this._user.asReadonly();
  readonly isParent = computed(() => this._user()?.role === 'parent');
  readonly isChild = computed(() => this._user()?.role === 'child');
  private _installed = false;

  login(username: string, password: string): Observable<AuthResponse> {
    return this.http
      .post<AuthResponse>('api/login', { username, password })
      .pipe(tap((response) => this.storeToken(response.token)));
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
    return this.http.post<void>('api/setup/verify', { code });
  }

  /** Installs TribuBudget: creates the family and its first parent, signed in right away. */
  install(payload: SetupPayload): Observable<AuthResponse & { user: Me }> {
    return this.http.post<AuthResponse & { user: Me }>('api/setup', payload).pipe(
      tap((response) => {
        this._installed = true;
        this.storeToken(response.token);
        this._user.set(response.user);
      }),
    );
  }

  changePassword(currentPassword: string, newPassword: string): Observable<void> {
    return this.http.post<void>('api/me/password', { currentPassword, newPassword });
  }

  /** Loads the signed-in member; resolves to false when there is no valid session. */
  async restoreSession(): Promise<boolean> {
    if (!this._token()) {
      return false;
    }
    if (this._user()) {
      return true;
    }
    try {
      this._user.set(await firstValueFrom(this.http.get<Me>('api/me')));
      return true;
    } catch (e) {
      // Only a rejected session logs out: a network error keeps it
      if (e instanceof HttpErrorResponse && (e.status === 401 || e.status === 403)) {
        this.clear();
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

  logout(): void {
    this.clear();
    void this.router.navigate(['/connexion']);
  }

  private storeToken(token: string): void {
    this._token.set(token);
    try {
      localStorage.setItem(TOKEN_KEY, token);
    } catch {
      // Storage unavailable (private mode…): the session lasts until the page is closed
    }
  }

  private clear(): void {
    this._token.set(null);
    this._user.set(null);
    try {
      localStorage.removeItem(TOKEN_KEY);
    } catch {
      // Nothing to clean up
    }
  }
}

function readToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}
