<?php

namespace App\Models\Concerns;

use App\Models\User;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::creating(function ($m) {
            if (! $m->user_id && auth()->check()) {
                $m->user_id = auth()->id();
            }
        });

        static::addGlobalScope('user', function ($q) {
            if (auth()->check()) {
                $q->where($q->getModel()->getTable().'.user_id', auth()->id());
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
