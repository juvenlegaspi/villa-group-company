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
                        <input type="text" name="cargo_volume" class="form-control" placeholder="Enter volume">
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
                    <div class="col-md-6">
                        <label class="form-label">Port Location</label>
                        <select name="port_location" class="form-select">
                            <option value="">Select Port</option>
                            <option value="CEBU">Cebu</option>
                            <option value="MANILA">Manila</option>
                            <option value="DAVAO">Davao</option>
                            <option value="ILOILO">Iloilo</option>
                        </select>
                        @error('port_location')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Voyage Number -->
                    <div class="col-md-6">
                        <label class="form-label">Voyage Number</label>
                        <input type="text" name="voyage_no" class="form-control" placeholder="Enter voyage number">
                        @error('voyage_no')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <!-- Fuel -->
                    <div class="col-md-6">
                        <label class="form-label">Fuel ROB</label>
                        <div class="input-group">
                            <input type="number" name="fuel_rob" class="form-control"
                                   placeholder="Enter fuel">
                            <span class="input-group-text">Liters</span>
                            @error('fuel_rob')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
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