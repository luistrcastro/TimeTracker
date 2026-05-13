<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Replicon\RepliconClient;
use App\Services\Replicon\RepliconSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncRepliconProjects implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private string $userId) {}

    public function handle(): void
    {
        $user   = User::findOrFail($this->userId);
        $client = new RepliconClient($user);
        $svc    = new RepliconSyncService($client);
        $svc->sync($user);
    }
}
