<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class RepliconTask extends Model
{
    use HasUuidV7;

    protected $fillable = ['replicon_project_id', 'replicon_task_id', 'name', 'path'];

    protected function casts(): array
    {
        return ['path' => 'array'];
    }

    public function project()
    {
        return $this->belongsTo(RepliconProject::class, 'replicon_project_id');
    }
}
