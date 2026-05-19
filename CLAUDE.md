# Time Tracker — Claude Development Guide

This document gives a Claude session full context to continue developing or debugging this project without needing to re-explain history.

---

## Project Overview

A multi-user time tracking web app built for **Luis Felipe Castro** to replace an Excel spreadsheet. Log work tasks throughout the day with project, description, start/finish times. Compile entries into a Replicon-compatible summary and generate client invoices.

**This is a public repository.** Never commit sensitive or confidential information — no real project codes, client names, internal ticket numbers, credentials, or personal data. Use generic placeholders in examples and defaults.

---

## Architecture

| Layer | Technology | Hosting |
|---|---|---|
| Backend API | Laravel 12 + Sanctum (Bearer tokens) + Postgres 16 | Fly.io (`yyz`) |
| Database | Postgres 16 | Supabase (pooler port 6543) |
| Frontend SPA | Nuxt 4 (`ssr:false`) + Vue 3 + Vuetify 3 + Pinia + TypeScript | Fly.io (`yyz`) |
| File storage | Supabase Storage (S3-compatible) | Supabase |
| Email (dev) | Mailpit | Docker |
| Email (prod) | Resend (SMTP) | — |
| PDF generation | Spatie Browsershot (Chromium + Node.js + Puppeteer in backend container) | Fly.io |

---

## Repo Layout

```
backend/                   Laravel 12 API
frontend/                  Nuxt 4 SPA
docker-compose.yml         Dev: postgres + mailpit + laravel + frontend
docker-compose.prod.yml    Prod image smoke-test + Fly.io secrets reference
.github/workflows/         deploy-backend.yml + deploy-frontend.yml
docs/                      replicon-api.md, history.md
```

---

## Dev Setup

```bash
# Start everything (postgres + mailpit + laravel + nuxt dev server)
docker compose up -d

# First time only
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate
```

Backend: port 8020. Frontend: port 3000. Mailpit UI: port 8025.

---

## Backend Structure (`backend/`)

```
app/
  Console/Commands/
    PurgeExpiredRepliconCredentials.php  # replicon:purge-expired (scheduled every minute)
  Enums/InvoiceStatus.php
  Http/Controllers/Api/
    Auth/{Register,Login,Logout,VerifyEmail,ResendVerification,
          ForgotPassword,ResetPassword}Controller.php
    Replicon/{Entries,Credentials,ProjectsCache,RowMap,Sync,Submit}Controller.php
    Contractor/{Entries,Clients,Invoices,Company}Controller.php
    UserCustomizationController.php
    UserProfileController.php
  Http/Resources/               (JSON API shape for each entity; JsonResource::withoutWrapping() set globally)
  Jobs/SyncRepliconProjects.php
  Models/
    User.php
    RepliconTimeEntry.php  RepliconCredential.php
    RepliconProject.php    RepliconTask.php  RepliconRowMap.php
    ContractorTimeEntry.php  Client.php  ClientTask.php
    Invoice.php  CompanySetting.php  UserCustomization.php
    (User.php has avatar_path; fillable includes name, email, password, avatar_path)
    Concerns/{BelongsToUser,HasUuidV7,HasTimeWindow,HasDuration}.php
  Services/
    Replicon/{RepliconClient,RepliconSyncService,RepliconSubmitService}.php
    Invoices/InvoicePdfService.php
database/migrations/            all domain tables
resources/views/invoices/invoice.blade.php
routes/api.php
```

### Key Design Decisions

