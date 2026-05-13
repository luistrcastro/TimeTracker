<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class ClientTask extends Model
{
    use HasUuidV7;

    protected $fillable = ['name'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
