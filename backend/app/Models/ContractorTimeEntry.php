<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasDuration;
use App\Models\Concerns\HasTimeWindow;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class ContractorTimeEntry extends Model
{
    use BelongsToUser, HasDuration, HasTimeWindow, HasUuidV7;

    protected $fillable = [
        'client_id', 'invoice_id', 'client_task_id', 'task', 'description',
        'sub_description', 'date', 'start', 'finish', 'duration_minutes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function clientTask()
    {
        return $this->belongsTo(ClientTask::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