- **`BelongsToUser` trait:** boots a global scope scoping every query to `auth()->id()`; auto-assigns `user_id` on `creating`. No-ops in Artisan/queue contexts without `auth()`.
- **`HasUuidV7` trait:** uses `symfony/uid` v7 UUIDs (monotonic prefix keeps B-tree indexes hot).
- **Replicon credentials** encrypted at rest via `'encrypted'` cast (AES-256-CBC via `APP_KEY`). `GET /api/replicon/credentials` never returns the raw cookie — only `cookie_set: bool`.
- **`invoice_id` FK** lives on `contractor_time_entries`, not as a JSON array on invoices. `Invoice::timeEntries()` is `hasMany`.
- **`invoiced` boolean column was dropped** from `contractor_time_entries`; invoice status is derived from `invoice_id IS NOT NULL`. Do not re-add the column.
- **`replicon_task_id`** nullable FK on `replicon_time_entries` — set when PROJ mode maps an entry to a specific Replicon task (UUID referencing `replicon_tasks`).
- **`client_task_id`** nullable FK on `contractor_time_entries` — set when the selected task is a known `client_tasks` record.
- **Company settings** are server-side (not localStorage).
- **`User::companySetting()`** uses `->withoutGlobalScopes()` — required because `InvoicePdfService` runs outside an HTTP request context (no `auth()`) and the `BelongsToUser` global scope would otherwise return null.
- **`UserResource`** is used for all user responses (`/me`, login, register, profile endpoints). It includes `avatar_url` as a 60-minute presigned Supabase Storage URL. `JsonResource::withoutWrapping()` is set in `AppServiceProvider` — resources are never wrapped in a `data` key.
- **Avatar storage** follows the same pattern as company logos: stored at `avatars/{userId}/{uuid}.ext` in Supabase Storage (S3 disk), path saved in `users.avatar_path`. Old file deleted on upload or removal.
- **Session expiry**: `users` table has no token expiry — Sanctum tokens are permanent until explicitly deleted (logout). "Keep me logged in" is frontend-only: a `sessionExpiry` timestamp stored in `tt_auth` localStorage, checked on app startup in `persist.client.ts`. If expired, `auth.logout()` is called (which deletes the token server-side) before anything else runs.

### User Customization (server-side prefs)

- `UserCustomization` model: one row per user, `configuration` JSON column. `getConfigAttribute()` merges stored values over hard-coded defaults so all keys are always present — add new prefs to `$defaults` in the model, no migration needed.
- Namespaces: `ui` (theme, use12h, activeVariant), `replicon` (jiraPattern), `contractor` (jiraPattern).
- `persist.client.ts` Nuxt plugin runs on startup after `auth.me()` — calls `useUserCustomization().load()`, then `ui.loadFromServer()`, `replicon.loadCustomization()`, `contractor.loadCustomization()`.
- Theme and use12h changes debounce-save to server (800ms) via `ui._debouncedSave()`. jiraPattern changes save explicitly via store actions.

### PDF Generation (Browsershot)

- Requires Chromium, Node.js, npm, and the `puppeteer` npm package — all installed in both `dev` and `runtime` Dockerfile stages.
- `InvoicePdfService` explicitly sets node/npm binary paths via `setNodeBinary()` / `setNpmBinary()` — Alpine Linux paths (`/usr/bin/node`, `/usr/bin/npm`) are not found by Browsershot's default PATH expansion.
- Env vars `BROWSERSHOT_CHROMIUM_PATH`, `BROWSERSHOT_NODE_PATH`, `BROWSERSHOT_NPM_PATH` are set in the Dockerfile and can be overridden via `.env`.
- `puppeteer` is installed at `/app/node_modules` via `npm install puppeteer --prefix /app` in the Dockerfile — this step must remain in both stages or PDF generation will fail after a rebuild.

### Replicon API Quirks

Full Replicon integration detail (credential files, PROJ mode, QueueRequests, row map, submit flow): [`docs/replicon-api.md`](docs/replicon-api.md).

**Do not change these:**
- `SetDuration paramList[2]` → `(string)$col`
- `SetComment paramList[2]` → `(int)$col`
- Column formula: `($iso + 1) % 7` where `$iso` = Carbon `dayOfWeekIso` (Mon=1..Sun=7)
- `extractLeafTasks`: always recurse into folder nodes regardless of `Enabled`; only append leaf tasks (no `ChildTasks`)
- Action 11 session redirect: parse `sessionId:'([^']+)'` from response, update creds, retry once
- `last_request_index` incremented inside `DB::transaction + lockForUpdate` to prevent races

---

## Frontend Structure (`frontend/`)

