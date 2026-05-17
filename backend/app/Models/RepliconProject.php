<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class RepliconProject extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = ['replicon_id', 'code', 'name', 'synced_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function tasks()
    {
        return $this->hasMany(RepliconTask::class);
    }
}
