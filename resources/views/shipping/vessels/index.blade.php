@extends('layouts.app')

@section('content')

<h3 class="mb-4">Vessel List</h3>

<a href="/shipping/vessels/create" class="btn btn-primary mb-3">+ Add Vessel</a>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Vessel ID</th>
                    <th>Vessel Name</th>
                    <th>IMO Number</th>
                    <th>Call Sign</th>
                    <th>Vessel Type</th>
                    <th>DWT</th>
                    <th>Fuel Type</th>
                    <th>Service Speed</th>
                    <th>Charter Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vessels as $vessel)
                    <tr>
                        <td>
                        @php
                            $words = explode(' ', $vessel->vessel_name);
                            $initials = '';
                            foreach($words as $w){
                                $initials .= strtoupper(substr($w,0,1));
                            }
                            $code = 'VL'.$initials.'-'.str_pad($vessel->id,5,'0',STR_PAD_LEFT);
                        @endphp
                            <a href="/shipping/vessels/{{ $vessel->id }}" class="fw-bold text-decoration-none">
                                {{ $code }}
                            </a>
                        </td>
                        <td>{{ $vessel->vessel_name }}</td>
                        <td>{{ $vessel->imo_number }}</td>
                        <td>{{ $vessel->call_sign }}</td>
                        <td>{{ $vessel->vessel_type }}</td>
                        <td>{{ $vessel->dwt }}</td>
                        <td>{{ $vessel->fuel_type }}</td>
                        <td>{{ $vessel->service_speed }}</td>
                        <td>{{ $vessel->charter_type }}</td>
                        <td>{{ $vessel->vessel_status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">No vessels yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection