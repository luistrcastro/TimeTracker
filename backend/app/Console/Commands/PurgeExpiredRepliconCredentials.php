<?php

namespace App\Console\Commands;

use App\Models\RepliconCredential;
use Illuminate\Console\Command;

class PurgeExpiredRepliconCredentials extends Command
{
    protected $signature = 'replicon:purge-expired';
    protected $description = 'Null encrypted session fields for expired Replicon credentials';

    public function handle(): void
    {
        $count = RepliconCredential::where('expires_at', '<', now())
            ->whereNotNull('cookie_header')
            ->update([
                'session_id'           => null,
                'server_view_state_id' => null,
                'cookie_header'        => null,
                'expires_at'           => null,
            ]);

        $this->info("Purged {$count} expired credential(s).");
    }
}
