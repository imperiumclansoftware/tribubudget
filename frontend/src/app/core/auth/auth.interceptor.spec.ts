import {
  HttpClient,
  provideHttpClient,
  withInterceptors,
  withXsrfConfiguration,
} from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Me } from '../api/models';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from './auth.service';

describe('session authentication', () => {
  let http: HttpClient;
  let backend: HttpTestingController;
  let auth: AuthService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(
          withXsrfConfiguration({ cookieName: 'XSRF-TOKEN', headerName: 'X-XSRF-TOKEN' }),
          withInterceptors([authInterceptor]),
        ),
        provideHttpClientTesting(),
      ],
    });
    http = TestBed.inject(HttpClient);
    backend = TestBed.inject(HttpTestingController);
    auth = TestBed.inject(AuthService);
    vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    document.cookie = 'XSRF-TOKEN=jeton-du-cookie';
  });

  afterEach(() => {
    backend.verify();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
  });

  it('sends the credentials to the API only, and never a bearer token', () => {
    http.get('api/accounts').subscribe();
    http.get('https://autre-site.example/data').subscribe();

    const api = backend.expectOne('api/accounts');
    expect(api.request.withCredentials).toBe(true);
    expect(api.request.headers.has('Authorization')).toBe(false);
    expect(backend.expectOne('https://autre-site.example/data').request.withCredentials).toBe(
      false,
    );
    api.flush([]);
  });

  it('copies the CSRF cookie into the header of the requests that change something', () => {
    http.post('api/accounts', {}).subscribe();
    http.get('api/accounts').subscribe();

    expect(backend.expectOne({ method: 'POST' }).request.headers.get('X-XSRF-TOKEN')).toBe(
      'jeton-du-cookie',
    );
    expect(backend.expectOne({ method: 'GET' }).request.headers.has('X-XSRF-TOKEN')).toBe(false);
  });

  it('keeps no token in the browser storage when signing in', async () => {
    const signedIn = firstValueFrom(auth.login('maman', 'Velo-rouge-du-dimanche'));
    backend.expectOne('api/login').flush({ username: 'maman', role: 'parent' } as Me);
    await signedIn;

    expect(auth.isParent()).toBe(true);
    expect(globalThis.localStorage?.length ?? 0).toBe(0);
  });

  it('goes back to the sign-in page when the session has expired', async () => {
    const signedIn = firstValueFrom(auth.login('maman', 'Velo-rouge-du-dimanche'));
    backend.expectOne('api/login').flush({ username: 'maman', role: 'parent' } as Me);
    await signedIn;
    const sessionEnded = vi.spyOn(auth, 'sessionEnded');

    http.get('api/accounts').subscribe({ error: () => undefined });
    backend.expectOne('api/accounts').flush(null, { status: 401, statusText: 'Unauthorized' });

    expect(sessionEnded).toHaveBeenCalledOnce();
    expect(auth.user()).toBeNull();
  });

  it('ends the session on the server when signing out', async () => {
    const signedOut = auth.logout();
    backend.expectOne({ method: 'POST', url: 'api/logout' }).flush(null);
    await signedOut;

    expect(auth.user()).toBeNull();
  });
});
