<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class RepliconCredential extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = [
        'base_url', 'session_id', 'server_view_state_id',
        'cookie_header', 'last_request_index', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'session_id'           => 'encrypted',
            'server_view_state_id' => 'encrypted',
            'cookie_header'        => 'encrypted',
            'expires_at'           => 'datetime',
            'last_request_index'   => 'integer',
        ];
    }
}
