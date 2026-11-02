# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

TribuBudget is a self-hosted family budget: one instance = one family. Parents manage the household
accounts, operations, budgets, savings projects and vehicles; teenagers keep their own accounts; children
have a piggy bank (pocket money) and send requests to their parents. The product is French: UI text, error
messages, docs (README, CHANGELOG, `docker/DOCKERHUB.md`) and commit messages are written in French; code,
identifiers and code comments are in English. README.md describes every business rule in detail (in
French): read the relevant part before changing a feature.

## Commands

Backend (`backend/`, Symfony 8.1, PHP 8.4, PostgreSQL 16):

```bash
docker compose up -d                       # dev PostgreSQL on port 5434 (compose project "tribubudget")
composer install
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate -n
php -S 127.0.0.1:8002 -t public            # API on http://127.0.0.1:8002 (or: symfony serve --port=8002)
php bin/console app:setup:code             # code asked by the installation page (/installation)
php bin/console app:schedules:run          # records due allowances / recurring operations (daily cron)

php bin/console doctrine:database:create --env=test --if-not-exists   # once, before the first test run
php bin/phpunit                            # all tests
php bin/phpunit tests/Api/TransferTest.php
php bin/phpunit --filter TransferTest
vendor/bin/phpstan analyse                 # level 6; needs the dev container (bin/console cache:warmup --env=dev)
vendor/bin/php-cs-fixer fix                # CI runs it with --dry-run --diff
```

Here Docker runs on the host `docker`: `backend/.env.local` and `.env.test.local` point `DATABASE_URL` at
`docker:5434`. Schema changes go through migrations (`make:migration`), never `doctrine:schema:update`.

Frontend (`frontend/`, Angular 22):

```bash
npm start                                  # http://localhost:4400, /api proxied to :8002 (proxy.conf.mjs, API_URL overrides)
npm run start:code-server                  # same, served under /absproxy/4400/ behind code-server
npx ng build
npx ng test --watch=false                  # Vitest through @angular/build:unit-test
npx ng test --watch=false --include src/app/core/money.spec.ts
npm run format:check                       # Prettier (printWidth 100, single quotes); npm run format to fix
```

Docker image (the whole app in one image):

```bash
docker build --pull -t iemperor/tribubudget:X.Y.Z --build-arg APP_VERSION=X.Y.Z .
```

CI (`.github/workflows/ci.yml`) runs PHPUnit, PHPStan and php-cs-fixer for the backend, and
`format:check`, `ng test` and `ng build` for the frontend.

## Architecture

### One image, everything inside

Since 0.4.0 the image is `php:8.4-fpm-alpine` with Alpine's nginx (FrankenPHP was dropped: its embedded Go
libraries carried unfixable CVEs). nginx (`docker/nginx.conf`) serves the Angular build from `/app/public`,
falls back to `index.html` for app routes, and sends `/api/` to PHP-FPM on `127.0.0.1:9000` with
`public/index.php`; any `.php` URL or dotfile returns 404. The frontend therefore calls the API with
**relative** URLs (`api/accounts`, no leading slash) so it works under any base href.

`docker/entrypoint.sh` (under `tini`, as root) creates the secrets in `/data/secrets` unless given as env
vars, initialises and starts the embedded PostgreSQL in `/data/postgres` (listening on 127.0.0.1 only)
unless `DATABASE_URL` is given, writes them to `/app/.env.local` (so `docker exec … console` sees them),
runs migrations, JWT keys and `app:setup:code`, then starts `docker/scheduler.sh` (`app:schedules:run`
every `SCHEDULER_INTERVAL`, default 1 h) and `docker/web.sh` (PHP-FPM + nginx, masters as root, workers
as `www-data`). On SIGTERM it stops everything and shuts PostgreSQL down cleanly. `config/jwt` and
`var/setup` are symlinks into `/data`. The Dockerfile refuses a base image with PHP < 8.4.27 (rebuild with
`--pull`).

### Backend

- **Request flow**: controllers in `src/Controller` bind input with `#[MapRequestPayload]` into readonly
  DTOs (`src/Dto`, validated with `#[Assert]`), do the money logic through services in `src/Money`
  (`AllowancePayer`, `TeenMoney`, `SavingsAllocator`, `MonthForecast`, `Garage`…), and build the JSON by
  hand with `App\Api\ApiPresenter`. No serializer groups: `frontend/src/app/core/api/models.ts` mirrors
  what `ApiPresenter` returns.
- **Amounts are integer cents** everywhere (entities, API, frontend `core/money.ts`); `Dto\Amount::MAX` caps
  them at one million euros.
- **Errors**: controllers throw HTTP exceptions with a French, user-facing message;
  `EventListener\ApiErrorListener` turns them into `{status, detail}` problem+json in every environment
  (otherwise prod would hide the message), except Symfony's own technical messages (unknown route, entity
  not found, missing role). It runs at priority -64, after the firewall has turned the
  `#[IsGranted(message: …)]` denials into HTTP exceptions; `tests/Api/ApiErrorTest.php` checks it with a
  non-debug kernel. Validation errors keep Symfony's `violations` format. The frontend's
  `apiErrorMessage()` reads both.
