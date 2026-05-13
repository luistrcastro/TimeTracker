<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Spatie\Browsershot\Browsershot;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $html = view('invoices.invoice', [
            'invoice' => $invoice->load('client', 'timeEntries'),
            'company' => $invoice->user->companySetting,
        ])->render();

        return Browsershot::html($html)
            ->setChromePath(env('BROWSERSHOT_CHROMIUM_PATH', '/usr/bin/chromium-browser'))
            ->noSandbox()
            ->format('A4')
            ->margins(15, 15, 15, 15)
            ->showBackground()
            ->pdf();
    }
}
