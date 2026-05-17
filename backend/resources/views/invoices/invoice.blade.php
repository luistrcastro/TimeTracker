<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: 'Noto Sans', sans-serif; font-size: 13px; color: #1a1a1a; margin: 0; padding: 0; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; padding: 24px; border-bottom: 2px solid #e5e7eb; }
  .addresses { display: flex; gap: 0; padding: 24px; }
  .address-divider { width: 1px; background: #e5e7eb; margin: 0 32px; flex-shrink: 0; }
  .address-block { flex: 1; }
  table { width: 100%; border-collapse: collapse; margin: 0 24px; width: calc(100% - 48px); }
  th { background: #f3f4f6; text-align: left; padding: 8px; }
  td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
  .totals { text-align: right; padding: 16px 24px; }
  .notes { padding: 16px 24px; color: #6b7280; }
</style>
</head>
<body>
<div class="header">
  <div>
    @if($logoDataUrl)
      <img src="{{ $logoDataUrl }}" style="max-height:48px;max-width:200px;width:auto;height:auto" alt="logo">
    @endif
    <div style="font-size:18px;font-weight:700;margin-top:8px">{{ $company?->name }}</div>
  </div>
  <div style="text-align:right">
    <div style="font-size:20px;font-weight:700;color:#1a1a1a">{{ $invoice->number }}</div>
    <div style="color:#4b5563"><span style="font-weight:700">Date:</span> {{ $invoice->created_date->format('Y-m-d') }}</div>
    <div style="color:#4b5563"><span style="font-weight:700">Due:</span> {{ $invoice->due_date->format('Y-m-d') }}</div>
    @php
      $statusColor = match($invoice->status->value) {
        'paid'  => '#16a34a',
        'sent'  => '#2563eb',
        'void'  => '#dc2626',
        default => '#6b7280',
      };
    @endphp
    <div style="margin-top:8px;padding:3px 10px;border:1.5px solid {{ $statusColor }};color:{{ $statusColor }};border-radius:4px;display:inline-block;font-size:11px;font-weight:600;letter-spacing:0.05em">
      {{ strtoupper($invoice->status->value) }}
    </div>
  </div>
</div>
<div class="addresses">
  <div class="address-block">
    <div style="font-weight:700;margin-bottom:4px">From</div>
    @if($company?->name)<div>{{ $company->name }}</div>@endif
    @if($company?->address)<div>{{ $company->address }}</div>@endif
    @if($company?->phone)<div>{{ $company->phone }}</div>@endif
    @if($company?->email)<div>{{ $company->email }}</div>@endif
    @if(!$company || (!$company->name && !$company->address))<div style="color:#9ca3af;font-style:italic">No company details set</div>@endif
  </div>
  <div class="address-divider"></div>
  <div class="address-block">
    <div style="font-weight:700;margin-bottom:4px">Bill To</div>
    <div>{{ $invoice->client->legal_name ?: $invoice->client->name }}</div>
    <div>{{ $invoice->client->address }}</div>
    <div>{{ $invoice->client->phone }}</div>
    <div>{{ $invoice->client->email }}</div>
  </div>
</div>
<table>
  <thead><tr><th>Date</th><th>Description</th><th>Hours</th><th>Amount</th></tr></thead>
  <tbody>
    @foreach($invoice->timeEntries as $entry)
    <tr>
      <td>{{ $entry->date->format('Y-m-d') }}</td>
      <td>{{ $entry->description }}@if($entry->sub_description) — {{ $entry->sub_description }}@endif</td>
      <td>{{ round($entry->duration_minutes / 60, 2) }}</td>
      <td>${{ number_format(round($entry->duration_minutes / 60 / 0.25) * 0.25 * $invoice->rate, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
<div class="totals">
  <div>Subtotal: ${{ number_format($invoice->subtotal, 2) }}</div>
  <div>Tax ({{ $invoice->tax_rate }}%): ${{ number_format($invoice->tax_amount, 2) }}</div>
  <div style="font-weight:700;font-size:15px;margin-top:8px">Total: ${{ number_format($invoice->total, 2) }}</div>
</div>
@if($invoice->notes)
<div class="notes">{{ $invoice->notes }}</div>
@endif
</body>
</html>
