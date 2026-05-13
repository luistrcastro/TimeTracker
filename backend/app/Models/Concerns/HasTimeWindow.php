<?php

namespace App\Models\Concerns;

trait HasTimeWindow
{
    public function getDurationMinutesAttribute(): int
    {
        return $this->attributes['duration_minutes'] ?? 0;
    }
}
