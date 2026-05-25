<?php

namespace App\Http\Controllers\Api\Contractor;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\ContractorTimeEntry;
use App\Models\Invoice;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicesController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            Invoice::with('timeEntries')->orderByDesc('created_date')->get()
        );
    }

    public function store(Request $request): InvoiceResource
    {
        $data = $request->validate([
            'clientId'    => ['required', 'uuid', 'exists:clients,id'],
            'number'      => ['required', 'string', 'max:50'],
            'createdDate' => ['required', 'date_format:Y-m-d'],
            'dueDate'     => ['required', 'date_format:Y-m-d'],
            'rate'        => ['required', 'numeric', 'min:0'],
            'subtotal'    => ['required', 'numeric', 'min:0'],
            'taxRate'     => ['required', 'numeric', 'min:0'],
            'taxAmount'   => ['required', 'numeric', 'min:0'],
            'total'       => ['required', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string'],
            'entryIds'    => ['required', 'array', 'min:1'],
            'entryIds.*'  => ['uuid'],
        ]);

        $invoice = Invoice::create([
            'client_id'    => $data['clientId'],
            'number'       => $data['number'],
            'created_date' => $data['createdDate'],
            'due_date'     => $data['dueDate'],
            'rate'         => $data['rate'],
            'subtotal'     => $data['subtotal'],
            'tax_rate'     => $data['taxRate'],
            'tax_amount'   => $data['taxAmount'],
            'total'        => $data['total'],
            'notes'        => $data['notes'] ?? '',
            'status'       => InvoiceStatus::Draft,
        ]);

        ContractorTimeEntry::whereIn('id', $data['entryIds'])
            ->where('user_id', auth()->id())
            ->update(['invoice_id' => $invoice->id]);

        return new InvoiceResource($invoice->load('timeEntries'));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('timeEntries'));
    }

    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', 'string'],
            'notes'  => ['nullable', 'string'],
        ]);

        if (isset($data['status'])) {
            $newStatus = InvoiceStatus::tryFrom($data['status']);
            $allowed   = match ($invoice->status) {
                InvoiceStatus::Sent     => [InvoiceStatus::Approved],
                InvoiceStatus::Approved => [InvoiceStatus::Paid],
                default                 => [],
            };
            if (! $newStatus || ! in_array($newStatus, $allowed, true)) {
                abort(422, 'Invalid status transition.');
            }
            $invoice->status = $newStatus;
        }

        if (array_key_exists('notes', $data)) {
            $invoice->notes = $data['notes'] ?? '';
        }

        $invoice->save();

        return new InvoiceResource($invoice->fresh()->load('timeEntries'));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->timeEntries()->update(['invoice_id' => null]);
        $invoice->delete();
        return response()->json(null, 204);
    }

    public function void(Invoice $invoice): InvoiceResource
    {
        if ($invoice->pdf_path) {
            Storage::disk()->delete($invoice->pdf_path);
        }

        $invoice->timeEntries()->update(['invoice_id' => null]);
        $invoice->update(['pdf_path' => null, 'status' => InvoiceStatus::Void]);

        return new InvoiceResource($invoice->fresh()->load('timeEntries'));
    }

    public function revert(Invoice $invoice): InvoiceResource
    {
        if ($invoice->pdf_path) {
            Storage::disk()->delete($invoice->pdf_path);
        }

        $invoice->update(['pdf_path' => null, 'status' => InvoiceStatus::Draft]);

        return new InvoiceResource($invoice->fresh()->load('timeEntries'));
    }

    public function send(Invoice $invoice, InvoicePdfService $pdfService): InvoiceResource
    {
        $invoice->load('timeEntries');

        $subtotal = $invoice->timeEntries->sum(function ($entry) use ($invoice) {
            $hours = round($entry->duration_minutes / 60 / 0.25) * 0.25;
            return $hours * $invoice->rate;
        });
        $subtotal  = round($subtotal, 2);
        $taxAmount = round($subtotal * $invoice->tax_rate / 100, 2);
        $total     = round($subtotal + $taxAmount, 2);

        // Set on unsaved model so the PDF blade reads updated values
        $invoice->status     = InvoiceStatus::Sent;
        $invoice->subtotal   = $subtotal;
        $invoice->tax_amount = $taxAmount;
        $invoice->total      = $total;

        $pdf  = $pdfService->render($invoice);
        $path = "invoices/{$invoice->user_id}/{$invoice->id}.pdf";

        Storage::disk()->put($path, $pdf);

        $invoice->update([
            'subtotal'   => $subtotal,
            'tax_amount' => $taxAmount,
            'total'      => $total,
            'pdf_path'   => $path,
            'status'     => InvoiceStatus::Sent,
        ]);

        return new InvoiceResource($invoice->fresh()->load('timeEntries'));
    }

    public function pdf(Invoice $invoice, InvoicePdfService $pdfService): StreamedResponse
    {
        $filename = "{$invoice->client->name} - {$invoice->number} - {$invoice->created_date->format('Y-m-d')}.pdf";
        $contents = $invoice->pdf_path
            ? Storage::disk()->get($invoice->pdf_path)
            : $pdfService->render($invoice);

        return response()->streamDownload(
            fn() => print($contents),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
