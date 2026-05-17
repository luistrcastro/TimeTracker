<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = [
        'client_id', 'number', 'created_date', 'due_date',
        'rate', 'subtotal', 'tax_rate', 'tax_amount', 'total',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date:Y-m-d',
            'due_date'     => 'date:Y-m-d',
            'status'       => InvoiceStatus::class,
            'rate'         => 'decimal:2',
            'subtotal'     => 'decimal:2',
            'tax_rate'     => 'decimal:2',
            'tax_amount'   => 'decimal:2',
            'total'        => 'decimal:2',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(ContractorTimeEntry::class);
    }
}
