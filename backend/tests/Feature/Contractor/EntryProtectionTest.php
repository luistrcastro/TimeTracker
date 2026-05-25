<?php

namespace Tests\Feature\Contractor;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\ContractorTimeEntry;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->client = Client::forceCreate(['user_id' => $this->user->id, 'name' => 'Acme']);
    }

    private function makeInvoice(InvoiceStatus $status): Invoice
    {
        return Invoice::forceCreate([
            'user_id'      => $this->user->id,
            'client_id'    => $this->client->id,
            'number'       => 'INV-0001',
            'created_date' => '2026-01-01',
            'due_date'     => '2026-01-31',
            'rate'         => 100,
            'subtotal'     => 0,
            'tax_rate'     => 0,
            'tax_amount'   => 0,
            'total'        => 0,
            'status'       => $status,
        ]);
    }

    private function makeEntry(Invoice $invoice): ContractorTimeEntry
    {
        return ContractorTimeEntry::forceCreate([
            'user_id'          => $this->user->id,
            'invoice_id'       => $invoice->id,
            'description'      => 'Work',
            'date'             => '2026-01-15',
            'duration_minutes' => 60,
        ]);
    }

    public function test_update_allowed_for_draft_invoiced_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);
        $entry   = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/entries/{$entry->id}", ['description' => 'Updated'])
             ->assertOk();
    }

    public function test_update_blocked_for_sent_invoiced_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent);
        $entry   = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/entries/{$entry->id}", ['description' => 'Updated'])
             ->assertUnprocessable()
             ->assertJsonPath('message', 'Entry belongs to a finalized invoice.');
    }

    public function test_update_blocked_for_approved_invoiced_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Approved);
        $entry   = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/entries/{$entry->id}", ['description' => 'Updated'])
             ->assertUnprocessable();
    }

    public function test_update_blocked_for_paid_invoiced_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Paid);
        $entry   = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->putJson("/api/contractor/entries/{$entry->id}", ['description' => 'Updated'])
             ->assertUnprocessable();
    }

    public function test_delete_allowed_for_draft_invoiced_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Draft);
        $entry   = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->deleteJson("/api/contractor/entries/{$entry->id}")
             ->assertNoContent();
    }

    public function test_delete_blocked_for_sent_invoiced_entry(): void
    {
        $invoice = $this->makeInvoice(InvoiceStatus::Sent);
        $entry   = $this->makeEntry($invoice);

        $this->actingAs($this->user, 'sanctum')
             ->deleteJson("/api/contractor/entries/{$entry->id}")
             ->assertUnprocessable()
             ->assertJsonPath('message', 'Entry belongs to a finalized invoice.');
    }
}
