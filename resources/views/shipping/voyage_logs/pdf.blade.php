<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
        color: #111;
        margin: 28px;
    }
    * {
        box-sizing: border-box;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #222;
        padding: 5px 6px;
        vertical-align: middle;
    }
    .header-table td {
        border: 1px solid #444;
    }
    .header-table .logo-cell {
        width: 20%;
        text-align: center;
    }
    .header-table .company-cell {
        width: 60%;
        text-align: center;
        padding: 14px 12px;
    }
    .header-table .meta-cell {
        width: 20%;
        padding: 0;
    }
    .meta-table td {
        border: 1px solid #444;
        padding: 6px 8px;
        font-size: 10px;
    }
    .meta-label {
        width: 58%;
    }
    .meta-value {
        text-align: center;
    }
    .company-title {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 2px;
    }
    .company-sub {
        font-size: 11px;
        margin: 0;
        line-height: 1.35;
    }
    .report-title {
        text-align: center;
        font-size: 17px;
        font-weight: 700;
        margin: 18px 0 16px;
    }
    .summary-table td {
        border: 1px solid #222;
        padding: 3px 8px;
        font-size: 11px;
    }
    .label {
        font-weight: 700;
        width: 17%;
        white-space: nowrap;
    }
    .value {
        width: 16.33%;
    }
    .section-title {
        font-size: 15px;
        font-weight: 700;
        text-transform: uppercase;
        margin: 36px 0 10px;
        text-decoration: underline;
    }
    .status-title {
        font-size: 12px;
        font-weight: 700;
        margin: 26px 0 10px;
    }
    .activity-table th,
    .fuel-table th {
        background: #f2f2f2;
        font-weight: 700;
        text-align: center;
    }
    .total-row td {
        font-weight: 700;
    }
    .total-label {
        text-align: right;
    }
    .signatures {
        margin-top: 48px;
        border-collapse: collapse;
    }
    .signatures td {
        border: none;
        text-align: center;
        width: 50%;
        padding: 0 14px;
    }
    .sign-label {
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 42px;
    }
    .sign-name {
        font-size: 14px;
        font-weight: 400;
        border-bottom: 1px solid #222;
        display: inline-block;
        min-width: 190px;
        padding-bottom: 2px;
    }
    .sign-role {
        font-style: italic;
        margin-top: 3px;
        font-size: 11px;
    }
    tr {
        page-break-inside: avoid;
    }
</style>
</head>
<body>
@php
    $groupedDetails = $voyage->details->groupBy('status');
    $dateStarted = $voyage->date_created ? \Carbon\Carbon::parse($voyage->date_created)->format('M d, Y') : '-';
    $dateEnded = $voyage->date_completed ? \Carbon\Carbon::parse($voyage->date_completed)->format('M d, Y') : '-';
    $voyageHours = number_format((float) ($voyage->total_hours_voyage ?? $voyage->details->sum('total_hours')), 2);
    $overallConsumption = (float) $voyage->fuelMonitorings->sum('total_consumed');
@endphp

<table class="header-table">
    <tr>
        <td class="logo-cell">
            <img src="{{ public_path('logo.jpg') }}"
                style="width: 110px; height: 72px; object-fit: contain;">
        </td>
        <td class="company-cell">
            <p class="company-title">VILLA SHIPPING LINES, INC.</p>
            <p class="company-sub">
                2nd floor Villa Shipping Lines Bldg. T. Padilla Street, Cebu City<br>
                Tel. Nos. 2331384; 234 0713
            </p>
        </td>
        <td class="meta-cell">
            <table class="meta-table">
                <tr>
                    <td class="meta-label">Page:</td>
                    <td class="meta-value">Page</td>
                </tr>
                <tr>
                    <td class="meta-label">Doc No.:</td>
                    <td class="meta-value"></td>
                </tr>
                <tr>
                    <td class="meta-label">Rev No.:</td>
                    <td class="meta-value">0</td>
                </tr>
                <tr>
                    <td class="meta-label">Date Revised:</td>
                    <td class="meta-value"></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="report-title">VOYAGE LOG REPORT</div>

