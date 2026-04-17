@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Edit Certificate</h4>
        <a href="{{ route('vessel.certificates.show', $certificate->vessel_id) }}" class="btn btn-secondary btn-sm">
            Back
        </a>
    </div>

    <div class="card shadow-sm border-0 p-4">
        <form action="{{ route('vessel-certificates.update', $certificate->id) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Vessel</label>
                    <input type="text" class="form-control" value="{{ $certificate->vessel->vessel_name }}" readonly>
                    <input type="hidden" name="vessel_id" value="{{ $certificate->vessel_id }}">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Certificate Name</label>
                    <input
                        type="text"
                        name="certificate_name"
                        class="form-control"
                        value="{{ old('certificate_name', $certificate->certificate_name) }}"
                        required
                    >
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Issue Date</label>
                    <input
                        type="date"
                        name="issue_date"
                        class="form-control"
                        value="{{ old('issue_date', optional($certificate->issue_date)->format('Y-m-d')) }}"
                        required
                    >
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Expiry Date</label>
                    <input
                        type="date"
                        name="expiry_date"
                        class="form-control"
                        value="{{ old('expiry_date', optional($certificate->expiry_date)->format('Y-m-d')) }}"
                        required
                    >
                </div>

                <div class="col-md-8 mb-3">
                    <label class="form-label">Remarks</label>
                    <input
                        type="text"
                        name="remarks"
                        class="form-control"
                        value="{{ old('remarks', $certificate->remarks) }}"
                    >
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Current Document</label><br>

                    @if($certificate->document)
                        <a
                            href="{{ asset('uploads/certificates/' . $certificate->document) }}"
                            target="_blank"
                            class="btn btn-sm btn-outline-primary"
                        >
                            View
                        </a>

                        <a
                            href="{{ asset('uploads/certificates/' . $certificate->document) }}"
                            download
                            class="btn btn-sm btn-outline-success"
                        >
                            Download
                        </a>
                    @else
                        <span class="text-muted">No file uploaded.</span>
                    @endif
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Upload New Document</label>
                    <input type="file" name="document" class="form-control">
                    <small class="text-muted">
                        Allowed: PDF, Word, Excel, or image files up to 5 MB.
                    </small>
                </div>
            </div>

            <button class="btn btn-success mt-2">Update Certificate</button>
        </form>
    </div>
</div>
@endsection