```
nuxt.config.ts                 ssr:false, static preset, Vuetify plugin
nginx.conf                     SPA static server; uses try_files $uri /index.html (no $uri/ — avoids trailing-slash 301s)
app.vue
plugins/
  vuetify.ts                   light/dark themes with primary=#5b6af5 in dark
  persist.client.ts            on startup: check sessionExpiry → auth.me() → load user customization → seed stores
middleware/auth.global.ts      login → verify-email → app route guard (strips trailing slash from path before comparisons)
layouts/
  default.vue                  app shell only: app bar, clock, shortcuts dialog, account menu (no tabs)
  module.vue                   thin wrapper: <NuxtLayout name="default"> + module tabs + <slot>
  auth.vue                     centered card layout for login/register/etc.
stores/
  auth.ts                      token + user + sessionExpiry, persisted to localStorage (tt_auth)
                               login(email, password, keepLoggedIn=true) — sets sessionExpiry if keepLoggedIn=false
                               profile actions: updateProfile, updatePassword, uploadAvatar, deleteAvatar
  ui.ts                        theme, use12h, currentDate, sortCol, shortcutsDialog; changes debounce-save to server
  contractor.ts                entries + clients + invoices + company + jiraPattern
  replicon.ts                  entries + credentials + projects + rowMap + jiraPattern
composables/
  useApi.ts                    $fetch wrapper, attaches Bearer, handles 401
  useTimeFormat.ts             formatTime (12/24h), minutesToHHMM, minutesToDecimal
  useGapOverlap.ts             detectGapsAndOverlaps
  useShortcuts.ts              global keydown: ←/→ (date nav), [ / ] (tab cycle), T (today), ? (shortcuts dialog)
  useUserCustomization.ts      load/save user prefs via GET|PUT /api/user/customization
components/
  entries/   AutocompleteInput, CopyFromDayDialog
  contractor/  ContractorDayEntryTable, ContractorEntryEditDialog, ContractorEntryRowNew,
               CompanySettingsCard, ClientDetailsCard, InvoiceCreateCard,
               InvoiceList, InvoiceDetailDialog
  replicon/    RepliconDayEntryTable, RepliconEntryEditDialog, RepliconEntryRowNew,
               RepliconProjectSelect, RepliconSubProjectSelect, RepliconSplitDialog,
               CredentialsCard, ProjectBrowser, RowMapEditor
  ui/          DateNavBar (prev/today/next nav + slot for actions)
pages/
  login / register / verify-email / forgot-password / reset-password
  profile                      avatar upload, name change, password change (layout: default, title: 'Profile')
  replicon/{day,week,compiled,settings}       (layout: module)
  contractor/{day,week,compiled,invoicing,settings}  (layout: module)
```

### Key Frontend Constraints

- `useShortcuts()` must be called inside `<script setup>` at the page level (not in layouts) to avoid duplicate listeners.
- **Layout system**: `default.vue` is the app shell (no tabs). `module.vue` wraps `default` and adds tabs — all module pages use `definePageMeta({ layout: 'module' })`. Non-module authenticated pages (e.g. profile) use `definePageMeta({ layout: 'default', title: 'PageName' })`. The navbar reads `route.meta.title` for the third segment; falls back to path-based detection for module pages.
- **Shortcuts**: `[` / `]` cycle tabs left/right. `←` / `→` navigate dates. `T` jumps to today. `?` toggles the shortcuts dialog. Digits `1-5` are NOT bound (they were removed to avoid conflicts with `v-autocomplete` inputs). `ui.shortcutsDialog` controls the dialog state.
- **Project/sub-project selects** use `v-autocomplete` (not `v-select`) — supports free-text filtering.
- `AutocompleteInput` uses `inheritAttrs: false` + `v-bind="$attrs"` so it accepts all `v-text-field` props.
- Cascade in `EntryEditDialog`: delta = new finish − original finish; affects rows where `start >= originalFinish` on same day.
- Acc Time is always computed chronologically, regardless of the current display sort.
- Dark mode primary color for interactive elements: `#5b6af5`. Never use the near-black accent for hover/focus in dark mode.
- `InvoiceCreateCard` uses `v-select` (not `AutocompleteInput`) for the client field — clients are a known fixed list, not free-text.
- `ContractorDayEntryTable` topbar shows `Today: Xh Ym | Week: Xh Ym`. Week is Sat–Fri. Week total sums all entries across the week, not just the current day.
- Contractor icon on the home page (`pages/index.vue`) uses `color="#6D3B2E"` (dark brown).
- `Client.tasks` is `{ id: string; name: string }[]` (not `string[]`) — task objects, not raw strings.
- Entry-table logic (gap/overlap, acc time, sort) lives in the variant-specific `*DayEntryTable` components — do not put it in pages.
- `toApiPayload()` in stores uses `'key' in entry` guards so partial PATCHes only send changed fields.
- **Email verification**: `verify-email.vue` uses `watch(() => route.query, handleQuery, { immediate: true })` — NOT `onMounted` — so it fires both on fresh page loads AND when Vue Router reuses the component (same path, different query). The nginx config must NOT include `$uri/` in `try_files` to avoid trailing-slash 301s that strip query params.
- **Account dropdown**: shows the user's avatar (`v-avatar`) when `auth.user.avatar_url` is set, falls back to `mdi-account-circle` icon. Includes a Profile link. Present in both `layouts/default.vue` and `pages/index.vue`.

