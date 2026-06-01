@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <!-- HEADER -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-0 text-primary">🚢 Add Voyage</h4>
                <small class="text-muted">Create new voyage record</small>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-light border">
                ← Back
            </a>
        </div>
    </div>
    <!-- FORM -->
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ url('/shipping/voyage-logs/store') }}">
                @csrf
                <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
                <div class="row g-3">
                    <!-- Voyage ID -->
                    <div class="col-md-6">
                        <label class="form-label">Voyage ID</label>
                        <input type="text" class="form-control bg-light" value="VL-NEW" readonly>
                    </div>
                    <!-- Cargo -->
                    <div class="col-md-6">
                        <label class="form-label">Cargo Type</label>
                        <input type="text" name="cargo_type" class="form-control">
                        @error('cargo_type')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Volume -->
                    <div class="col-md-6">
                        <label class="form-label">Cargo Volume</label>
                        <div class="input-group">

                        <input type="text"
                            name="cargo_volume"
                            class="form-control"
                            placeholder="Enter volume">

                        <select name="cargo_unit"
                                class="form-select"
                                style="max-width:120px;"
                                required>
                            <option value="">-- SELECT UNIT --</option>
                            <option value="Crates">Crates</option>
                            <option value="MT">MT</option>
                            <option value="LB">LB</option>
                            <option value="CBM">CBM</option>
                            <option value="L">L</option>
                            <option value="BBL">BBL</option>
                            <option value="Bushel">Bushel</option>
                            <option value="Bag/Sacks">Bag/Sacks</option>
                            <option value="Piece/Unit">Piece/Unit</option>
                        </select>
                    </div>
                        @error('cargo_volume')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Crew -->
                    <div class="col-md-6">
                        <label class="form-label">Crew on Board</label>
                        <input type="number" name="crew_on_board" class="form-control" placeholder="Enter number of crew">
                        @error('crew_on_board')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Port -->
                    <div class="col-md-3">
                        <label class="form-label">Port Origin</label>
                        <select name="port_id" class="form-control" required>
                            <option value="">-- SELECT PORT --</option>
                            @foreach($ports as $port)
                                <option value="{{ $port->id }}">
                                    {{ $port->port_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('port_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port Destination</label>

                        <select name="port_destination_id" class="form-control" required>
                            <option value="">-- SELECT PORT DESTINATION --</option>

                            @foreach($ports as $port)
                                <option value="{{ $port->id }}">
                                    {{ $port->port_name }}
                                </option>
                            @endforeach
                        </select>

                        @error('port_destination_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Voyage Number -->
                    <div class="col-md-6">
                        <label class="form-label">Current Location</label>

                        <select name="current_location_id" class="form-control" required>
                            <option value="">-- SELECT PORT --</option>

                            @foreach($ports as $port)
                                <option value="{{ $port->id }}">
                                    {{ $port->port_name }}
                                </option>
                            @endforeach
                        </select>

                        @error('current_location_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Voyage Number</label>

                        <input type="text"
                            name="voyage_no"
                            class="form-control"
                            placeholder="Enter voyage number">

                        @error('voyage_no')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Fuel -->
                    <div class="col-md-6">
                        <label class="form-label">Biginning Fuel ROB</label>
                        <div class="input-group">
                            <input type="number"
                                name="fuel_rob"
                                class="form-control"
                                value="{{ $lastVoyage ? preg_replace('/[^0-9.]/', '', $lastVoyage->fuel_rob) : '' }}"
                                placeholder="Enter fuel"
                                {{ $lastVoyage ? 'readonly' : '' }}>
                            <span class="input-group-text">Liters</span>
                            @error('fuel_rob')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>
                    <!-- ETA NEXT PORT -->
                    <div class="col-md-6">
                        <label class="form-label">ETA Next Port</label>

                        <input type="datetime-local"
                            name="arrival_date"
                            class="form-control">

                        @error('arrival_date')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <!-- BUTTONS -->
                <div class="mt-4 d-flex justify-content-end">
                    <a href="{{ url()->previous() }}" class="btn btn-light border me-2">
                        Cancel
                    </a>
                    <button class="btn btn-primary px-4 shadow-sm">
                        💾 Save Voyage
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- STYLE -->
<style>
.form-control {
    border-radius: 8px;
}
.card {
    border-radius: 12px;
}
label {
    font-weight: 600;
    margin-bottom: 4px;
}
input:focus {
    box-shadow: none;
    border-color: #0d6efd;
}
</style>
<script>
document.querySelectorAll('input[type="text"]').forEach(input => {
    input.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
});
</script>
@endsection