<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $company = $invoice->user->companySetting;

        $logoDataUrl = null;
        if ($company?->logo_path) {
            $bytes = Storage::disk()->get($company->logo_path);
            if ($bytes) {
                $mime = Storage::disk()->mimeType($company->logo_path) ?: 'image/png';
                $logoDataUrl = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

        return Pdf::loadView('invoices.invoice', [
            'invoice'     => $invoice->load('client', 'timeEntries'),
            'company'     => $company,
            'logoDataUrl' => $logoDataUrl,
        ])->setPaper('a4')->output();
    }
}