---

## API Routes Summary

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout                     [auth]
GET    /api/auth/verify-email/{id}/{hash}   [signed]
POST   /api/auth/resend-verification        [auth]
GET    /api/me                              [auth]

GET|POST|PUT|DELETE  /api/replicon/entries/{id?}    [auth+verified]
GET|PUT|DELETE       /api/replicon/credentials       [auth+verified]
GET                  /api/replicon/projects           [auth+verified]
GET|PUT              /api/replicon/row-map            [auth+verified]
POST                 /api/replicon/sync               [auth+verified]
POST                 /api/replicon/submit             [auth+verified]

GET|POST|PUT|DELETE  /api/contractor/entries/{id?}   [auth+verified]
GET|POST|PUT|DELETE  /api/contractor/clients/{id?}   [auth+verified]
GET|POST|PUT|DELETE  /api/contractor/invoices/{id?}  [auth+verified]
GET                  /api/contractor/invoices/{id}/pdf [auth+verified]
GET|PUT              /api/contractor/company          [auth+verified]
POST                 /api/contractor/company/logo     [auth+verified]

GET|PUT              /api/user/customization          [auth+verified]
PATCH                /api/user/profile                [auth+verified]
PUT                  /api/user/password               [auth+verified]
POST                 /api/user/avatar                 [auth+verified]
DELETE               /api/user/avatar                 [auth+verified]

GET /api/health
```

---

## Production Deployment

- **Backend:** `fly deploy` from `backend/`. Fly app: `timetracker-api`, region `yyz`, scale-to-zero. Release command runs `php artisan migrate --force`.
- **Frontend:** `fly deploy` from `frontend/`. Fly app: `timetracker-app`, region `yyz`, scale-to-zero. `NUXT_PUBLIC_API_BASE` is a build arg set in `frontend/fly.toml` — baked into the JS bundle at build time. GitHub Actions deploys automatically on push to `main` when `frontend/**` changes.
- **Database:** Supabase `ca-central-1`, use pooler port 6543 (not 5432) to avoid connection exhaustion on Fly scale-to-zero.
- **Backend secrets:** `fly secrets set APP_KEY=... DB_HOST=... DB_PASSWORD=...` (see `README.md` for the full list). Storage vars are `SUPABASE_S3_KEY`, `SUPABASE_S3_SECRET`, `SUPABASE_S3_BUCKET`, `SUPABASE_S3_ENDPOINT` — not `AWS_*`.

---

## How to Make Changes

**Backend:**
1. Edit files in `backend/` — no build step
2. `docker compose up` — artisan serve hot-reloads
3. For new API endpoints: add migration → model → controller → route → resource
4. Run `php artisan route:list` to verify

**Frontend:**
1. Edit files in `frontend/` — Nuxt HMR picks up changes instantly
2. `npm run build` before merging to catch TypeScript errors
3. New pages: create under `pages/`, Nuxt auto-registers them
4. New stores: create under `stores/`, Pinia auto-imports via `@pinia/nuxt`

---

## Open Items

- [x] Mail provider: Resend (SMTP — `smtp.resend.com:587`, username `resend`)
- [x] Account dropdown with user name/email and profile link
- [x] User profile page — avatar upload (512KB), name change, password change
- [x] "Keep me logged in" checkbox on login (unchecked = 2h frontend session expiry + server-side token deletion)
- [x] Keyboard shortcuts help dialog (`?` key or toolbar icon)
- [x] Tab navigation via `[` / `]`; project/sub-project selects use `v-autocomplete`
- [x] Email verification fixed (nginx trailing-slash redirect + middleware path normalization + `watch` instead of `onMounted`)
- [ ] Split entry dialog — `RepliconSplitDialog` exists; `ContractorSplitDialog` not yet built; `openSplit` stubs remain in day.vue pages
- [ ] Replicon PROJ mode — `RepliconProjectSelect` and `RepliconSubProjectSelect` components built; full wiring in `RepliconEntryRowNew` / `RepliconEntryEditDialog` still in progress
- [ ] `useShortcuts` keyboard shortcut `N` (focus new-entry row) — ref to new-row input needed from page level
- [ ] Rate limits: bump `throttle:api` to `throttle:120,1` for the `auth:sanctum,verified` group before going live
- [ ] Supabase RLS: not used (auth is in Laravel policies) — document this so future contributors don't add it accidentally
