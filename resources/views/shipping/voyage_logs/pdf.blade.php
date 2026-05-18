<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
    }
    .header-table, .header-table td {
        border: 1px solid black;
        border-collapse: collapse;
    }
    .header-table td {
        padding: 5px;
    }
    .company-title {
        font-size: 16px;
        font-weight: bold;
        margin: 0;
    }
    .company-sub {
        font-size: 11px;
        margin: 0;
    }
    .section-title {
        font-weight: bold;
        margin-top: 10px;
        margin-bottom: 5px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        background: #f2f2f2;
        font-weight: bold;
    }
    th, td {
        border: 1px solid black;
        padding: 6px;
    }
    .info-table td {
        border: none;
        padding: 4px;
    }
    tr {
        page-break-inside: avoid;
    }
    .signature {
        margin-top: 40px;
    }
    .signature td {
        border: none;
        text-align: center;
    }
    body {
    font-family: Arial, sans-serif;
    font-size: 10px;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
}

th, td {
    border: 1px solid black;
    padding: 4px;
}
</style>
</head>
<body>
<!--  HEADER -->
<table class="header-table" width="100%">
    <tr>
        <td width="20%" align="center" valign="middle">
            <img src="{{ public_path('logo.jpg') }}"
                style="
                    width: 120px;
                    height: 80px;
                    object-fit: contain;
                ">
        </td>
        <td width="60%" align="center">
            <p class="company-title">VILLA SHIPPING LINES, INC.</p>
            <p class="company-sub">
                2nd floor Villa Shipping Lines Bldg. T. Padilla Street, Cebu City<br>
                Tel. Nos. 2331384; 234 0713
            </p>
        </td>
        <td width="20%">
            <table width="100%">
                <tr>
                    <td>Page:</td>
                    <td style="text-align:center;">
                        Page <span class="page-number"></span>
                    </td>
                </tr>
                <tr>
                    <td>Doc No.:</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Rev No.:</td>
                    <td>0</td>
                </tr>
                <tr>
                    <td>Date Revised:</td>
                    <td></td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<br>
<!--  TITLE -->
<h3 style="text-align:center;">VOYAGE LOG REPORT</h3>
<!--  INFO -->
<table class="info-table">
    <tr>
        <td><b>Voyage ID:</b> VL-{{ str_pad($voyage->voyage_id,5,'0',STR_PAD_LEFT) }}</td>
        <td><b>Voyage No:</b> {{ $voyage->voyage_no }}</td>
    </tr>
    <tr>
        <td><b>Port:</b> {{ $voyage->port_location }}</td>
        <td><b>Date Started:</b> {{ \Carbon\Carbon::parse($voyage->date_created)->format('F d, Y') }}</td>
    </tr>
    <tr>
        <td><b>Cargo Type:</b> {{ $voyage->cargo_type }}</td>
        <td><b>Cargo Volume:</b> {{ $voyage->cargo_volume }}</td>
    </tr>
    <tr>
        <td><b>Crew on Board:</b> {{ $voyage->crew_on_board }}</td>
        <td><b>Fuel ROB:</b> {{ $voyage->fuel_rob }}</td>
    </tr>
    @if($voyage->date_completed)
    <tr>
        <td><b>Date Completed:</b> {{ \Carbon\Carbon::parse($voyage->date_completed)->format('F d, Y') }}</td>
        <td></td>
    </tr>
    @endif
</table>
<br>
<!--  ACTIVITIES -->
<div class="section-title">Activity Timeline</div>
{{-- GROUP DETAILS BY STATUS --}}
@php
    $groupedDetails = $voyage->details->groupBy('status');
