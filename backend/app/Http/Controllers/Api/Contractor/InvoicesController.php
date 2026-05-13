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
            ->update(['invoiced' => true, 'invoice_id' => $invoice->id]);

        return new InvoiceResource($invoice->load('timeEntries'));
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load('timeEntries'));
    }

    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,paid,void'],
            'notes'  => ['nullable', 'string'],
        ]);

        $newStatus = InvoiceStatus::from($data['status']);

        if ($newStatus === InvoiceStatus::Void && $invoice->status !== InvoiceStatus::Void) {
            $invoice->timeEntries()->update(['invoiced' => false, 'invoice_id' => null]);
        }

        $invoice->update([
            'status' => $newStatus,
            'notes'  => $data['notes'] ?? $invoice->notes,
        ]);

        return new InvoiceResource($invoice->load('timeEntries'));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->timeEntries()->update(['invoiced' => false, 'invoice_id' => null]);
        $invoice->delete();
        return response()->json(null, 204);
    }

    public function pdf(Invoice $invoice, InvoicePdfService $pdfService): StreamedResponse
    {
        $pdf = $pdfService->render($invoice);
        $filename = "{$invoice->client->name} - {$invoice->number} - {$invoice->created_date->format('Y-m-d')}.pdf";

        return response()->streamDownload(
            fn() => print($pdf),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
