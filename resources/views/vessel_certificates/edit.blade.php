@extends('layouts.app')

@section('content')
<div class="container">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Edit Certificate</h4>
        <a href="{{ route('vessel-certificates.show', $certificate->vessel_id) }}" 
           class="btn btn-secondary btn-sm">
            ← Back
        </a>
    </div>

    <div class="card shadow-sm border-0 p-4">
        <form action="{{ route('vessel-certificates.update', $certificate->id) }}" 
              method="POST" 
              enctype="multipart/form-data"> {{-- IMPORTANT NI --}}
            @csrf
            @method('PUT')

            <div class="row">

                {{-- Vessel --}}
                <div class="col-md-4 mb-3">
                    <label>Vessel</label>
                    <input type="text" class="form-control" 
                           value="{{ $certificate->vessel->vessel_name }}" readonly>
                    <input type="hidden" name="vessel_id" value="{{ $certificate->vessel_id }}">
                </div>

                {{-- Certificate Name --}}
                <div class="col-md-4 mb-3">
                    <label>Certificate Name</label>
                    <input type="text" name="certificate_name" class="form-control"
                           value="{{ $certificate->certificate_name }}" required>
                </div>

                {{-- Issue Date --}}
                <div class="col-md-4 mb-3">
                    <label>Issue Date</label>
                    <input type="date" name="issue_date" class="form-control"
                           value="{{ $certificate->issue_date }}" required>
                </div>

                {{-- Expiry Date --}}
                <div class="col-md-4 mb-3">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control"
                           value="{{ $certificate->expiry_date }}" required>
                </div>

                {{-- Remarks --}}
                <div class="col-md-8 mb-3">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-control"
                           value="{{ $certificate->remarks }}">
                </div>

                {{-- CURRENT FILE --}}
                <div class="col-md-6 mb-3">
                    <label>Current Document</label><br>

                    @if($certificate->document)
                        <a href="{{ asset('uploads/certificates/'.$certificate->document) }}" 
                           target="_blank"
                           class="btn btn-sm btn-outline-primary">
                            👁 View
                        </a>

                        <a href="{{ asset('uploads/certificates/'.$certificate->document) }}" 
                           download
                           class="btn btn-sm btn-outline-success">
                            ⬇ Download
                        </a>
                    @else
                        <span class="text-muted">No file uploaded</span>
                    @endif
                </div>

                {{-- UPLOAD NEW FILE --}}
                <div class="col-md-6 mb-3">
                    <label>Upload New Document</label>
                    <input type="file" name="document" class="form-control">
                    <small class="text-muted">
                        Allowed: PDF, Word, Excel, Images (Max 5MB)
                    </small>
                </div>

            </div>

            <button class="btn btn-success mt-2">
                💾 Update Certificate
            </button>
        </form>
    </div>

</div>
@endsection