@endphp
@foreach($groupedDetails as $status => $details)
    <div style="margin-bottom: 25px;">
        {{-- STATUS TITLE --}}
        <h4 style="margin-bottom:10px;">
            Status: {{ $details->first()->statusRelation->name ?? $status }}
        </h4>
        <table width="100%">
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
                {{-- LOOP DETAILS --}}
                @foreach($details as $detail)

                    {{-- LOOP ACTIVITIES --}}
                    @foreach($detail->activities as $act)
                        <tr>
                            {{-- ACTIVITY --}}
                            <td>
                                {{ $act->activity->name ?? '-' }}
                            </td>
                            {{-- START --}}
                            <td>
                                @if($act->start_date_time)
                                    {{ \Carbon\Carbon::parse($act->start_date_time)->format('M d, Y h:i A') }}
                                @else
                                    -
                                @endif
                            </td>
                            {{-- END --}}
                            <td>
                                @if($act->end_date_time)
                                    {{ \Carbon\Carbon::parse($act->end_date_time)->format('M d, Y h:i A') }}
                                @else
                                    -
                                @endif
                            </td>
                            {{-- PORT --}}
                            <td>
                                {{ $act->port_location ?? '-' }}
                            </td>
                            {{-- REMARKS --}}
                            <td>
                                {{ $act->remarks ?? '-' }}
                            </td>
                            {{-- TOTAL HOURS --}}
                            <td>
                                {{ number_format($act->total_hours, 2) }}
                            </td>
                        </tr>
                    @endforeach
                @endforeach
                {{-- TOTAL --}}
                <tr>
                    <td colspan="5" align="right">
                        <strong>Total All</strong>
                    </td>
                    <td>
                        <strong>
                            {{ number_format($details->sum('total_hours'), 2) }}
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
<br><br>
<h3 style="margin-bottom:10px;">FUEL ROB MONITORING</h3>
<table width="100%" border="1" cellspacing="0" cellpadding="5">
    <tr>
        <td><b>Vessel Name:</b></td>
        <td>{{ $voyage->vessel->vessel_name ?? '-' }}</td>

        <td><b>Voyage Number:</b></td>
        <td>{{ $voyage->voyage_no }}</td>
    </tr>
    <tr>
        <td><b>Period Covered:</b></td>
        <td colspan="3">
            {{ \Carbon\Carbon::parse($voyage->date_created)->format('M d, Y') }}
            -
            {{ $voyage->date_completed 
                ? \Carbon\Carbon::parse($voyage->date_completed)->format('M d, Y')
                : 'Present'
            }}
        </td>
    </tr>
</table>
<br>
<table width="100%" border="1" cellspacing="0" cellpadding="5">
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
        @php
            $overallConsumption = 0;
        @endphp
        @foreach($voyage->fuelMonitorings as $fuel)
        @php
            $overallConsumption += $fuel->total_consumed;
        @endphp
        <tr>
            <td>
                {{ \Carbon\Carbon::parse($fuel->created_at)->format('M d, Y') }}
            </td>
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
<br>
<table width="50%" border="1" cellspacing="0" cellpadding="5">
    <tr>
        <td><b>Total Fuel Consumed</b></td>
        <td>{{ number_format($overallConsumption, 2) }}</td>
    </tr>
    <tr>
        <td><b>Net Available Fuel</b></td>
        <td>{{ $voyage->fuel_rob }}</td>
    </tr>
</table>
<!--  SIGNATURE -->
<table class="signature" width="100%">
    <tr>
        <td>
            <br><br><br>
            ___________________________<br>
            <b>
                {{ $voyage->creator->name ?? '' }} {{ $voyage->creator->lastname ?? '' }}
            </b><br>
            Prepared By
        </td>
        <td>
            <br><br><br>
            ___________________________<br>
            Approved By
        </td>
    </tr>
</table>
<br>
<!--  FOOTER -->
<p style="text-align:right; font-size:10px;">
    Generated on {{ date('F d, Y') }}
</p>
<!--  DYNAMIC PAGE NUMBER -->
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("Arial", "normal");
    $size = 10;
    $pageText = "Page {PAGE_NUM} of {PAGE_COUNT}";
    $x = 500;
    $y = 35;
    $pdf->page_text($x, $y, $pageText, $font, $size);
}
</script>
</body>
</html>