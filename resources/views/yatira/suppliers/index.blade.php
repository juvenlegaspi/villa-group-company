@extends('layouts.app')

@section('title', 'Supplier Management | Yatira')

@section('content')

<style>
    .supplier-filter-grid { display:grid; grid-template-columns:minmax(240px,1fr) 190px auto auto; gap:12px; align-items:end; margin-bottom:20px; padding:18px; border:1px solid #e2e8f0; border-radius:18px; background:#f8fafc; }
    .supplier-filter-grid label { display:block; margin-bottom:7px; color:#475569; font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .supplier-table-card { border:1px solid #e2e8f0 !important; border-radius:24px !important; box-shadow:0 14px 36px rgba(15,23,42,.07) !important; }
    .supplier-table-card thead th { padding:14px 16px; background:#f8fafc; color:#64748b; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; }
    .supplier-table-card tbody td { padding:15px 16px; border-color:#f1f5f9; }
    .supplier-table-card tbody tr { transition:background .15s ease; }
    .supplier-table-card tbody tr:hover { background:#fffbeb; }
    @media(max-width:860px){.supplier-filter-grid{grid-template-columns:1fr 1fr}.supplier-filter-grid .btn{width:100%}}
    @media(max-width:560px){.supplier-filter-grid{grid-template-columns:1fr}}
</style>

<div class="container-fluid px-2 px-sm-4 py-3">

    <!-- HEADER -->
    <header class="mb-5 overflow-hidden rounded-3xl bg-gradient-to-r from-villa-900 via-villa-800 to-amber-700 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="mb-1 text-xs font-extrabold uppercase tracking-[.18em] text-amber-200">Yatira Construction, Inc.</p><h1 class="mb-1 text-2xl font-black sm:text-3xl">Supplier Management</h1><p class="mb-0 text-sm text-blue-100">Manage supplier profiles, commercial terms, contact details and history.</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('yatira.applications') }}" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 text-sm font-bold text-white no-underline hover:bg-white/20"><i class="bi bi-grid"></i>Applications</a>@if(auth()->user()->hasPermission('yatira.suppliers.manage'))<button class="inline-flex min-h-11 items-center gap-2 rounded-xl border-0 bg-white px-4 text-sm font-black text-villa-900 shadow-lg" data-bs-toggle="modal" data-bs-target="#addSupplierModal"><i class="bi bi-plus-lg"></i>Add Supplier</button>@endif</div></div>
    </header>

    <!-- TABLE -->
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Please review the highlighted fields.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="card supplier-table-card overflow-hidden">
        <div class="card-body">
          <form method="GET" action="{{ route('suppliers.index') }}" class="supplier-filter-grid">

    <!-- 🔍 Search Input -->
    <div>
        <label>Search</label>
        <input 
            type="text" 
            name="search" 
            placeholder="Supplier, product, or user..." 
            value="{{ request('search') }}"
            class="form-control"
        >
    </div>

    <!-- 📅 Date -->
    <div>
        <label>Date</label>
        <input 
            type="date" 
            name="date" 
            value="{{ request('date') }}"
            class="form-control"
        >
    </div>

    <!-- 🔎 Search Button -->
    <div>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Search
        </button>
    </div>

    <!-- 🔄 Reset Button -->
    <div>
        <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Reset</a>
    </div>

</form>
            <div class="table-responsive">
            <table class="table table-hover align-middle text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Business Type</th>
                        <th>Products</th>
                        <th>Terms</th>
                        <th>Date</th>
                        <th>Added By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                  @foreach($suppliers as $supplier)
                  <tr>
                      <td><strong>{{ $supplier->name }}</strong></td>
                      <td>{{ $supplier->contact_person }}</td>
                      <td>{{ $supplier->business_type }}</td>
                      <td>{{ $supplier->products }}</td>

                      <td>
                          <span class="badge bg-primary">
                              {{ $supplier->credit_term ?? 'N/A' }} days
                          </span>
                      </td>

                      <td>{{ optional($supplier->created_at)->format('M d, Y') }}</td>

                      <td>
                          <span class="badge bg-info">
                              {{ $supplier->added_by_name }}
                          </span>
                      </td>

                      <td><span class="badge {{ $supplier->status ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->status ? 'Active' : 'Inactive' }}</span></td>

                      <td>
                        @php
                        $user = auth()->user();
                        @endphp
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('suppliers.show', $supplier) }}">View</a>
                    @if($user->hasPermission('yatira.suppliers.manage'))
                        <button class="btn btn-sm editBtn"
                            onclick="editSupplier(this)"
                            data-id="{{ $supplier->id }}"
                            data-name="{{ $supplier->name }}"
                            data-business_type="{{ $supplier->business_type }}"
                            data-tin="{{ $supplier->tin }}"
                            data-address="{{ $supplier->address }}"
                            data-products="{{ $supplier->products }}"
                            data-tax_type="{{ $supplier->tax_type }}"
                            data-lead_time="{{ $supplier->lead_time }}"
                            data-credit_term="{{ $supplier->credit_term }}"
                            data-limit_advances="{{ $supplier->limit_advances }}"
                            data-contact_person="{{ $supplier->contact_person }}"
                            data-telephone="{{ $supplier->telephone }}"
                            data-mobile="{{ $supplier->mobile }}"
                            data-email="{{ $supplier->email }}"
                            data-status="{{ $supplier->status }}">
                            Edit
                        </button>
                    @endif
                  </tr>
                  @endforeach
                </tbody>
            </table>
            </div>
            <div class="mt-3">
              {{ $suppliers->links() }}
            </div>
        </div>
    </div>

</div>

<!-- ================= MODAL ================= -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form method="POST" action="{{ route('suppliers.store') }}" novalidate>
        @csrf
        <input type="hidden" name="_form_context" value="create_supplier">

        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">

          @if(old('_form_context') === 'create_supplier' && $errors->any())
            <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
              <i class="bi bi-exclamation-circle-fill mt-1"></i>
              <div><strong>Supplier was not saved.</strong><div class="small">Review the highlighted fields below.</div></div>
            </div>
          @endif

          <!-- SUPPLIER INFO -->
          <h6 class="border-bottom pb-2 mb-3">Supplier Information</h6>

          <div class="mb-3">
            <label for="add_supplier_name" class="form-label fw-semibold">Supplier Name <span class="text-danger">*</span></label>
            <input id="add_supplier_name" type="text" name="name" value="{{ old('name') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('name')) is-invalid @endif" placeholder="Enter supplier name" required>
            @if(old('_form_context') === 'create_supplier') @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="add_supplier_business_type" class="form-label fw-semibold">Business Type <span class="text-danger">*</span></label>
              <input id="add_supplier_business_type" type="text" name="business_type" value="{{ old('business_type') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('business_type')) is-invalid @endif" placeholder="e.g. Corporation" required>
              @if(old('_form_context') === 'create_supplier') @error('business_type')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="col-md-6">
              <label for="add_supplier_tin" class="form-label fw-semibold">TIN <span class="text-danger">*</span></label>
              <input id="add_supplier_tin" type="text" name="tin" value="{{ old('tin') }}" class="form-control @if(old('_form_context') === 'create_supplier' && ($errors->has('tin') || $errors->has('normalized_tin'))) is-invalid @endif" placeholder="Enter tax identification number" required>
              @if(old('_form_context') === 'create_supplier' && ($errors->has('tin') || $errors->has('normalized_tin')))<div class="invalid-feedback">{{ $errors->first('tin') ?: $errors->first('normalized_tin') }}</div>@endif
            </div>
          </div>

          <div class="mb-3">
            <label for="add_supplier_address" class="form-label fw-semibold">Address <span class="text-muted fw-normal">(optional)</span></label>
            <input id="add_supplier_address" type="text" name="address" value="{{ old('address') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('address')) is-invalid @endif" placeholder="Enter business address">
            @if(old('_form_context') === 'create_supplier') @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
          </div>

          <div class="mb-3">
            <label for="add_supplier_products" class="form-label fw-semibold">Products or Services <span class="text-danger">*</span></label>
            <textarea id="add_supplier_products" name="products" rows="3" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('products')) is-invalid @endif" placeholder="Describe the products or services supplied" required>{{ old('products') }}</textarea>
            @if(old('_form_context') === 'create_supplier') @error('products')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
          </div>

          <!--<div class="row">
            <div class="col-md-6">
              <input type="text" name="tax_type" class="form-control mb-2 required" placeholder="Tax Type">
            </div>
            <div class="col-md-6">
              <input type="number" name="lead_time" class="form-control mb-2 required" placeholder="Lead Time">
            </div>
          </div>-->

          <div class="row g-3">
            <div class="col-md-6">
              <label for="add_supplier_credit_term" class="form-label fw-semibold">Credit Term <span class="text-muted fw-normal">(optional)</span></label>
              <select id="add_supplier_credit_term" name="credit_term" class="form-select @if(old('_form_context') === 'create_supplier' && $errors->has('credit_term')) is-invalid @endif">
                <option value="">Credit Term</option>
                @foreach([15, 30, 35, 40, 45, 50, 55, 60] as $days)<option value="{{ $days }}" @selected((string) old('credit_term') === (string) $days)>{{ $days }} Days</option>@endforeach
              </select>
              @if(old('_form_context') === 'create_supplier') @error('credit_term')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="col-md-6">
              <label for="add_supplier_limit" class="form-label fw-semibold">Advance Limit <span class="text-muted fw-normal">(optional)</span></label>
              <input id="add_supplier_limit" type="number" min="0" step="0.01" name="limit_advances" value="{{ old('limit_advances') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('limit_advances')) is-invalid @endif" placeholder="0.00">
              @if(old('_form_context') === 'create_supplier') @error('limit_advances')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
          </div>

          <!-- CONTACT INFO -->
          <h6 class="border-bottom pb-2 mt-4 mb-3">Contact Information</h6>

          <div class="mb-3">
            <label for="add_supplier_contact_person" class="form-label fw-semibold">Contact Person <span class="text-danger">*</span></label>
            <input id="add_supplier_contact_person" type="text" name="contact_person" value="{{ old('contact_person') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('contact_person')) is-invalid @endif" placeholder="Enter contact person's name" required>
            @if(old('_form_context') === 'create_supplier') @error('contact_person')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
          </div>

          <div class="rounded-3 border bg-light p-3">
            <p class="small text-secondary mb-3"><i class="bi bi-info-circle me-1"></i>Provide at least one contact detail: telephone, mobile number, or email.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="add_supplier_telephone" class="form-label fw-semibold">Telephone</label>
              <input id="add_supplier_telephone" type="text" name="telephone" value="{{ old('telephone') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('telephone')) is-invalid @endif" placeholder="Enter telephone number">
              @if(old('_form_context') === 'create_supplier') @error('telephone')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
            <div class="col-md-6">
              <label for="add_supplier_mobile" class="form-label fw-semibold">Mobile</label>
              <input id="add_supplier_mobile" type="text" name="mobile" value="{{ old('mobile') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('mobile')) is-invalid @endif" placeholder="Enter mobile number">
              @if(old('_form_context') === 'create_supplier') @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
          </div>
          <div class="mt-3">
            <label for="add_supplier_email" class="form-label fw-semibold">Email</label>
            <input id="add_supplier_email" type="email" name="email" value="{{ old('email') }}" class="form-control @if(old('_form_context') === 'create_supplier' && $errors->has('email')) is-invalid @endif" placeholder="supplier@example.com">
            @if(old('_form_context') === 'create_supplier') @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
          </div>
          @if(old('_form_context') === 'create_supplier') @error('contact_details')<div class="text-danger small fw-semibold mt-2"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>@enderror @endif
          </div>
          <input type="hidden" name="status" value="1">
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="saveBtn" class="btn btn-primary">Save Supplier</button>
        </div>

      </form>

    </div>
  </div>
</div>

<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Edit Supplier</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="editForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="_form_context" value="edit_supplier">
                <input type="hidden" name="_supplier_id" id="editSupplierId">

                <input type="hidden" id="edit_id">

                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">

                    <!-- BASIC INFO -->
                    <h6 class="border-bottom pb-2">Supplier Information</h6>

                    <label>Name</label>
                    <input type="text" id="edit_name" name="name" class="form-control mb-2">

                    <div class="row">
                        <div class="col-md-6">
                            <label>Business Type</label>
                            <input type="text" id="edit_business_type" name="business_type" class="form-control mb-2">
                        </div>
                        <div class="col-md-6">
                            <label>TIN</label>
                            <input type="text" id="edit_tin" name="tin" class="form-control mb-2">
                        </div>
                    </div>

                    <label>Address</label>
                    <input type="text" id="edit_address" name="address" class="form-control mb-2">

                    <label>Products</label>
                    <textarea id="edit_products" name="products" class="form-control mb-2"></textarea>

                    <!-- TERMS -->
                    <h6 class="border-bottom pb-2 mt-3">Terms & Details</h6>

                    <div class="row">
                        <div class="col-md-6">
                            <label>Tax Type</label>
                            <input type="text" id="edit_tax_type" name="tax_type" class="form-control mb-2">
                        </div>
                        <div class="col-md-6">
                            <label>Lead Time</label>
                            <input type="number" id="edit_lead_time" name="lead_time" class="form-control mb-2">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label>Credit Term</label>
                            <select id="edit_credit_term" name="credit_term" class="form-control mb-2">
                               <option value="">Credit Term</option>
                                <option value="15">15 Days</option>
                                <option value="30">30 Days</option>
                                <option value="35">35 Days</option>
                                <option value="40">40 Days</option>
                                <option value="45">45 Days</option>
                                <option value="50">50 Days</option>
                                <option value="55">55 Days</option>
                                <option value="60">60 Days</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Limit Advances</label>
                            <input type="number" id="edit_limit_advances" name="limit_advances" class="form-control mb-2">
                        </div>
                    </div>

                    <!-- CONTACT -->
                    <h6 class="border-bottom pb-2 mt-3">Contact Information</h6>

                    <label>Contact Person</label>
                    <input type="text" id="edit_contact_person" name="contact_person" class="form-control mb-2">

                    <div class="row">
                        <div class="col-md-6">
                            <label>Telephone</label>
                            <input type="text" id="edit_telephone" name="telephone" class="form-control mb-2">
                        </div>
                        <div class="col-md-6">
                            <label>Mobile</label>
                            <input type="text" id="edit_mobile" name="mobile" class="form-control mb-2">
                        </div>
                    </div>

                    <label>Email</label>
                    <input type="email" id="edit_email" name="email" class="form-control mb-2">

                    <!-- STATUS -->
                    <h6 class="border-bottom pb-2 mt-3">Status</h6>

                    <label>Status</label>
                    <select id="edit_status" name="status" class="form-control mb-2">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                </div>

            </form>

        </div>
    </div>
</div>

<!-- ================= TOAST ================= -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 9999">
  <div id="liveToast" class="toast text-bg-success border-0">
    <div class="d-flex">
      <div class="toast-body">
        Supplier saved successfully!
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
function editSupplier(btn){

    let id = btn.dataset.id;

   document.getElementById('editForm').action = `/yatira/suppliers/${id}`;
   document.getElementById('editSupplierId').value = id;

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = btn.dataset.name;
    document.getElementById('edit_business_type').value = btn.dataset.business_type;
    document.getElementById('edit_tin').value = btn.dataset.tin;
    document.getElementById('edit_address').value = btn.dataset.address;
    document.getElementById('edit_products').value = btn.dataset.products;
    document.getElementById('edit_tax_type').value = btn.dataset.tax_type;
    document.getElementById('edit_lead_time').value = btn.dataset.lead_time;
    document.getElementById('edit_credit_term').value = btn.dataset.credit_term;
    document.getElementById('edit_limit_advances').value = btn.dataset.limit_advances;
    document.getElementById('edit_contact_person').value = btn.dataset.contact_person;
    document.getElementById('edit_telephone').value = btn.dataset.telephone;
    document.getElementById('edit_mobile').value = btn.dataset.mobile;
    document.getElementById('edit_email').value = btn.dataset.email;
    document.getElementById('edit_status').value = btn.dataset.status;

    new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
}
</script>

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (@json(old('_form_context')) === 'edit_supplier') {
        const button = document.querySelector(`[data-id="${@json(old('_supplier_id'))}"]`);
        if (button) { editSupplier(button); return; }
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('addSupplierModal')).show();
});
</script>
@endif

@endsection
