<div class="modal" id="addPropertyModal" tabindex="-1" role="dialog" aria-labelledby="addPropertyTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" action="{{ route($propertyRoutes.'store') }}" class="modal-content">
            @csrf
            <header class="modal-header">
                <div><p class="mb-1 text-xs font-bold uppercase tracking-widest text-teal-700">{{ $propertyCompany }}</p><h2 id="addPropertyTitle" class="modal-title">Add Property</h2><p class="mb-0 mt-1 text-xs text-slate-500">Your property code is generated when you save.</p></div>
                <button type="button" data-bs-dismiss="modal" aria-label="Close Add Property" class="ml-auto grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500">&times;</button>
            </header>
            <div class="modal-body space-y-4 bg-slate-50">
                @if($errors->propertyCreate->any())
                    <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->propertyCreate->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p class="mb-0 mt-2">Please reselect any attachments before saving again.</p></div>
                @endif
                @include('haruhay.properties.fields', ['property' => new \App\Models\HaruhayProperty])
            </div>
            <footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button class="inline-flex min-h-11 items-center rounded-xl border-0 bg-teal-800 px-6 text-sm font-extrabold text-white hover:bg-teal-900">Save Property</button></footer>
        </form>
    </div>
</div>
@if($errors->propertyCreate->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('addPropertyModal')).show();
            });
        </script>
    @endpush
@endif
