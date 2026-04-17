@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0">Add Vessel Certificate</h3>
            <small class="text-muted">
                Vessel: <strong>{{ $vessel->vessel_name }}</strong>
            </small>
        </div>

        <a href="{{ route('vessel.certificates.show', $vessel->id) }}" class="btn btn-outline-secondary">
            Back
        </a>
    </div>

    <div class="card shadow border-0">
        <div class="card-body p-4">
            <form action="{{ route('vessel-certificates.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Vessel</label>
                        <input type="text" class="form-control bg-light" value="{{ $vessel->vessel_name }}" readonly>
                        <input type="hidden" name="vessel_id" value="{{ $vessel->id }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Certificate Name</label>
                        <input
                            type="text"
                            name="certificate_name"
                            class="form-control"
                            placeholder="Enter certificate name"
                            value="{{ old('certificate_name') }}"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Issue Date</label>
                        <input type="date" name="issue_date" class="form-control" value="{{ old('issue_date') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="{{ old('expiry_date') }}" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Remarks</label>
                        <input
                            type="text"
                            name="remarks"
                            class="form-control"
                            placeholder="Optional notes"
                            value="{{ old('remarks') }}"
                        >
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Upload Document</label>
                        <div class="border rounded p-3 bg-light text-center">
                            <input type="file" name="document" class="form-control">
                            <small class="text-muted d-block mt-2">
                                Allowed: PDF, Word, Excel, or image files up to 5 MB.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('vessel.certificates.show', $vessel->id) }}" class="btn btn-secondary px-4">
                        Cancel
                    </a>

                    <button class="btn btn-success px-4">
                        Save Certificate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
