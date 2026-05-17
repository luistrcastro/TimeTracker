<?php

namespace App\Models\Concerns;

trait HasDuration
{
    public function durationAsHHMM(): string
    {
        $minutes = $this->duration_minutes ?? 0;
        return sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
