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
                    </tr>
                </thead>
                <tbody>
                    <!-- AJAX DATA -->
                    @foreach($suppliers as $supplier)
                    <tr>
                        <td><strong>{{ $supplier->name }}</strong></td>
                        <td>{{ $supplier->contact_person }}</td>
                        <td>{{ $supplier->business_type }}</td>
                        <td>{{ $supplier->products }}</td>

                        <td>
                            <span class="badge bg-primary">
                                {{ $supplier->credit_term ?? 'N/A' }}
                            </span>
                        </td>

                        <td>{{ $supplier->created_at }}</td>

                        <td>
                            <button class="btn btn-light btn-sm">⋮</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="saveBtn" class="btn btn-primary">Save Supplier</button>
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
        if(!input.value){
            input.classList.add('is-invalid');
            valid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });

    if(!valid){
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

        if(res.success){

            let d = res.data;

            let row = `
            <tr>
                <td>${d.name}</td>
                <td>${d.contact_person ?? ''}</td>
                <td>${d.business_type ?? ''}</td>
                <td>${d.products ?? ''}</td>
                <td><span class="badge bg-primary">${d.credit_term ?? ''} days</span></td>
                <td>${new Date().toLocaleDateString()}</td>
                <td><button class="btn btn-light btn-sm">⋮</button></td>
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
</script>

@endsection