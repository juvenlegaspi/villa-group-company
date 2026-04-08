<!DOCTYPE html>
    <html>
        <head>
            <meta charset="utf-8">
                <style>
                    body{
                        font-family: Arial;
                        font-size:12px;
                    }
                    h2{
                        text-align:center;
                    }
                    table{
                        width:100%;
                        border-collapse:collapse;
                    }
                    table, th, td{
                        border:1px solid black;
                    }
                    th, td{
                        padding:6px;
                    }
                    .info-table td{
                        border:none;
                        padding:3px;
                    }
                    .signature{
                        margin-top:40px;
                    }
            </style>
        </head>
        <body>
            <h2>Voyage Log Report</h2>
            <br>
            <table class="info-table">
                <tr>
                    <td><b>Voyage ID:</b> VL-{{ str_pad($voyage->voyage_id,5,'0',STR_PAD_LEFT) }}</td>
                    <td><b>Voyage No:</b> {{ $voyage->voyage_no }}</td>
                </tr>
                <tr>
                    <td><b>Port Location:</b> {{ $voyage->port_location }}</td>
                    <td><b>Date Started:</b> {{ \Carbon\Carbon::parse($voyage->date_created)->format('m-d-Y') }}</td>
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
                        <td><b>Date Completed:</b> {{ \Carbon\Carbon::parse($voyage->date_completed)->format('m-d-Y') }}</td>
                        <td></td>
                    </tr>
                @endif
            </table>
            <br><br>
            <h3>Activity Timeline</h3>
            <table>
                <tr>
                    <th>Status</th>
                    <th>Activity</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Total Hours</th>
                    <th>Remarks</th>
                </tr>
                @foreach($voyage->details as $detail)
                    <tr>
                        <td>{{ $detail->voyage_status }}</td>
                        <td>{{ $detail->activity }}</td>
                        <td>
                            @if($detail->date_time_started)
                                {{ \Carbon\Carbon::parse($detail->date_time_started)->format('m-d-Y h:i A') }}
                            @endif
                        </td>
                        <td>
                            @if($detail->date_time_ended)
                                {{ \Carbon\Carbon::parse($detail->date_time_ended)->format('m-d-Y h:i A') }}
                            @endif
                        </td>
                        <td>{{ $detail->total_hours }}</td>
                        <td>{{ $detail->remarks }}</td>
                    </tr>
                @endforeach
            </table>
            <div class="signature">
                <br><br><br>
                <table style="width:100%; border:0;">
                    <tr>
                        <td style="border:0; width:50%;">Prepared By:
                            <br><br><br>
                            _____________________________
                            <br>
                            {{ $voyage->creator->name }} {{ $voyage->creator->lastname }}
                        </td>
                        <td style="border:0; width:50%; text-align:right;"> 
                            Date Generated:
                            <br><br>
                            {{ date('m-d-Y') }}
                        </td>
                    </tr>
                </table>
            </div>
        </body>
    </html>