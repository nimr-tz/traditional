# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

TMSC (Traditional Medicine Scientific Conference) is a Laravel 12 + Inertia/React registration, abstracts,
payments, and check-in system for NIMR (National Institute for Medical Research). There are two apps in this
repo:

- **Root**: the Laravel + Inertia/React web app (registration portal, admin panel, abstract review, payments).
- **`checkin-app/`**: a separate Expo/React Native app used by staff at the venue to scan badges and record
  attendance against the same backend's `/api/checkin/*` endpoints. It has its own `package.json`, `AGENTS.md`,
  and its own instance of Claude Code guidance — read `checkin-app/AGENTS.md` before touching that app (Expo
  SDK 57 is new enough that you must check the versioned docs rather than relying on training data).

## Commands

### PHP / Laravel (run from repo root)

- `composer dev` — runs the full local dev stack concurrently: `php artisan serve`, `queue:listen`, `pail`
  (log tailing), and `npm run dev`. This is the normal way to run the app locally.
- `./vendor/bin/phpunit` — run the full test suite (this is what CI runs). Tests are PHPUnit-style classes
  under `tests/Feature` and `tests/Unit`, even though Pest is installed (`tests/Pest.php` binds `TestCase` +
  `RefreshDatabase` for the `Feature` suite, but no test files currently use Pest's functional syntax).
- `./vendor/bin/phpunit --filter test_method_name` or `./vendor/bin/phpunit tests/Feature/PaymentFlowTest.php`
  — run a single test / file.
- `php artisan test` also works and is more readable for local iteration.
- `vendor/bin/pint` — PHP code style fixer (Laravel Pint). CI runs this with no arguments (auto-fixes), so run
  it before committing PHP changes.
- Test DB is SQLite in-memory (`phpunit.xml`); no separate test DB setup is needed.

### JS / frontend (run from repo root, applies to the Inertia/React app, not `checkin-app/`)

- `npm run dev` — Vite dev server (normally invoked via `composer dev`, not standalone).
- `npm run build` / `npm run build:ssr` — production build (SSR entry is `resources/js/ssr.jsx`).
- `npm run lint` — ESLint with `--fix`.
- `npm run format` / `npm run format:check` — Prettier over `resources/`.
- CI runs, in order: `vendor/bin/pint`, `npm run format`, `npm run lint` (see `.github/workflows/lint.yml`).
  Run all three before considering PHP+frontend changes done.

### checkin-app (Expo)

- `cd checkin-app && npm start` (or `npm run android` / `npm run ios` / `npm run web`).
- Read `checkin-app/AGENTS.md` and `checkin-app/product-facts.md` first — Expo SDK 57 / RN 0.86 / React 19.2
  are recent enough that assumptions from older Expo knowledge will be wrong.

## Architecture

### Domain flow

Users self-register on the public site, get assigned a `fee_category` (which determines price, currency, and
the NIMR billing revenue-source mapping), optionally submit an abstract for review, pay via GePG-style control
number, and receive a QR-coded badge/certificate. Staff use the separate check-in app to scan badges at the
venue and record `Attendance`.

Key models (`app/Models/`): `User` (registrant *and* staff/admin — see roles below), `AbstractSubmission` (+
`AbstractReviewHistory` for the audit trail of decisions), `FeeCategory`, `Institution`, `Subtheme`,
`ConferenceSetting`, `Attendance`, `Certificate`, `AdministratorAccessChange` (audit log for role grants).

### Roles and authorization

Single `User` model carries a `role` column (`app/Models/User.php`): `user`, `reviewer`, `staff`, `admin`,
`super_admin`. There is no separate roles/permissions package — authorization is done via:
- The `EnsureUserHasRole` middleware (`app/Http/Middleware/EnsureUserHasRole.php`), aliased as `role:` in
  routes, e.g. `role:reviewer,admin,super_admin`. `super_admin` is **implicitly allowed everywhere** regardless
  of which roles are listed — every other role must be named explicitly on the route.
- Helper methods on `User`: `isSuperAdmin()`, `isAdmin()`, `canReviewAbstracts()`, `canUseCheckinApp()`.
- Route grouping by role lives in `routes/admin.php` (abstract review is reviewer+admin+super_admin;
  registrations/students/settings are admin+super_admin only; granting/revoking admin roles is
  super_admin-only) and `routes/api.php` (check-in app requires a Sanctum token scoped to staff/admin roles,
  issued via `POST /api/checkin/login`).

### Billing (GePG / NIMR Billing System)

Payments go through an external "NIMR Billing System" (GePG-style control numbers), documented in `api.md` at
the repo root — **read `api.md` before touching anything billing-related**, it's the spec for the external
API's request/response shapes. Integration code:
- `app/Services/Billing/GepgService.php` — submits bill/control-number requests. Real NIMR credentials aren't
  provisioned yet, so `config('billing.sandbox')` (default `true`, see `config/billing.php` /
  `BILLING_SANDBOX` env) makes this mimic the async control-number flow locally via
  `App\Jobs\AssignSandboxControlNumber` instead of calling the real API.
- `app/Http/Controllers/Api/BillingCallbackController.php` — receives the real system's async callbacks
  (`control-number-callback`, `payment-callback`) plus a dev-only `sandbox/simulate/{user}` endpoint.
- `config/billing.php` maps each `fee_categories.key` to a NIMR `RevenueSourceItem` ID via `REV_*` env vars —
  these are `null` until NIMR finance provisions them, and the service fails loudly (not silently) if a
  category without a mapping is used outside sandbox mode.
- Note in the code: the billing payload shapes follow `api.md`'s spec, *not* AJSC's (a related/prior system)
  implementation, which has drifted from that spec over time — don't copy patterns from AJSC without checking
  against `api.md`.

### Frontend (Inertia + React)

- Pages live in `resources/js/pages/`, mirroring route structure (`admin/`, `abstracts/`, `auth/`, `settings/`,
  plus top-level `dashboard.tsx`, `payment.tsx`, `welcome.tsx`). Inertia resolves a route to a page component
  by name (see `App\Http\Controllers\*` `Inertia::render(...)` calls and `app/Http/Middleware/HandleInertiaRequests.php`
  for shared props).
- UI components use shadcn/ui conventions (`components.json`: Radix primitives under `resources/js/components/ui`,
  `@/lib/utils` for `cn()`, Tailwind v4 via `@tailwindcss/vite`).
- Path alias `@/*` → `resources/js/*` (and `ziggy-js` → the published Ziggy client) — see `tsconfig.json`.
- `vite.config.js` uses `base: './'` (relative asset URLs) deliberately, so the build works both under
  `php artisan serve` and when served from a subpath under XAMPP htdocs — don't change this to an absolute
  base without checking both deployment modes.
- Route helpers use Ziggy (`tightenco/ziggy`) for generating Laravel route URLs from TS/JS.

### Check-in app integration

`checkin-app/` (Expo/React Native) authenticates staff via `POST /api/checkin/login` (Sanctum token, throttled
under the `checkin-login` limiter), then calls `register`, `scan`, `lookup`, `users/{user}/check-in`, and
`recent` under `auth:sanctum` + `checkin/` prefix (`routes/api.php`,
`app/Http/Controllers/Api/CheckinController.php`). The Expo app's own API client lives in
`checkin-app/src/api/client.ts`.
