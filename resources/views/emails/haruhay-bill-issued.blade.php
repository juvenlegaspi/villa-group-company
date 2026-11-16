<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Haruhay Monthly Bill</title></head>
<body style="margin:0;background:#f3f5f0;font-family:Arial,sans-serif;color:#18392c;padding:24px 12px">
<table role="presentation" style="width:100%;max-width:600px;margin:auto;border-collapse:collapse;background:white"><tr><td style="background:#184e39;color:white;padding:27px"><strong style="font-size:23px">HARUHAY SPACE</strong><p style="margin:8px 0 0;color:#f4d477">Monthly billing statement &middot; {{ $bill['code'] }}</p></td></tr><tr><td style="padding:28px">
<p>Hello {{ $bill['name'] }},</p><p>Your monthly bill has been issued. Here are the finalized details:</p>
<p><strong>{{ $bill['property'] }} &middot; {{ $bill['room'] }}</strong><br>Period: {{ $bill['period'] }}<br>Due date: <strong>{{ $bill['due'] }}</strong></p>
<table style="width:100%;border-collapse:collapse;font-size:14px"><thead><tr><th style="text-align:left;padding:10px 0;border-bottom:1px solid #ddd">Charge</th><th style="text-align:right;border-bottom:1px solid #ddd">Amount</th></tr></thead><tbody>
@foreach($bill['charges'] as $label => $amount)<tr><td style="padding:9px 0;border-bottom:1px solid #eee">{{ $label }}</td><td style="text-align:right;border-bottom:1px solid #eee">{{ $label === 'Discount' ? '-' : '' }}PHP {{ number_format($amount, 2) }}</td></tr>@endforeach
<tr><td style="padding:12px 0"><strong>Invoice total</strong></td><td style="text-align:right"><strong>PHP {{ number_format($bill['total'], 2) }}</strong></td></tr>
<tr><td style="padding:9px 0">Payments / advance applied</td><td style="text-align:right">PHP {{ number_format($bill['paid'], 2) }}</td></tr>
<tr style="background:#eaf0e4"><td style="padding:15px 10px"><strong>Remaining balance</strong></td><td style="text-align:right;padding:15px 10px"><strong>PHP {{ number_format($bill['balance'], 2) }}</strong></td></tr>
</tbody></table>
<p style="font-size:12px;color:#657362;line-height:1.8">Balance as of {{ $bill['as_of'] }}. If you have paid since this bill was issued, please coordinate with Haruhay staff to confirm the updated balance. This statement is not a payment receipt.</p>
<p style="font-size:13px;line-height:1.8">Please arrange payment using the official instructions provided by Haruhay staff. For billing questions, contact the Haruhay team.</p>
<p>Thank you,<br><strong>Haruhay Space</strong></p></td></tr></table></body></html>
