<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class RepliconRowMap extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = ['replicon_project_id', 'replicon_task_id', 'row_index'];
}
