<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1e293b; }
  .header { background: linear-gradient(135deg, #0077BE, #00A8E8); color: white; padding: 30px 40px; }
  .header h1 { font-size: 28px; font-weight: bold; letter-spacing: -0.5px; }
  .header .tagline { font-size: 11px; opacity: 0.85; margin-top: 2px; }
  .header .receipt-info { float: right; text-align: right; }
  .header .receipt-id { font-size: 18px; font-weight: bold; }
  .clearfix::after { content: ''; display: table; clear: both; }
  .body { padding: 30px 40px; }
  .section { margin-bottom: 24px; }
  .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; margin-bottom: 12px; }
  .info-grid { display: table; width: 100%; }
  .info-cell { display: table-cell; width: 50%; vertical-align: top; }
  .info-label { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
  .info-value { font-size: 13px; font-weight: 600; color: #1e293b; }
  table.items { width: 100%; border-collapse: collapse; }
  table.items thead tr { background: #f8fafc; }
  table.items th { padding: 10px 12px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; }
  table.items th.right { text-align: right; }
  table.items td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
  table.items td.right { text-align: right; }
  table.items td.center { text-align: center; }
  .totals { background: #f8fafc; border-radius: 8px; padding: 16px 20px; }
  .totals-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; }
  .totals-row.total { font-size: 15px; font-weight: bold; border-top: 2px solid #e2e8f0; padding-top: 10px; margin-top: 4px; color: #0077BE; }
  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; }
  .status-pending { background: #fef3c7; color: #92400e; }
  .status-paid { background: #d1fae5; color: #065f46; }
  .status-shipped { background: #dbeafe; color: #1e40af; }
  .status-delivered { background: #dcfce7; color: #14532d; }
  .status-cancelled { background: #fee2e2; color: #991b1b; }
  .footer { margin-top: 40px; text-align: center; color: #94a3b8; font-size: 10px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
</style>
</head>
<body>
<div class="header clearfix">
  <div>
    <h1>RT Water Solution</h1>
    <div class="tagline">Votre partenaire de confiance pour des solutions en eau — Yaoundé, Cameroun</div>
  </div>
  <div class="receipt-info">
    <div style="font-size:10px;opacity:.8;margin-bottom:4px;">REÇU DE COMMANDE</div>
    <div class="receipt-id">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</div>
    <div style="font-size:10px;opacity:.8;margin-top:4px;">{{ \Carbon\Carbon::parse($order->created_at)->locale('fr')->isoFormat('D MMMM YYYY') }}</div>
  </div>
</div>

<div class="body">
  <!-- Client & Order info -->
  <div class="section">
    <div class="section-title">Informations</div>
    <div class="info-grid">
      <div class="info-cell">
        <div class="info-label">Client</div>
        <div class="info-value">{{ $order->user?->name ?? 'Client invité' }}</div>
        @if($order->user?->email)
          <div style="color:#64748b;font-size:11px;margin-top:2px;">{{ $order->user->email }}</div>
        @endif
        @if($order->user?->phone)
          <div style="color:#64748b;font-size:11px;">{{ $order->user->phone }}</div>
        @endif
      </div>
      <div class="info-cell">
        <div class="info-label">Adresse de livraison</div>
        <div class="info-value" style="font-weight:400;">{{ $order->address ?: 'Non spécifiée' }}</div>
        <div style="margin-top:10px;">
          <div class="info-label">Statut</div>
          @php
            $statusLabels = ['pending'=>'En attente','paid'=>'Payée','shipped'=>'Expédiée','delivered'=>'Livrée','cancelled'=>'Annulée'];
            $statusClass = ['pending'=>'status-pending','paid'=>'status-paid','shipped'=>'status-shipped','delivered'=>'status-delivered','cancelled'=>'status-cancelled'];
          @endphp
          <span class="status-badge {{ $statusClass[$order->status] ?? '' }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Items table -->
  <div class="section">
    <div class="section-title">Articles commandés</div>
    <table class="items">
      <thead>
        <tr>
          <th>Article</th>
          <th class="center">Qté</th>
          <th class="right">Prix unit.</th>
          <th class="right">Total</th>
        </tr>
      </thead>
      <tbody>
        @foreach($order->items as $item)
        <tr>
          <td>{{ $item->product?->name ?? 'Produit supprimé' }}</td>
          <td class="center">{{ $item->quantity }}</td>
          <td class="right">{{ number_format($item->price, 0, ',', ' ') }} XAF</td>
          <td class="right" style="font-weight:600;">{{ number_format($item->price * $item->quantity, 0, ',', ' ') }} XAF</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="section">
    <div class="totals">
      <div class="totals-row">
        <span style="color:#64748b;">Sous-total</span>
        <span>{{ number_format($order->total, 0, ',', ' ') }} XAF</span>
      </div>
      <div class="totals-row">
        <span style="color:#64748b;">Livraison</span>
        <span>Gratuite</span>
      </div>
      <div class="totals-row total">
        <span>Total TTC</span>
        <span>{{ number_format($order->total, 0, ',', ' ') }} XAF</span>
      </div>
    </div>
  </div>
</div>

<div class="footer">
  <p>Merci de votre confiance — RT Water Solution</p>
  <p style="margin-top:4px;">info@rt-water.cm · +237 6XX XXX XXX · Yaoundé, Cameroun</p>
  <p style="margin-top:4px;">Ce document tient lieu de reçu officiel de commande</p>
</div>
</body>
</html>
