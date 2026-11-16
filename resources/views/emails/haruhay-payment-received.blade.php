<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Haruhay Payment Receipt</title></head>
<body style="margin:0;background:#f3f5f0;font-family:Arial,sans-serif;color:#18392c;padding:24px 12px;font-size:13px">
<table role="presentation" style="width:100%;max-width:620px;margin:auto;border-collapse:collapse;background:white"><tr><td style="background:#184e39;color:white;padding:24px"><strong style="font-size:23px">HARUHAY SPACE</strong><p style="margin:8px 0 0;color:#f4d477">Payment receipt &middot; {{ $receipt['code'] }}</p></td></tr><tr><td style="padding:25px">
<p>Hello {{ $receipt['name'] }},</p><p>We have recorded your payment. Thank you.</p>
<table style="width:100%;border-collapse:collapse;font-size:12px">
@foreach(['Receipt number' => $receipt['code'], 'Receipt type' => $receipt['type'], 'Received from' => $receipt['name'], 'Property / Room' => $receipt['property'].' / '.$receipt['room'], 'Payment date' => $receipt['paid_on'], 'Recorded at' => $receipt['recorded_at'], 'Payment method' => $receipt['method'], 'Transaction reference' => $receipt['reference'] ?: 'Cash / Not applicable', 'Recorded by' => $receipt['received_by']] as $label => $value)
<tr><td style="padding:7px 0;color:#687665;width:40%">{{ $label }}</td><td style="padding:7px 0;text-align:right">{{ $value }}</td></tr>
@endforeach
<tr style="background:#eaf0e4"><td style="padding:14px 10px"><strong>Actual amount received</strong></td><td style="text-align:right;padding:14px 10px"><strong>PHP {{ number_format($receipt['amount'], 2) }}</strong></td></tr>
<tr><td style="padding:10px 0">Applied to bills</td><td style="text-align:right">PHP {{ number_format($receipt['applied'], 2) }}</td></tr>
<tr><td style="padding:10px 0">Unapplied tenant credit</td><td style="text-align:right">PHP {{ number_format($receipt['credit'], 2) }}</td></tr>
</table>
@foreach($receipt['allocations'] as $allocation)
<div style="border-top:1px solid #ddd;margin-top:16px;padding-top:14px"><strong>{{ $allocation['bill'] }}</strong><p style="color:#687665;font-size:12px">Covered period: {{ $allocation['period'] }}</p><p>Payment applied: <strong>PHP {{ number_format($allocation['amount'], 2) }}</strong><br>Remaining bill balance: <strong>PHP {{ number_format($allocation['balance'], 2) }}</strong><br>Bill status: <strong>{{ $allocation['status'] }}</strong></p></div>
@endforeach
@if(!$receipt['allocations'])<p style="padding:12px;background:#f4f5e9">This payment is held as advance credit. It will be applied when an eligible bill is issued; it does not mean a future bill is fully paid.</p>@endif
<p style="font-size:11px;color:#687665;line-height:1.8">Amounts, applications and balances above reflect the time this payment was recorded. Later bill applications, payments or corrections may change the current balance. Contact Haruhay staff for the latest account details.</p>
@unless($pdf ?? false)<p style="font-size:12px">Your PDF receipt is attached. Keep this email and receipt number for your records.</p>@endunless
<p>Thank you,<br><strong>Haruhay Space</strong></p>
</td></tr></table></body></html>
