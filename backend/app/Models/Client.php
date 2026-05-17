<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = ['name', 'legal_name', 'address', 'phone', 'email'];

    public function tasks()
    {
        return $this->hasMany(ClientTask::class);
    }

    public function entries()
    {
        return $this->hasMany(ContractorTimeEntry::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
