<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; }
  .header { background: #0077BE; color: white; padding: 24px 32px; display: flex; justify-content: space-between; }
  .header h1 { font-size: 28px; font-weight: bold; letter-spacing: 1px; }
  .header .meta { text-align: right; }
  .header .meta p { font-size: 11px; opacity: 0.85; }
  .header .meta .number { font-size: 18px; font-weight: bold; }
  .body { padding: 24px 32px; }
  .parties { display: flex; justify-content: space-between; margin-bottom: 28px; }
  .party { width: 45%; }
  .party h3 { font-size: 10px; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
  .party p { line-height: 1.6; }
  .party .name { font-weight: bold; font-size: 14px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  thead tr { background: #f1f5f9; }
  th { padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
  td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
  tr:last-child td { border-bottom: none; }
  .totals { width: 260px; margin-left: auto; }
  .totals .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; }
  .totals .row.total { border-top: 2px solid #0077BE; margin-top: 8px; padding-top: 10px; font-weight: bold; font-size: 15px; color: #0077BE; }
  .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: bold; }
  .badge-invoice { background: #dbeafe; color: #1d4ed8; }
  .badge-quote { background: #fef9c3; color: #92400e; }
  .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="header">
  <div>
    <h1>RT Water Solution</h1>
    <p style="opacity:.75;margin-top:4px">Solutions d'eau potable</p>
  </div>
  <div class="meta">
    <p class="number">{{ $invoice->number }}</p>
    <p style="margin-top:4px">
      <span class="badge {{ $invoice->type === 'quote' ? 'badge-quote' : 'badge-invoice' }}">
        {{ $invoice->type === 'quote' ? 'DEVIS' : 'FACTURE' }}
      </span>
    </p>
    <p style="margin-top:8px">Date : {{ $invoice->created_at->format('d/m/Y') }}</p>
    @if($invoice->due_at)
    <p>Échéance : {{ $invoice->due_at->format('d/m/Y') }}</p>
    @endif
  </div>
</div>

<div class="body">
  <div class="parties">
    <div class="party">
      <h3>Émetteur</h3>
      <p class="name">RT Water Solution</p>
      <p>Bénin, Afrique de l'Ouest</p>
      <p>contact@rtwater.com</p>
    </div>
    <div class="party">
      <h3>Client</h3>
      <p class="name">{{ $invoice->user->name }}</p>
      <p>{{ $invoice->user->email }}</p>
      @if($invoice->user->phone)
      <p>{{ $invoice->user->phone }}</p>
      @endif
      @if($invoice->order->address)
      <p>{{ $invoice->order->address }}</p>
      @endif
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:50%">Désignation</th>
        <th style="text-align:center">Qté</th>
        <th style="text-align:right">Prix unit.</th>
        <th style="text-align:right">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($invoice->order->items as $item)
      <tr>
        <td>{{ $item->product->name ?? 'Produit supprimé' }}</td>
        <td style="text-align:center">{{ $item->quantity }}</td>
        <td style="text-align:right">{{ number_format($item->price, 0, ',', ' ') }} XOF</td>
        <td style="text-align:right">{{ number_format($item->price * $item->quantity, 0, ',', ' ') }} XOF</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="totals">
    <div class="row">
      <span>Sous-total HT</span>
      <span>{{ number_format($invoice->subtotal, 0, ',', ' ') }} XOF</span>
    </div>
    @if($invoice->tax > 0)
    <div class="row">
      <span>Taxes</span>
      <span>{{ number_format($invoice->tax, 0, ',', ' ') }} XOF</span>
    </div>
    @endif
    <div class="row total">
      <span>TOTAL TTC</span>
      <span>{{ number_format($invoice->total, 0, ',', ' ') }} XOF</span>
    </div>
  </div>

  <div class="footer">
    <p>RT Water Solution · Bénin · contact@rtwater.com</p>
    <p style="margin-top:4px">Ce document est généré automatiquement et fait foi de {{ $invoice->type === 'quote' ? 'devis commercial' : 'facture' }}.</p>
  </div>
</div>
</body>
</html>
