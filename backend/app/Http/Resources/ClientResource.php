<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'legalName' => $this->legal_name,
            'address'   => $this->address,
            'phone'     => $this->phone,
            'email'     => $this->email,
            'tasks'     => $this->whenLoaded('tasks', fn() => $this->tasks->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values()),
        ];
    }
}
