# FacilityDesk

Multi-tenant Facility Management SaaS: FM companies sign up as tenants, register client sites, and track every complaint as a work order with an SLA.

> Status: **Phase 5 — SLA engine, scheduler, assets & QR** complete. See `.md` for the full build plan.

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

**Livewire.** Livewire's default `/livewire/update` route has no domain and no tenant middleware, so component requests would run with no tenant in context. `AppServiceProvider` registers a tenant-subdomain update route (with `tenant`, `auth`, `verified`…) before Livewire boots, which stops Livewire from adding its default; on the central domain `/livewire/update` is a 404. Asset auto-injection is off and `@livewireScripts` lives only in the tenant layout, so central pages never try to build that tenant-only URL.

**Queued work.** Workers serve every tenant, so every queued job and listener carries a `tenant_id` and runs through the `App\Jobs\Middleware\SetTenantContext` job middleware, which restores `TenantContext` (and Spatie's team id) and deletes the job if the tenant is gone. Queue connections use `after_commit`, and events implement `ShouldDispatchAfterCommit`, so nothing runs against a rolled-back transaction.

**Password reset tokens** are keyed by `(tenant_id, email)` (`App\Auth\TenantDatabaseTokenRepository`). Keyed by email alone, a reset token issued on tenant B would also reset the same address on tenant A.

## Roles and permissions

spatie/laravel-permission with **teams** (team key = `tenant_id`): roles are per tenant, permissions are global. `TenantContext::set()` keeps Spatie's team id in sync, so checks follow the tenant in requests, jobs and commands.

| Role | Permissions |
|---|---|
| Tenant Admin | everything |
| Supervisor | view masters and users; see, raise, assign, work on, close, cancel all work orders; internal comments |
| Technician | work on (start / hold / resolve) jobs assigned to them; internal comments |
| Client User | raise work orders for their client's sites; close or reopen resolved ones |

The mapping lives in `App\Enums\UserRole::permissions()`. After changing it, run `php artisan tenancy:provision`: it is idempotent and also backfills missing SLA policies and default categories without overwriting tenant customisations.

## Work orders

| From | To |
|---|---|
| open | assigned, cancelled |
| assigned | in_progress, assigned (reassign), cancelled |
| in_progress | on_hold, resolved |
| on_hold | in_progress |
| resolved | closed (confirm), assigned (reopen) |

- **One path for every change.** `App\Actions\WorkOrders\ChangeStatus` locks the row (`SELECT … FOR UPDATE`), checks `WorkOrderPolicy::transition()` and `WorkOrderStatus::canTransitionTo()`, applies side effects, writes the history row in the same transaction and fires `WorkOrderStatusChanged` after commit. `AssignWorkOrder` and `CloseWorkOrder` go through it.
- **Side effects.** First `in_progress` sets `responded_at`; `resolved` needs a note and sets `resolved_at`; `closed` sets `closed_at`; cancel and reopen need a note. Time spent `on_hold` is added to `hold_minutes` and pushes `resolution_due_at` out by the same amount.
- **SLA.** `App\Services\SlaCalculator` is pure PHP (no DB, caller passes "now") and fully unit-tested. Due dates come from the tenant's `sla_policies` row for the priority; calendar minutes, 24x7.
- **Numbers.** `WO-2026-00123`, gap-free per tenant per year (year in the tenant's timezone). `WorkOrderNumberGenerator` increments one counter row per (tenant, year) under a row lock instead of `MAX(number) + 1`, which races; `UNIQUE (tenant_id, number)` is the backstop.
- **Visibility.** `WorkOrder::visibleTo($user)` (lists) mirrors `WorkOrderPolicy::view()` (single records): supervisors and admins see everything in the tenant, technicians their assigned jobs, client users their client's work orders. Internal comments are filtered out in the query for client users, not just hidden in the view.
- **Client users** cannot choose a priority; their work orders take the category default.

## Notifications, queues and uploads

| Trigger | Listener | Queue | Sends |
|---|---|---|---|
| `WorkOrderCreated` | `NotifySupervisorsOfNewWorkOrder` | default | `WorkOrderRaised` to the site's supervisor and covering supervisors (all supervisors if none) |
| `WorkOrderAssigned` | `SendAssignmentNotification` | notifications | `WorkOrderAssigned` to the technician |
| `WorkOrderStatusChanged` → resolved | `NotifyRaiserOfResolution` | default | `WorkOrderResolved` to the raiser (not if they resolved it) |
| photo uploaded | `ProcessUploadedPhoto` job | media | — rotates upright, caps at 1600px, strips EXIF, 320px thumbnail |

- Notifications go out by **mail and database**; database rows carry `tenant_id` (`TenantDatabaseChannel`) and store a relative `path`, so the header bell (`App\Livewire\NotificationBell`, polls every 30s) can only ever redirect on the current subdomain.
- Every job/listener declares `$tries`, `$backoff` and `failed()`. Horizon runs two supervisors: `app` (notifications, default, media) and `reports` (long exports, Phase 7). Only Super Admins can open Horizon outside local.
- **Uploads**: JPG, PNG, WebP, PDF only, 5 MB each, up to 5 when raising a work order; technicians add before/after photos to their jobs. Files land on a private disk (`ATTACHMENTS_DISK`: `local` = storage/app/private, `s3` = MinIO/S3) under random names, and are served by `GET /attachments/{id}` which is **signed** (links expire), requires login and checks the policy, so a leaked link is useless to anyone who cannot see the work order. On S3 it redirects to a 5-minute presigned URL.

## SLA engine and scheduler

| Schedule | Command | Does |
|---|---|---|
| every 5 min | `sla:check-breaches` | flags work orders that just missed a target; fires `SlaBreached` → `SlaBreachAlert` (mail + bell) to the site supervisor and tenant admins |
| hourly | `digest:daily` | queues `SendDailyDigest` for every tenant where it is 08:00 **locally** (so half-hour zones work); supervisors get open / unassigned / breached / due today / resolved yesterday |

- **Idempotent alerts.** A breach flag only goes `false → true`, under a row lock, and the alert fires only on that flip; running the checker twice (or two overlapping runs) sends one alert. A work order can alert at most twice: response, then later resolution. `breach_notified_at` records when.
- **Cross-tenant by design.** The checker uses one indexed query with the tenant scope explicitly removed (`withoutGlobalScope('tenant')`), skips suspended tenants, and leaves per-tenant work to queued listeners that restore context with `SetTenantContext`.
- **On hold pauses the clock.** An on-hold work order is never newly flagged; when work resumes the hold time is added to `resolution_due_at` and it is judged again. Late responses and late resolutions are also flagged at the moment they happen (`ChangeStatus`), not only while waiting.
- The digest is once per tenant per local day (cache key), so a retried schedule never mails twice. `php artisan digest:daily --tenant=acme --force` sends one now.
- Run locally with `php artisan schedule:work`; in production a cron entry runs `php artisan schedule:run` every minute.

## Assets and QR codes

- Assets belong to a site and location, with make/model/serial, install and warranty dates and a status (working, under repair, retired). Codes are unique per tenant.
- Each asset gets a random ULID `qr_token`. Its QR code (SVG, `bacon/bacon-qr-code`) encodes `https://{tenant}.facilitydesk.test/a/{qr_token}`; a printable label can be downloaded from the asset page.
- Scanning (login required) shows the asset and its open work orders, and **Report a problem** opens the work order form with site, location, category and asset prefilled and `source = qr`. The token is resolved through the tenant scope, so another tenant's label is a 404; client users can scan only their own client's assets.

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
php artisan queue:work --queue=notifications,default,media,reports   # in a second terminal
php artisan schedule:work                                            # SLA checks and digests
```

Without Redis, set `QUEUE_CONNECTION=database` (Sail uses Redis + `sail artisan horizon`). `composer dev` starts the server, a queue listener, logs and Vite together.

Browsers resolve `*.localhost` to 127.0.0.1, so <http://acme.localhost:8000> works without editing `/etc/hosts`. With `MAIL_MAILER=log`, verification, invitation and reset emails (with their links) are written to `storage/logs/laravel.log`.

## Quality gates

```bash
vendor/bin/pest                  # tests (SQLite in memory locally, MySQL in CI)
vendor/bin/pint --test           # code style
vendor/bin/phpstan analyse       # static analysis (Larastan level 6)
```

CI (`.github/workflows/ci.yml`) runs all three against MySQL 8.4 + Redis, with `--coverage --min=70`.