- **Security** (`config/packages/security.yaml`): stateless JWT (LexikJWT), `json_login` on `/api/login`
  with `login_throttling` (5 tries / 15 min, children log in with a short PIN). Only `/api/login`,
  `/api/health` and `/api/setup(/verify)` are public. Roles: `ROLE_PARENT` and `ROLE_TEEN` both inherit
  `ROLE_ACCOUNT_HOLDER`; `ROLE_CHILD`. Household controllers carry `#[IsGranted('ROLE_PARENT', message: …)]`; finer rules live
  in the voters of `src/Security` (a child/teen sees only their own data, a teen edits only what they
  recorded, requests are answered once).
- **Family isolation**: every family-scoped entity implements `FamilyOwned`; controllers call
  `FamilyScope::sameFamily()` so another family's data is a 404. Ids referenced in payloads are resolved
  with `App\Api\FamilyLookup` (`account()` limits a teen to their own accounts, `householdAccount()`
  excludes teen accounts).
- **Household vs teen accounts**: a teen's accounts have `Account::owner` set and are kept **out** of the
  household budget (balance, month totals, budgets, projects): household queries filter on
  `owner IS NULL` (`AccountRepository`, `OperationRepository`). Keep doing so in new household queries.
- **Transfers** are two linked operations (`Operation::$transfer`), edited or deleted as a whole, and are
  excluded from income/expense totals, budgets and history.
- **Schedules**: allowances and recurring operations are caught up (including missed dates) by
  `Money\Schedules::catchUp()` whenever a member opens the app (called from the dashboard, operations,
  `me`, child and teen controllers) and by `app:schedules:run`. It takes a `symfony/lock` lock per family;
  the image sets `LOCK_DSN` to PostgreSQL advisory locks so the web and scheduler processes share it
  (dev uses `flock`). "Today" comes from `Money\Today` (the `APP_TIMEZONE` date, default Europe/Paris).
- **Installation**: `SetupController` creates the single family and its first parent once, after checking
  the one-time code of `Setup\SetupCode` (stored in `var/setup`, rate-limited by `setup_code` in
  `rate_limiter.yaml`); `Money\FamilySetup` adds the default account and categories.
- **Vehicle catalog**: `Api\VehicleCatalog` queries the ADEME open data from the server, caches it a day
  and degrades to an empty catalog when unreachable (manual entry).
- `backend/AGENTS.md` is mostly the generic Symfony agent guide; what applies here: attributes and
  autowiring rather than YAML, `composer require` + Flex recipes rather than hand-wiring bundles,
  `symfony/lock` for mutual exclusion, constructor promotion and `readonly` DTOs, migrations only,
  `@Symfony` php-cs-fixer style.

### Backend tests

Mostly functional (`tests/Api`), extending `tests/ApiTestCase.php`: the schema is recreated once per
PHPUnit run, then every table is truncated before **each** test. Helpers: `registerFamily()` (returns a
parent token), `addChild()`, `addTeen()`, `login()`, `api()` (JSON in, decoded JSON out). `tests/Money`
holds unit tests (`VehicleCatalogTest` uses `MockHttpClient`). PHPUnit fails on deprecations, notices and
warnings.

### Frontend

- Standalone components with signals; pages are lazy-loaded from `app.routes.ts` under the `Shell`
  layout, with French paths (`tableau-de-bord`, `famille/ado/:teenId`…) and role guards
  (`parentGuard`, `teenGuard`, `childGuard`, `setupGuard` for `/installation`). Route params arrive as
  component inputs (`withComponentInputBinding`). Locale `fr-FR`.
- HTTP: `core/budget-api.ts` and `core/auth/auth.service.ts` wrap `HttpClient`; `authInterceptor` adds the
  JWT (kept in `localStorage`) and logs out on a 401.
- Styling: mobile first, light/dark from the system. Tokens and layout come from the shared `themecss/`
  theme (outside this repo), adapted in `src/styles/` (`_tokens.scss`, `_base.scss`, `_layout.scss`, `_components.scss`);
  no touch target under 48 px (`--touch-target`); breakpoints 600/768/1024/1280/1440 px (sidebar
  navigation from 1024 px). Modals are bottom sheets on phones (`shared/modal.ts`).

## Releases

1. Add the version's section to `CHANGELOG.md` (`## X.Y.Z — date`, in French) and its line in the tag
   table of `docker/DOCKERHUB.md`; commit as "TribuBudget X.Y.Z : <résumé>".
2. Build and push `iemperor/tribubudget:X.Y.Z` (command above) and tag it `latest`.
3. `git tag vX.Y.Z && git push origin main vX.Y.Z`: the *Release* workflow runs CI, then publishes the
   GitHub release with that version's CHANGELOG section (it fails if the section is missing).

Database changes need no manual step on update: the container runs migrations at start.
