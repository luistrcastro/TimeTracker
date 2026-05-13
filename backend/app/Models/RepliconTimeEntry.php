<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasDuration;
use App\Models\Concerns\HasTimeWindow;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class RepliconTimeEntry extends Model
{
    use BelongsToUser, HasDuration, HasTimeWindow, HasUuidV7;

    protected $fillable = [
        'date', 'project', 'sub_project', 'description',
        'sub_description', 'further_info', 'start', 'finish',
        'duration_minutes', 'logged',
    ];

    protected function casts(): array
    {
        return [
            'date'   => 'date:Y-m-d',
            'logged' => 'boolean',
        ];
    }
}
