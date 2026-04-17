<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate Expiry Alert</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <p>Hello {{ $recipientName ?: 'Team' }},</p>

    <p>This is to inform you that a vessel certificate is due to expire soon.</p>

    <p>
        <strong>Vessel:</strong> {{ $vesselName }}<br>
        <strong>Certificate:</strong> {{ $certificateName }}<br>
        <strong>Expiry Date:</strong> {{ $expiryDate }}<br>
        <strong>Days Remaining:</strong> {{ $daysRemaining }}
    </p>

    @if(!empty($remarks))
        <p><strong>Remarks:</strong> {{ $remarks }}</p>
    @endif

    <p>Please take the necessary action before the certificate expires.</p>

    <p>Vessel Monitoring System</p>
</body>
</html>
