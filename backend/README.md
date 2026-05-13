# Time Tracker — Backend

Laravel 12 REST API. See the [root README](../README.md) for local dev setup, deployment, and data migration instructions.

## Key commands

```bash
# Run inside the Docker container:
docker compose -f ../docker/dev/docker-compose.yml exec laravel <command>

php artisan migrate                          # run pending migrations
php artisan migrate:fresh --seed             # wipe and reseed (dev only)
php artisan route:list                       # list all API routes
php artisan tinker                           # REPL against the live DB
php artisan timetracker:import {dir} {uuid}  # import legacy JSON data
php artisan schedule:work                    # run the scheduler locally (replicon credential expiry)
```

## Structure

```
app/
  Console/Commands/       ImportTimeTrackerData, PurgeExpiredRepliconCredentials
  Enums/                  InvoiceStatus
  Http/Controllers/Api/   Auth/, Contractor/, Replicon/
  Models/
    Concerns/             BelongsToUser (global user scope), HasUuidV7, HasTimeWindow, HasDuration
  Services/
    Replicon/             RepliconClient, RepliconSyncService, RepliconSubmitService
    Invoices/             InvoicePdfService
database/migrations/      all domain tables
resources/views/invoices/ invoice.blade.php (PDF template)
routes/api.php
```
