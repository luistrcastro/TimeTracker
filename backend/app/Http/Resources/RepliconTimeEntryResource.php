<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepliconTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'date'           => $this->date?->format('Y-m-d'),
            'project'        => $this->project,
            'subProject'     => $this->sub_project,
            'description'    => $this->description,
            'subDescription' => $this->sub_description,
            'furtherInfo'    => $this->further_info,
            'start'          => $this->start ? substr($this->start, 0, 5) : null,
            'finish'         => $this->finish ? substr($this->finish, 0, 5) : null,
            'duration'        => $this->durationAsHHMM(),
            'durationMinutes' => $this->duration_minutes,
            'logged'          => $this->logged,
        ];
    }
}
