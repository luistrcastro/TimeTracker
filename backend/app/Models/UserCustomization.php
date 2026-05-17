<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Model;

class UserCustomization extends Model
{
    use BelongsToUser, HasUuidV7;

    protected $fillable = ['configuration'];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
        ];
    }

    /**
     * Returns the full configuration document, merging stored values over
     * application defaults so missing keys are always present in the response.
     * Add new namespaces/keys here — no migration needed.
     */
    public function getConfigAttribute(): array
    {
        $defaults = [
            'ui' => [
                'theme'         => 'light',
                'use12h'        => false,
                'activeVariant' => 'replicon',
            ],
            'replicon'   => ['jiraPattern' => 'PROJ-\d+'],
            'contractor' => ['jiraPattern' => 'PROJ-\d+'],
        ];

        $stored = $this->configuration ?? [];
        $merged = $defaults;

        foreach ($defaults as $ns => $nsDefaults) {
            $merged[$ns] = array_merge($nsDefaults, $stored[$ns] ?? []);
        }

        return $merged;
    }
}