<table class="summary-table">
    <tr>
        <td class="label">Voyage ID:</td>
        <td class="value">{{ $voyage->voyage_code }}</td>
        <td class="label">Crew on Board:</td>
        <td class="value">{{ $voyage->crew_on_board ?? '-' }}</td>
        <td class="label">Date Voyage Started:</td>
        <td class="value">{{ $dateStarted }}</td>
    </tr>
    <tr>
        <td class="label">Voyage Number:</td>
        <td class="value">{{ $voyage->voyage_no ?? '-' }}</td>
        <td class="label">Cargo Type:</td>
        <td class="value">{{ $voyage->cargo_type ?? '-' }}</td>
        <td class="label">Date Voyage Ended:</td>
        <td class="value">{{ $dateEnded }}</td>
    </tr>
    <tr>
        <td class="label">Vessel Name:</td>
        <td class="value">{{ $voyage->vessel->vessel_name ?? '-' }}</td>
        <td class="label">Cargo Volume:</td>
        <td class="value">{{ $voyage->cargo_volume ?? '-' }}</td>
        <td class="label">Voyage Total Hours:</td>
        <td class="value">{{ $voyageHours }}</td>
    </tr>
    <tr>
        <td class="label">Port:</td>
        <td class="value">{{ $voyage->port_location ?? '-' }}</td>
        <td class="label">Fuel ROB:</td>
        <td class="value">{{ $voyage->fuel_rob ?? '-' }}</td>
        <td class="label"></td>
        <td class="value"></td>
    </tr>
</table>

<div class="section-title">Activity Timeline</div>
@foreach($groupedDetails as $status => $details)
    @php
        $statusTotal = (float) $details->flatMap->activities->sum('total_hours');
    @endphp
    <div style="margin-bottom: 8px;">
        <div class="status-title">Status: {{ $details->first()->statusRelation->name ?? $status }}</div>
        <table class="activity-table">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Port</th>
                    <th>Remarks</th>
                    <th>Total Hours</th>
                </tr>
            </thead>
            <tbody>
                @foreach($details as $detail)
                    @foreach($detail->activities as $act)
                        <tr>
                            <td>{{ $act->activity->name ?? '-' }}</td>
                            <td>{{ $act->start_date_time ? \Carbon\Carbon::parse($act->start_date_time)->format('M d, Y h:i A') : '-' }}</td>
                            <td>{{ $act->end_date_time ? \Carbon\Carbon::parse($act->end_date_time)->format('M d, Y h:i A') : '-' }}</td>
                            <td>{{ $act->port_location ?? '-' }}</td>
                            <td>{{ $act->remarks ?? '-' }}</td>
                            <td>{{ number_format((float) $act->total_hours, 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr class="total-row">
                    <td colspan="5" class="total-label">Total All</td>
                    <td>{{ number_format($statusTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach

<div class="section-title">Fuel Monitoring Report</div>
<table class="fuel-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Main Engine</th>
            <th>Auxiliary</th>
            <th>Boiler</th>
            <th>Others</th>
            <th>Total ROB</th>
            <th>Total Consumption</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody>
        @foreach($voyage->fuelMonitorings as $fuel)
        <tr>
            <td>{{ \Carbon\Carbon::parse($fuel->created_at)->format('M d, Y') }}</td>
            <td>{{ number_format($fuel->main_engine, 2) }}</td>
            <td>{{ number_format($fuel->auxiliary_engine, 2) }}</td>
            <td>{{ number_format($fuel->boiler ?? 0, 2) }}</td>
            <td>{{ number_format($fuel->others, 2) }}</td>
            <td>{{ number_format($fuel->remaining_fuel, 2) }}</td>
            <td>{{ number_format($fuel->total_consumed, 2) }}</td>
            <td>{{ $fuel->remarks ?? '-' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table class="fuel-table" style="margin-top: 8px;">
    <tr>
        <td style="width:66%;"><b>Total Fuel Consumed</b></td>
        <td>{{ number_format($overallConsumption, 2) }}</td>
    </tr>
    <tr>
        <td><b>Net Available Fuel</b></td>
        <td>{{ $voyage->fuel_rob ?? '-' }}</td>
    </tr>
</table>

<table class="signatures">
    <tr>
        <td>
            <div class="sign-label">Prepared By:</div>
            <div class="sign-name">{{ trim(($voyage->creator->name ?? '') . ' ' . ($voyage->creator->lastname ?? '')) ?: ' ' }}</div>
            
        </td>
        <td>
            <div class="sign-label">Reviewed By:</div>
            <div class="sign-name"> </div>
            <div class="sign-role">General Manager</div>
        </td>
    </tr>
</table>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("Arial", "normal");
    $size = 10;
    $pageText = "{PAGE_NUM}";
    $x = 540;
    $y = 40;
    $pdf->page_text($x, $y, $pageText, $font, $size);
}
</script>
</body>
</html>
