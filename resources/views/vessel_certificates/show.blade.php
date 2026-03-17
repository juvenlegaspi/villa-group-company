@extends('layouts.app')

@section('content')

<div class="container">

<div class="d-flex justify-content-between align-items-center">

<h4>Vessel Certificate Monitoring</h4>

<a href="{{ route('vessel-certificates.index') }}" class="btn btn-secondary btn-sm">
← Back
</a>

</div>

<div class="mb-3">

<strong>VESSEL NAME:</strong> {{ $vessel->vessel_name }} <br>
<strong>DATE TODAY:</strong> {{ $today->format('F d, Y') }}

</div>

<a href="{{ route('vessel-certificates.add',$vessel->id) }}" class="btn btn-primary btn-sm mb-3">
Add Certificate
</a>
<form method="GET" class="row mb-3">

<div class="col-md-4">
<input type="text"
name="search"
class="form-control"
placeholder="Search Certificate"
value="{{ request('search') }}">
</div>

<div class="col-md-3">
<select name="filter" class="form-control">
<option value="">All Certificates</option>
<option value="expiring" {{ request('filter')=='expiring'?'selected':'' }}>
Expiring Soon (30 days)
</option>
<option value="expired" {{ request('filter')=='expired'?'selected':'' }}>
Expired
</option>
</select>
</div>

<div class="col-md-3">
<button class="btn btn-primary btn-sm">Search</button>
<a href="{{ route('vessel-certificates.show',$vessel->id) }}"
class="btn btn-secondary btn-sm">Reset</a>
</div>

</form>
<table class="table table-bordered table-sm">

<thead class="table-dark">

<tr>
<th>Certificates</th>
<th>Created date</th>
<th>Issuance Date</th>
<th>Expiration Date</th>
<th>Days to Expiry</th>
<th>Status</th>
<th>Action</th>
</tr>

</thead>

<tbody>

@foreach($certificates as $c)

@php

$expiry = \Carbon\Carbon::parse($c->expiry_date);
$days = now()->diffInDays($expiry, false);

$status = 'VALID';
$color = 'success';

if($days < 0){
$status = 'EXPIRED';
$color = 'danger';
}

elseif($days <= 30){
$status = 'URGENT';
$color = 'warning';
}

@endphp

<tr class=" @if($days < 0) table-danger @elseif($days <= 30) table-warning @endif ">

<td>{{ $c->certificate_name }}</td>

<td>{{ $c->created_at ?? '-' }}</td>

<td>{{ $c->issue_date }}</td>

<td>{{ $c->expiry_date }}</td>

<td>{{ intval($days) }}</td>

<td>

<span class="badge bg-{{ $color }} px-3 py-2">
{{ $status }}
</span>

</td>

<td>

<a href="{{ route('vessel-certificates.edit',$c->id) }}"
class="btn btn-primary btn-sm">
Edit
</a>

</td>

</tr>

@endforeach

</tbody>

</table>

</div>

@endsection