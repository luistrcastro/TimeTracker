<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\CompanySetting;
use App\Models\ContractorTimeEntry;
use App\Models\Invoice;
use App\Models\RepliconCredential;
use App\Models\RepliconProject;
use App\Models\RepliconRowMap;
use App\Models\RepliconTask;
use App\Models\RepliconTimeEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImportTimeTrackerData extends Command
{
    protected $signature = 'timetracker:import {jsonDir : Path to directory containing TimeTracker JSON files} {userId : Target user UUID} {--dry-run : Print counts without writing}';
    protected $description = 'Import TimeTracker JSON data files into the new multi-user database';

    public function handle(): int
    {
        $jsonDir = rtrim($this->argument('jsonDir'), '/');
        $userId  = $this->argument('userId');
        $dry     = $this->option('dry-run');

        $user = User::find($userId);
        if (! $user) {
            $this->error("User {$userId} not found.");
            return 1;
        }

        $this->info("Importing into user: {$user->email}" . ($dry ? ' [DRY RUN]' : ''));

        Auth::onceUsingId($userId);

        $this->importRepliconEntries($jsonDir, $dry);
        $this->importContractorEntries($jsonDir, $userId, $dry);
        $this->importClients($jsonDir, $userId, $dry);
        $this->importInvoices($jsonDir, $userId, $dry);
        $this->importRepliconCredentials($jsonDir, $dry);
        $this->importRepliconProjectsCache($jsonDir, $userId, $dry);
        $this->importRepliconRowMap($jsonDir, $userId, $dry);

        $this->info('Import complete.');
        return 0;
    }

    private function importRepliconEntries(string $dir, bool $dry): void
    {
        // Try data-replicon.json first, fall back to data.json
        $file = file_exists("{$dir}/data-replicon.json")
            ? "{$dir}/data-replicon.json"
            : "{$dir}/data.json";

        if (! file_exists($file)) {
            $this->warn("Replicon entries: no file found, skipping.");
            return;
        }

        $rows = json_decode(file_get_contents($file), true) ?? [];
        $count = 0;

        DB::transaction(function () use ($rows, $dry, &$count) {
            foreach ($rows as $row) {
                $durationMins = $this->parseDuration($row['duration'] ?? '0:00');

                if (! $dry) {
                    RepliconTimeEntry::updateOrCreate(
                        ['id' => $row['id']],
                        [
                            'date'            => $row['date'] ?? now()->format('Y-m-d'),
                            'project'         => $row['project'] ?? '',
                            'sub_project'     => $row['subProject'] ?? '',
                            'description'     => $row['description'] ?? '',
                            'sub_description' => $row['subDescription'] ?? '',
                            'further_info'    => $row['furtherInfo'] ?? '',
                            'start'           => $this->normalizeTime($row['start'] ?? null),
                            'finish'          => $this->normalizeTime($row['finish'] ?? null),
                            'duration_minutes'=> $durationMins,
                            'logged'          => (bool) ($row['logged'] ?? false),
                        ]
                    );
                }
                $count++;
            }
        });

        $this->info("Replicon entries: {$count}" . ($dry ? ' (dry)' : ' imported'));
    }

    private function importContractorEntries(string $dir, string $userId, bool $dry): void
    {
        $file = "{$dir}/data-contractor.json";
        if (! file_exists($file)) {
            $this->warn("Contractor entries: file not found, skipping.");
            return;
        }

        $rows  = json_decode(file_get_contents($file), true) ?? [];
        $count = 0;

        DB::transaction(function () use ($rows, $userId, $dry, &$count) {
            foreach ($rows as $row) {
                $clientName = $row['project'] ?? $row['client'] ?? null;
                $clientId   = null;

                if ($clientName) {
                    $client   = Client::firstOrCreate(
                        ['user_id' => $userId, 'name' => $clientName],
                        ['legal_name' => '', 'address' => '', 'phone' => '', 'email' => '']
                    );
                    $clientId = $client->id;

                    $task = $row['subProject'] ?? $row['task'] ?? null;
                    if ($task) {
                        ClientTask::firstOrCreate(['client_id' => $clientId, 'name' => $task]);
                    }
                }

                $durationMins = $this->parseDuration($row['duration'] ?? '0:00');

                if (! $dry) {
                    ContractorTimeEntry::updateOrCreate(
                        ['id' => $row['id']],
                        [
                            'user_id'         => $userId,
                            'client_id'       => $clientId,
                            'task'            => $row['subProject'] ?? $row['task'] ?? '',
                            'description'     => $row['description'] ?? '',
                            'sub_description' => $row['subDescription'] ?? '',
                            'date'            => $row['date'] ?? now()->format('Y-m-d'),
                            'start'           => $this->normalizeTime($row['start'] ?? null),
                            'finish'          => $this->normalizeTime($row['finish'] ?? null),
                            'duration_minutes'=> $durationMins,
                            'invoiced'        => (bool) ($row['invoiced'] ?? false),
                        ]
                    );
                }
                $count++;
            }
        });

        $this->info("Contractor entries: {$count}" . ($dry ? ' (dry)' : ' imported'));
    }

    private function importClients(string $dir, string $userId, bool $dry): void
    {
        $file = "{$dir}/data-contractor-clients.json";
        if (! file_exists($file)) {
            $this->warn("Clients: file not found, skipping.");
            return;
        }

        $data  = json_decode(file_get_contents($file), true) ?? [];
        $count = 0;

        DB::transaction(function () use ($data, $userId, $dry, &$count) {
            foreach ($data as $name => $details) {
                if (! $dry) {
                    $client = Client::updateOrCreate(
                        ['user_id' => $userId, 'name' => $name],
                        [
                            'legal_name' => $details['legalName'] ?? '',
                            'address'    => $details['address']   ?? '',
                            'phone'      => $details['phone']     ?? '',
                            'email'      => $details['email']     ?? '',
                        ]
                    );

                    foreach ($details['tasks'] ?? [] as $taskName) {
                        ClientTask::firstOrCreate(['client_id' => $client->id, 'name' => $taskName]);
                    }
                }
                $count++;
            }
        });

        $this->info("Clients: {$count}" . ($dry ? ' (dry)' : ' imported'));
    }

    private function importInvoices(string $dir, string $userId, bool $dry): void
    {
        $file = "{$dir}/data-contractor-invoices.json";
        if (! file_exists($file)) {
            $this->warn("Invoices: file not found, skipping.");
            return;
        }

        $rows  = json_decode(file_get_contents($file), true) ?? [];
        $count = 0;

        DB::transaction(function () use ($rows, $userId, $dry, &$count) {
            foreach ($rows as $inv) {
                $clientName = $inv['client'] ?? '';
                $client = Client::where('user_id', $userId)->where('name', $clientName)->first();
                if (! $client) {
                    $this->warn("Invoice {$inv['number']}: client '{$clientName}' not found, skipping.");
                    continue;
                }

                if (! $dry) {
                    $invoice = Invoice::updateOrCreate(
                        ['id' => $inv['id']],
                        [
                            'user_id'      => $userId,
                            'client_id'    => $client->id,
                            'number'       => $inv['number'],
                            'created_date' => $inv['createdDate'],
                            'due_date'     => $inv['dueDate'],
                            'rate'         => $inv['rate'] ?? 0,
                            'subtotal'     => $inv['subtotal'] ?? 0,
                            'tax_rate'     => $inv['taxRate'] ?? 0,
                            'tax_amount'   => $inv['taxAmount'] ?? 0,
                            'total'        => $inv['total'] ?? 0,
                            'status'       => InvoiceStatus::from($inv['status'] ?? 'draft'),
                            'notes'        => $inv['notes'] ?? '',
                        ]
                    );

                    // Resolve entryIds → set invoice_id on entries (new FK schema)
                    if (! empty($inv['entryIds'])) {
                        ContractorTimeEntry::whereIn('id', $inv['entryIds'])
                            ->where('user_id', $userId)
                            ->update(['invoice_id' => $invoice->id, 'invoiced' => true]);
                    }
                }
                $count++;
            }
        });

        $this->info("Invoices: {$count}" . ($dry ? ' (dry)' : ' imported'));
    }

    private function importRepliconCredentials(string $dir, bool $dry): void
    {
        $file = "{$dir}/replicon-credentials.json";
        if (! file_exists($file)) {
            $this->warn("Replicon credentials: file not found, skipping.");
            return;
        }

        $data = json_decode(file_get_contents($file), true) ?? [];

        if (! $dry) {
            RepliconCredential::updateOrCreate(
                ['user_id' => auth()->id()],
                [
                    'base_url'             => $data['base_url'] ?? '',
                    'session_id'           => $data['session_id'] ?? null,
                    'server_view_state_id' => $data['server_view_state_id'] ?? null,
                    'cookie_header'        => $data['cookie_header'] ?? null,
                    'last_request_index'   => $data['last_request_index'] ?? 0,
                    'expires_at'           => null,
                ]
            );
        }

        $this->info('Replicon credentials: imported' . ($dry ? ' (dry)' : ''));
    }

    private function importRepliconProjectsCache(string $dir, string $userId, bool $dry): void
    {
        $file = "{$dir}/replicon-projects-cache.json";
        if (! file_exists($file)) {
            $this->warn("Replicon projects cache: file not found, skipping.");
            return;
        }

        $data     = json_decode(file_get_contents($file), true) ?? [];
        $projects = $data['projects'] ?? $data;
        $count    = 0;

        DB::transaction(function () use ($projects, $userId, $dry, &$count) {
            foreach ($projects as $proj) {
                if (! $dry) {
                    $project = RepliconProject::updateOrCreate(
                        ['user_id' => $userId, 'replicon_id' => (string) ($proj['id'] ?? $proj['repliconId'] ?? '')],
                        [
                            'code'      => $proj['code'] ?? '',
                            'name'      => $proj['name'] ?? '',
                            'synced_at' => now(),
                        ]
                    );

                    foreach ($proj['tasks'] ?? [] as $task) {
                        RepliconTask::updateOrCreate(
                            [
                                'replicon_project_id' => $project->id,
                                'replicon_task_id'    => (string) ($task['id'] ?? $task['repliconTaskId'] ?? ''),
                            ],
                            ['name' => $task['name'] ?? '']
                        );
                    }
                }
                $count++;
            }
        });

        $this->info("Replicon projects: {$count}" . ($dry ? ' (dry)' : ' imported'));
    }

    private function importRepliconRowMap(string $dir, string $userId, bool $dry): void
    {
        $file = "{$dir}/replicon-row-map.json";
        if (! file_exists($file)) {
            $this->warn("Replicon row map: file not found, skipping.");
            return;
        }

        $data  = json_decode(file_get_contents($file), true) ?? [];
        $count = 0;

        DB::transaction(function () use ($data, $userId, $dry, &$count) {
            foreach ($data as $key => $rowIndex) {
                [$projectId, $taskId] = array_pad(explode(':', $key, 2), 2, '');
                if (! $dry) {
                    RepliconRowMap::updateOrCreate(
                        [
                            'user_id'             => $userId,
                            'replicon_project_id' => $projectId,
                            'replicon_task_id'    => $taskId,
                        ],
                        ['row_index' => (int) $rowIndex]
                    );
                }
                $count++;
            }
        });

        $this->info("Replicon row maps: {$count}" . ($dry ? ' (dry)' : ' imported'));
    }

    private function parseDuration(string $duration): int
    {
        if (empty($duration)) return 0;
        [$h, $m] = array_pad(explode(':', $duration), 2, '0');
        return (int) $h * 60 + (int) $m;
    }

    private function normalizeTime(?string $t): ?string
    {
        if (! $t) return null;
        // Already HH:MM, or H:MM → normalize to HH:MM
        if (preg_match('/^\d{1,2}:\d{2}$/', $t)) {
            [$h, $m] = explode(':', $t);
            return sprintf('%02d:%02d', (int) $h, (int) $m);
        }
        return null;
    }
}
