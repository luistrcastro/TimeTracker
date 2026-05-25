<?php

namespace Tests\Feature\Contractor;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ContractorTimeEntry;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user   = User::factory()->create();
        $this->client = Client::forceCreate(['user_id' => $this->user->id, 'name' => 'Acme']);

        Storage::fake();
        Storage::disk()->buildTemporaryUrlsUsing(fn ($path) => "https://fake/{$path}");
    }

    private function makeInvoice(array $attrs = []): Invoice
    {
        return Invoice::forceCreate(array_merge([
            'user_id'      => $this->user->id,
            'client_id'    => $this->client->id,
            'number'       => 'INV-0001',
            'created_date' => '2026-01-01',
            'due_date'     => '2026-01-31',
            'rate'         => 100.00,
            'subtotal'     => 0,
            'tax_rate'     => 10.00,
            'tax_amount'   => 0,
            'total'        => 0,
            'status'       => InvoiceStatus::Draft,
        ], $attrs));
    }

    private function makeEntry(Invoice $invoice, int $durationMinutes = 120): ContractorTimeEntry
    {
        return ContractorTimeEntry::forceCreate([
            'user_id'          => $this->user->id,
            'invoice_id'       => $invoice->id,
            'description'      => 'Work',
            'date'             => '2026-01-15',
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function test_update_allows_sent_to_approved(): void
    {
        $invoice = $this->makeInvoice(['status' => InvoiceStatus::Sent]);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/invoices/{$invoice->id}", ['status' => 'approved'])
             ->assertOk()
             ->assertJsonPath('status', 'approved');
    }

    public function test_update_allows_approved_to_paid(): void
    {
        $invoice = $this->makeInvoice(['status' => InvoiceStatus::Approved]);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/invoices/{$invoice->id}", ['status' => 'paid'])
             ->assertOk()
             ->assertJsonPath('status', 'paid');
    }

    public function test_update_rejects_illegal_transition(): void
    {
        $invoice = $this->makeInvoice(['status' => InvoiceStatus::Draft]);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/invoices/{$invoice->id}", ['status' => 'approved'])
             ->assertUnprocessable();
    }

    public function test_update_rejects_void_transition(): void
    {
        $invoice = $this->makeInvoice(['status' => InvoiceStatus::Sent]);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/invoices/{$invoice->id}", ['status' => 'void'])
             ->assertUnprocessable();
    }

    public function test_void_deletes_pdf_unlinks_entries_and_sets_status_void(): void
    {
        $invoice = $this->makeInvoice(['status' => InvoiceStatus::Sent]);
        $pdfPath = "invoices/{$this->user->id}/{$invoice->id}.pdf";
        Storage::disk()->put($pdfPath, 'PDF-BYTES');
        $invoice->update(['pdf_path' => $pdfPath]);
        $entry = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/contractor/invoices/{$invoice->id}/void")
             ->assertOk()
             ->assertJsonPath('status', 'void');

        Storage::assertMissing($pdfPath);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'void', 'pdf_path' => null]);
        $this->assertDatabaseHas('contractor_time_entries', ['id' => $entry->id, 'invoice_id' => null]);
    }

    public function test_revert_deletes_pdf_and_sets_status_draft(): void
    {
        $invoice = $this->makeInvoice(['status' => InvoiceStatus::Sent]);
        $pdfPath = "invoices/{$this->user->id}/{$invoice->id}.pdf";
        Storage::disk()->put($pdfPath, 'PDF-BYTES');
        $invoice->update(['pdf_path' => $pdfPath]);

        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/contractor/invoices/{$invoice->id}/revert")
             ->assertOk()
             ->assertJsonPath('status', 'draft')
             ->assertJsonPath('pdfStored', false);

        Storage::assertMissing($pdfPath);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'draft', 'pdf_path' => null]);
    }

    public function test_send_generates_pdf_stores_it_and_sets_status_sent(): void
    {
        $this->mock(InvoicePdfService::class)
             ->shouldReceive('render')->once()->andReturn('PDF-BYTES');

        $invoice = $this->makeInvoice();
        $this->makeEntry($invoice, 120); // 2h → $200 subtotal, $20 tax, $220 total

        $this->actingAs($this->user, 'sanctum')
             ->postJson("/api/contractor/invoices/{$invoice->id}/send")
             ->assertOk()
             ->assertJsonPath('status', 'sent')
             ->assertJsonPath('pdfStored', true);

        Storage::assertExists("invoices/{$this->user->id}/{$invoice->id}.pdf");

        $this->assertDatabaseHas('invoices', [
            'id'         => $invoice->id,
            'status'     => 'sent',
            'subtotal'   => '200.00',
            'tax_amount' => '20.00',
            'total'      => '220.00',
        ]);
    }
}
