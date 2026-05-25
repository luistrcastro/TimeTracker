<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'number'      => $this->number,
            'clientId'    => $this->client_id,
            'createdDate' => $this->created_date?->format('Y-m-d'),
            'dueDate'     => $this->due_date?->format('Y-m-d'),
            'rate'        => $this->rate,
            'subtotal'    => $this->subtotal,
            'taxRate'     => $this->tax_rate,
            'taxAmount'   => $this->tax_amount,
            'total'       => $this->total,
            'status'      => $this->status?->value,
            'notes'       => $this->notes,
            'pdfStored'   => $this->pdf_path !== null,
            'pdfPath'     => $this->pdf_path
                ? Storage::disk()->temporaryUrl($this->pdf_path, now()->addMinutes(60))
                : null,
            'entryIds'    => $this->whenLoaded('timeEntries', fn() => $this->timeEntries->pluck('id')),
        ];
    }
}
