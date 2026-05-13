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
            'start'          => $this->start,
            'finish'         => $this->finish,
            'duration'       => $this->durationAsHHMM(),
            'logged'         => $this->logged,
        ];
    }
}
