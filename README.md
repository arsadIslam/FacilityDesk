# FacilityDesk

Multi-tenant Facility Management SaaS: FM companies sign up as tenants, register client sites, and track every complaint as a work order with an SLA.

> Status: **Phase 2 — Auth, roles, masters** complete. See `.md` for the full build plan.

## Stack

Laravel 12 · PHP 8.3 (Sail/CI; code stays 8.2-compatible) · MySQL 8 · Redis · Livewire 3 + Tailwind · Pest · Pint · Larastan (level 6)

## Multi-tenancy

Single database, `tenant_id` column on every tenant-owned table.

| Piece | Responsibility |
|---|---|
| `App\Services\TenantContext` | Scoped singleton holding the current tenant (`id()`, `tenant()`, `set()`, `clear()`, `run()`). Scoped so it is flushed between requests and queued jobs. |
| `App\Http\Middleware\IdentifyTenant` | Web: resolves the tenant from the `{tenant}` subdomain. API: from the authenticated user. Unknown tenant → 404. |
| `App\Http\Middleware\EnsureTenantIsActive` | Suspended tenant → 403. |
| `App\Models\Concerns\BelongsToTenant` | Global scope `where tenant_id = ?` + stamps `tenant_id` on create. |

**Why a shared database instead of database-per-tenant?** One schema to migrate, cheap tenants, and simple cross-tenant platform reporting. Isolation is enforced in one place (the global scope) and proven by tests rather than by infrastructure.

**Users and tenancy.** `User` uses the tenant scope too, so the session/auth lookup on `acme.facilitydesk.test` can only ever find Acme's users. Super Admins have `tenant_id = NULL` and never match a tenant subdomain.

**Middleware order.** `IdentifyTenant` is registered in the middleware *priority list* ahead of authentication and `SubstituteBindings`. Laravel sorts route middleware by priority, so declaring `tenant` first in a route group is not enough: route-model binding would otherwise resolve `/users/{user}` before the tenant scope exists. A regression test covers this.

**Validation.** `exists`/`unique` rules query tables directly and ignore Eloquent scopes, so they go through `App\Support\TenantRules`, which pins them to the current tenant.

**Password reset tokens** are keyed by `(tenant_id, email)` (`App\Auth\TenantDatabaseTokenRepository`). Keyed by email alone, a reset token issued on tenant B would also reset the same address on tenant A.

## Roles and permissions

spatie/laravel-permission with **teams** (team key = `tenant_id`): roles are per tenant, permissions are global. `TenantContext::set()` keeps Spatie's team id in sync, so checks follow the tenant in requests, jobs and commands.

| Role | Permissions |
|---|---|
| Tenant Admin | everything (`users.*`, `clients.*`, `sites.*`, `categories.*`, `sla_policies.*`) |
| Supervisor | view users, clients, sites, categories, SLA policies |
| Technician | — (work-order permissions arrive in Phase 3) |
| Client User | — (work-order permissions arrive in Phase 3) |

The mapping lives in `App\Enums\UserRole::permissions()`. After changing it, run `php artisan tenancy:provision`: it is idempotent and also backfills missing SLA policies and default categories without overwriting tenant customisations.

## Local setup

### With Sail (recommended)

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Add to `/etc/hosts` (one line per tenant you create):

```
127.0.0.1 facilitydesk.test acme.facilitydesk.test
```

Open <http://facilitydesk.test/signup>.

### Without Docker

Set `APP_DOMAIN=localhost` and `APP_URL=http://localhost:8000` in `.env`, then:

```bash
php artisan migrate
php artisan tenancy:provision   # only needed for tenants created before a permissions change
npm run build                   # or npm run dev while editing views
php artisan serve
```

Browsers resolve `*.localhost` to 127.0.0.1, so <http://acme.localhost:8000> works without editing `/etc/hosts`. With `MAIL_MAILER=log`, verification, invitation and reset emails (with their links) are written to `storage/logs/laravel.log`.

## Quality gates

```bash
vendor/bin/pest                  # tests (SQLite in memory locally, MySQL in CI)
vendor/bin/pint --test           # code style
vendor/bin/phpstan analyse       # static analysis (Larastan level 6)
```

CI (`.github/workflows/ci.yml`) runs all three against MySQL 8.4 + Redis, with `--coverage --min=70`.
