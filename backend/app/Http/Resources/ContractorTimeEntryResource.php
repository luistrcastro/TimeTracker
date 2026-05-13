<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractorTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'clientId'       => $this->client_id,
            'invoiceId'      => $this->invoice_id,
            'task'           => $this->task,
            'description'    => $this->description,
            'subDescription' => $this->sub_description,
            'date'           => $this->date?->format('Y-m-d'),
            'start'          => $this->start,
            'finish'         => $this->finish,
            'duration'       => $this->durationAsHHMM(),
            'invoiced'       => $this->invoiced,
        ];
    }
}
