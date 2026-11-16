<div class="modal" id="updatePropertyModal" tabindex="-1" role="dialog" aria-labelledby="updatePropertyTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route($propertyRoutes.'update', $property) }}" class="modal-content">
            @csrf @method('PUT')
            <header class="modal-header">
                <div><p class="mb-1 text-xs font-bold uppercase tracking-widest text-teal-700">{{ $property->code }}</p><h2 id="updatePropertyTitle" class="modal-title">Update Property</h2></div>
                <button type="button" data-bs-dismiss="modal" aria-label="Close Update Property" class="ml-auto grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xl text-slate-500">&times;</button>
            </header>
            <div class="modal-body space-y-4 bg-slate-50">
                @if($errors->getBag('default')->any())
                    <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please check the following:</strong><ul class="mb-0 mt-2">@foreach($errors->getBag('default')->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @include('haruhay.properties.fields')
            </div>
            <footer class="modal-footer"><button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button><button class="inline-flex min-h-11 items-center rounded-xl border-0 bg-teal-800 px-6 text-sm font-extrabold text-white hover:bg-teal-900">Save Changes</button></footer>
        </form>
    </div>
</div>
@if($errors->getBag('default')->any())
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('updatePropertyModal')).show();
            });
        </script>
    @endpush
@endif
