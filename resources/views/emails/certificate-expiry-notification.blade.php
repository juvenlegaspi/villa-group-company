<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate Expiry Alert</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <p>Hello {{ $recipientName ?: 'Team' }},</p>

    <p>
        @if($isOverdue)
            This vessel certificate is overdue and requires immediate compliance action.
        @else
            This vessel certificate is approaching its expiry date.
        @endif
    </p>

    <p>
        <strong>Vessel:</strong> {{ $vesselName }}<br>
        <strong>Certificate:</strong> {{ $certificateName }}<br>
        <strong>Expiry Date:</strong> {{ $expiryDate }}<br>
        <strong>{{ $isOverdue ? 'Days Overdue' : 'Days Remaining' }}:</strong> {{ abs($daysRemaining) }}
    </p>

    @if(!empty($remarks))
        <p><strong>Remarks:</strong> {{ $remarks }}</p>
    @endif

    <p>{{ $isOverdue ? 'Please escalate and begin renewal action immediately.' : 'Please begin the renewal process before the certificate expires.' }}</p>

    <p>Vessel Monitoring System</p>
</body>
</html>
