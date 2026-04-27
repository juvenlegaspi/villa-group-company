@extends('layouts.app')

@section('content')

<div class="container">

    <!-- HEADER -->
    <div class="d-flex justify-content-between mb-3">
        <div>
            <h4>Suppliers</h4>
            <small class="text-muted">Manage, review, and track supplier records.</small>
        </div>

        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            + Add Supplier
        </button>
    </div>

    <!-- TABLE -->
    <div class="card">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Business Type</th>
                        <th>Products</th>
                        <th>Terms</th>
                        <th>Date</th>
                        <th>Added By</th>
                        <th>Option</th>
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

                      <td>{{ $supplier->created_at }}</td>

                      <td>
                          <span class="badge bg-info">
                              {{ $supplier->added_by_name }}
                          </span>
                      </td>

                      <td>
                        @php
                            $user = auth()->user();
                        @endphp
                    @if(
                        $user->is_admin == 1 || 
                        ($user->role === 'manager')
                    )
                        <button class="btn btn-sm editBtn"
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
            <div class="mt-3">
              {{ $suppliers->links() }}
            </div>
        </div>
    </div>

</div>

<!-- ================= MODAL ================= -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Add Supplier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <form id="supplierForm">
        @csrf

        <div class="modal-body">

          <!-- SUPPLIER INFO -->
          <h6 class="border-bottom pb-2">Supplier Information</h6>

          <input type="text" name="name" class="form-control mb-2 required" placeholder="Name">

          <div class="row">
            <div class="col-md-6">
              <input type="text" name="business_type" class="form-control mb-2 required" placeholder="Business Type">
            </div>
            <div class="col-md-6">
              <input type="text" name="tin" class="form-control mb-2 required" placeholder="TIN">
            </div>
          </div>

          <input type="text" name="address" class="form-control mb-2 required" placeholder="Address">
          <textarea name="products" class="form-control mb-2 required" placeholder="Products"></textarea>

          <div class="row">
            <div class="col-md-6">
              <input type="text" name="tax_type" class="form-control mb-2 required" placeholder="Tax Type">
            </div>
            <div class="col-md-6">
              <input type="number" name="lead_time" class="form-control mb-2 required" placeholder="Lead Time">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <select name="credit_term" class="form-control mb-2 required">
                <option value="">Credit Term</option>
                <option value="15">15 Days</option>
                <option value="30">30 Days</option>
                <option value="60">60 Days</option>
              </select>
            </div>
            <div class="col-md-6">
              <input type="number" name="limit_advances" class="form-control mb-2 required" placeholder="Limit Advances">
            </div>
          </div>

          <!-- CONTACT INFO -->
          <h6 class="border-bottom pb-2 mt-3">Contact Information</h6>

          <input type="text" name="contact_person" class="form-control mb-2 required" placeholder="Contact Person">

          <div class="row">
            <div class="col-md-6">
              <input type="text" name="telephone" class="form-control mb-2 required" placeholder="Telephone">
            </div>
            <div class="col-md-6">
              <input type="text" name="mobile" class="form-control mb-2 required" placeholder="Mobile">
            </div>
          </div>

          <input type="email" name="email" class="form-control mb-2" placeholder="Email">
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

<div class="modal fade" id="editSupplierModal">
    <div class="modal-dialog modal-lg" style="max-height: 90vh;">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Edit Supplier</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="editSupplierForm">
                @csrf
                @method('POST')

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
                                <option value="">Select</option>
                                <option value="15">15 Days</option>
                                <option value="30">30 Days</option>
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

<!-- ================= CSS ================= -->
<style>
.is-invalid {
    border: 1px solid red;
}
</style>

<!-- ================= AJAX ================= -->
<script>
document.getElementById('supplierForm').addEventListener('submit', function(e) {
    e.preventDefault();

    let form = this;
    let formData = new FormData(form);
    let valid = true;

    // VALIDATION
    form.querySelectorAll('.required').forEach(input => {
        if (!input.value) {
            input.classList.add('is-invalid');
            valid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });

    if (!valid) {
        alert('Please fill all required fields');
        return;
    }

    let btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerText = "Saving...";

    fetch("{{ route('suppliers.store') }}", {
        method: "POST",
        headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value
        },
        body: formData
    })
    .then(res => res.json())
    .then(res => {

        if (res.success) {

            let d = res.data;

            let row = `
            <tr>
                <td><strong>${d.name}</strong></td>
                <td>${d.contact_person ?? ''}</td>
                <td>${d.business_type ?? ''}</td>
                <td>${d.products ?? ''}</td>

                <td>
                    <span class="badge bg-primary">
                        ${d.credit_term ?? ''} days
                    </span>
                </td>

                <td>${d.created_at ?? ''}</td>

                <td>
                    <span class="badge bg-info">
                        ${d.added_by_name ?? 'N/A'}
                    </span>
                </td>

                <td>
                    <button class="btn btn-light btn-sm">Edit</button>
                </td>
            </tr>
            `;

            document.querySelector("tbody").insertAdjacentHTML('afterbegin', row);

            form.reset();

            let modal = bootstrap.Modal.getInstance(document.getElementById('addSupplierModal'));
            modal.hide();

            let toast = new bootstrap.Toast(document.getElementById('liveToast'));
            toast.show();

        } else {
            alert('Error saving');
        }

        btn.disabled = false;
        btn.innerText = "Save Supplier";
    })
    .catch(err => {
        console.log(err);
        alert('Server error');

        btn.disabled = false;
        btn.innerText = "Save Supplier";
    });
});

document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', function() {

        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_business_type').value = this.dataset.business_type;
        document.getElementById('edit_tin').value = this.dataset.tin;
        document.getElementById('edit_address').value = this.dataset.address;
        document.getElementById('edit_products').value = this.dataset.products;
        document.getElementById('edit_tax_type').value = this.dataset.tax_type;
        document.getElementById('edit_lead_time').value = this.dataset.lead_time;
        document.getElementById('edit_credit_term').value = this.dataset.credit_term;
        document.getElementById('edit_limit_advances').value = this.dataset.limit_advances;
        document.getElementById('edit_contact_person').value = this.dataset.contact_person;
        document.getElementById('edit_telephone').value = this.dataset.telephone;
        document.getElementById('edit_mobile').value = this.dataset.mobile;
        document.getElementById('edit_email').value = this.dataset.email;
        document.getElementById('edit_status').value = this.dataset.status;

        new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
    });
});

document.getElementById('editSupplierForm').addEventListener('submit', function(e){
    e.preventDefault();

    let id = document.getElementById('edit_id').value;
    let formData = new FormData(this);

    fetch(`/yatira/suppliers/${id}`, {
        method: 'POST', // Laravel PUT workaround
        headers: {
            'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value,
            'X-HTTP-Method-Override': 'PUT'
        },
        body: formData
    })
    .then(res => res.json())
    .then(res => {

        if(res.success){

            location.reload(); // simple refresh (safe)

        } else {
            alert('Update failed');
        }

    })
    .catch(err => {
        console.log(err);
        alert('Server error');
    });
});
</script>

@endsection