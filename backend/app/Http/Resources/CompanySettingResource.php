<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name'           => $this->name,
            'address'        => $this->address,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'logoUrl'        => $this->logo_path
                ? \Storage::disk('supabase')->temporaryUrl($this->logo_path, now()->addMinutes(10))
                : null,
            'defaultRate'    => $this->default_rate,
            'defaultTaxRate' => $this->default_tax_rate,
        ];
    }
}
