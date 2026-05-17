<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = [
        'name', 'address', 'phone', 'email',
        'logo_path', 'default_rate', 'default_tax_rate',
    ];

    protected function casts(): array
    {
        return [
            'default_rate'     => 'decimal:2',
            'default_tax_rate' => 'decimal:2',
        ];
    }
}
