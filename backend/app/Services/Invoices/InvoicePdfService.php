<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

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

        $html = view('invoices.invoice', [
            'invoice'     => $invoice->load('client', 'timeEntries'),
            'company'     => $company,
            'logoDataUrl' => $logoDataUrl,
        ])->render();

        return Browsershot::html($html)
            ->setChromePath(env('BROWSERSHOT_CHROMIUM_PATH', '/usr/bin/chromium-browser'))
            ->setNodeBinary(env('BROWSERSHOT_NODE_PATH', '/usr/bin/node'))
            ->setNpmBinary(env('BROWSERSHOT_NPM_PATH', '/usr/bin/npm'))
            ->noSandbox()
            ->addChromiumArguments(['--disable-dev-shm-usage'])
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->pdf();
    }
}
