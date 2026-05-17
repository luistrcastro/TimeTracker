# Time Tracker

Personal time tracking web app. Log work entries throughout the day, compile them for Replicon, and generate client invoices.

---

## Stack

| Layer | Tech |
|---|---|
| Backend API | Laravel 12 + Sanctum + Postgres 16 |
| Frontend | Nuxt 4 + Vue 3 + Vuetify 3 + TypeScript |
| Dev database | Docker (postgres:16-alpine) |
| Dev email | Mailpit |
| Prod backend | Fly.io (`yyz`) |
| Prod database | Supabase (pooler port 6543) |
| Prod frontend | Fly.io (`yyz`) |

---

## Local development

### Prerequisites

- Docker + Docker Compose
- Node.js 20+

### 1. Start everything

```bash
docker compose up -d

# First time only
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate
```

| Service | URL |
|---|---|
| Frontend | http://localhost:3000 |
| Backend | http://localhost:8020 |
| Mailpit (email) | http://localhost:8025 |

Both Laravel and the Nuxt dev server hot-reload — edit files in `backend/` or `frontend/` and changes apply immediately.

### 2. Create an account

Open http://localhost:3000/register. The verification email lands in Mailpit at http://localhost:8025.

---

## Deployment

### Prerequisites

- [Fly CLI](https://fly.io/docs/hands-on/install-flyctl/) authenticated (`fly auth login`)
- Supabase project (database + file storage)
- A transactional mail provider (Resend or Postmark)

### Backend — Fly.io (`timetracker-api`)

**First deploy:**

```bash
cd backend

# Generate an app key locally to use as a secret
php artisan key:generate --show
# → base64:xxxxx...

fly secrets set \
  APP_KEY="base64:xxxxx..." \
  APP_URL="https://timetracker-api.fly.dev" \
  FRONTEND_URL="https://timetracker-app.fly.dev" \
  DB_HOST="aws-0-ca-central-1.pooler.supabase.com" \
  DB_PORT=6543 \
  DB_DATABASE=postgres \
  DB_USERNAME="postgres.your-project-ref" \
  DB_PASSWORD="your-supabase-db-password" \
  MAIL_MAILER=smtp \
  MAIL_HOST="smtp.resend.com" \
  MAIL_PORT=587 \
  MAIL_USERNAME=resend \
  MAIL_PASSWORD="re_xxxx..." \
  MAIL_FROM_ADDRESS="noreply@yourdomain.com" \
  AWS_ACCESS_KEY_ID="your-supabase-s3-key-id" \
  AWS_SECRET_ACCESS_KEY="your-supabase-s3-secret" \
  AWS_BUCKET="company-logos" \
  AWS_ENDPOINT="https://your-project-ref.supabase.co/storage/v1/s3" \
  AWS_USE_PATH_STYLE_ENDPOINT=true

fly deploy
```

Migrations run automatically on every deploy (`release_command = "php artisan migrate --force"` in `fly.toml`).

> **Important:** Use Supabase's pooler port **6543**, not 5432. Fly scales to zero between requests; 5432 exhausts Supabase's direct connection limit.

### Frontend — Fly.io (`timetracker-app`)

`NUXT_PUBLIC_API_BASE` is baked into the JS bundle at build time. It's set in `frontend/fly.toml` under `[build.args]` — update it there if the API URL changes.

**First deploy:**

```bash
cd frontend
fly deploy
```

**Subsequent deploys** (both apps):

```bash
cd backend  && fly deploy
cd frontend && fly deploy
```

---

## Migrating data from the legacy app

The `timetracker:import` command reads the JSON files from `TimeTrackerSystem/` and writes them into the database for a given user account.

**What gets imported:**

| File | Destination |
|---|---|
| `data-replicon.json` (or `data.json`) | Replicon time entries |
| `data-contractor.json` | Contractor time entries |
| `data-contractor-clients.json` | Client billing details |
| `data-contractor-invoices.json` | Invoices + entry links |
| `replicon-credentials.json` | Replicon session credentials |
| `replicon-projects-cache.json` | Projects and tasks cache |
| `replicon-row-map.json` | Project/task → timesheet row index map |

Existing UUIDs are reused. Running the import twice is safe — duplicates are skipped.

### Into the dev database

```bash
# Find your user UUID
docker compose exec laravel \
  php artisan tinker --execute="echo \App\Models\User::where('email', 'you@example.com')->value('id');"

# Dry run first
docker compose exec laravel \
  php artisan timetracker:import /app/TimeTrackerSystem {USER_UUID} --dry-run

# Run for real
docker compose exec laravel \
  php artisan timetracker:import /app/TimeTrackerSystem {USER_UUID}
```

Note: the container mounts `backend/` as `/app` — it cannot see `TimeTrackerSystem/` directly. See the production instructions below; the same workaround applies here.

### Into the production database (Fly.io)

The Fly machine scales to zero, so files uploaded via SSH won't persist. Instead, run the import locally using the dev container but pointed at Supabase.

**1. Register on the production app first, then get your UUID:**

```bash
fly ssh console -a timetracker-api \
  -C "php artisan tinker --execute=\"echo App\Models\User::where('email','you@example.com')->value('id');\""
```

**2. Copy the legacy JSON files into `backend/`** (the only directory mounted in the container):

```bash
cp TimeTrackerSystem/data-*.json backend/
cp TimeTrackerSystem/replicon-credentials.json \
   TimeTrackerSystem/replicon-projects-cache.json \
   TimeTrackerSystem/replicon-row-map.json \
   backend/ 2>/dev/null || true
```

**3. Run the import against the production database:**

```bash
docker compose -f docker-compose.prod.yml exec \
  -e DB_HOST="aws-0-ca-central-1.pooler.supabase.com" \
  -e DB_PORT=6543 \
  -e DB_DATABASE=postgres \
  -e DB_USERNAME="postgres.your-project-ref" \
  -e DB_PASSWORD="your-supabase-password" \
  laravel \
  php artisan timetracker:import /app {USER_UUID} --dry-run
```

Remove `--dry-run` once the counts look right. The `-e` flags override the DB connection for this one call only — your dev `.env` and dev database are untouched.

**4. Clean up:**

```bash
rm -f backend/data-*.json backend/replicon-credentials.json \
       backend/replicon-projects-cache.json backend/replicon-row-map.json
```

---

## Project structure

```
backend/                    Laravel 12 API
frontend/                   Nuxt 4 SPA (source under frontend/app/)
docker-compose.yml          Dev: postgres + mailpit + laravel + frontend
docker-compose.prod.yml     Prod image smoke-test + Fly.io secrets reference
docker-compose.legacy.yml   Legacy standalone Python app (TimeTrackerSystem)
TimeTrackerSystem/          Legacy single-user app (archived)
docs/                       replicon-api.md, history.md, running.md
CLAUDE.md                   Full developer reference (architecture, schemas, decisions)
```

For full architecture details, data schemas, API routes, and development decisions see [CLAUDE.md](CLAUDE.md).

---

## Legacy app

The original single-user Python + vanilla JS app lives in `TimeTrackerSystem/` and still works standalone. See [docs/running.md](docs/running.md) for instructions.
