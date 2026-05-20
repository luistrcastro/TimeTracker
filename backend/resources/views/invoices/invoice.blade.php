<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #1a1a1a; margin: 0; padding: 0; }
  .header-table { width: 100%; border-collapse: collapse; border-bottom: 1px solid #e5e7eb; }
  .addresses-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
  .address-cell { padding: 12px 24px; vertical-align: top; width: 50%; }
  .divider-cell { width: 1px; background: #e5e7eb; padding: 0; }
  table.entries { width: 100%; border-collapse: collapse; }
  th { background: #f3f4f6; text-align: left; padding: 8px; }
  td.entry { padding: 8px; border-bottom: 1px solid #e5e7eb; }
  .totals { text-align: right; padding: 16px 24px; }
  .notes { padding: 16px 24px; color: #6b7280; }
</style>
</head>
<body>

<table class="header-table">
  <tr>
    <td style="vertical-align:top;padding:24px 24px 20px 24px">
      @if($logoDataUrl)
        <img src="{{ $logoDataUrl }}" style="max-height:48px;max-width:200px;width:auto;height:auto" alt="logo">
      @endif
      <div style="font-size:18px;font-weight:700;margin-top:8px">{{ $company?->name }}</div>
    </td>
    <td style="vertical-align:top;text-align:right;padding:24px 24px 20px 24px">
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
      <div style="margin-top:8px">
        <span style="padding:3px 10px;border:1.5px solid {{ $statusColor }};color:{{ $statusColor }};border-radius:4px;font-size:11px;font-weight:600;letter-spacing:0.05em">
          {{ strtoupper($invoice->status->value) }}
        </span>
      </div>
    </td>
  </tr>
</table>

<table class="addresses-table">
  <tr>
    <td class="address-cell">
      <div style="font-weight:700;margin-bottom:4px">From</div>
      @if($company?->name)<div>{{ $company->name }}</div>@endif
      @if($company?->address)<div>{{ $company->address }}</div>@endif
      @if($company?->phone)<div>{{ $company->phone }}</div>@endif
      @if($company?->email)<div>{{ $company->email }}</div>@endif
      @if(!$company || (!$company->name && !$company->address))<div style="color:#9ca3af;font-style:italic">No company details set</div>@endif
    </td>
    <td class="divider-cell"></td>
    <td class="address-cell">
      <div style="font-weight:700;margin-bottom:4px">Bill To</div>
      <div>{{ $invoice->client->legal_name ?: $invoice->client->name }}</div>
      <div>{{ $invoice->client->address }}</div>
      <div>{{ $invoice->client->phone }}</div>
      <div>{{ $invoice->client->email }}</div>
    </td>
  </tr>
</table>

<div style="padding:0 24px">
  <table class="entries">
    <thead><tr><th style="width:90px">Date</th><th>Description</th><th style="text-align:right;width:60px">Hours</th><th style="text-align:right;width:80px">Amount</th></tr></thead>
    <tbody>
      @foreach($invoice->timeEntries as $entry)
      <tr>
        <td class="entry">{{ $entry->date->format('Y-m-d') }}</td>
        <td class="entry">{{ $entry->description }}@if($entry->sub_description) — {{ $entry->sub_description }}@endif</td>
        <td class="entry" style="text-align:right">{{ round($entry->duration_minutes / 60, 2) }}</td>
        <td class="entry" style="text-align:right">${{ number_format(round($entry->duration_minutes / 60 / 0.25) * 0.25 * $invoice->rate, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

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
