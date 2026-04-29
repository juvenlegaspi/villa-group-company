@extends('layouts.app')

@section('content')
@if(session('success'))
    <div style="background:#d1e7dd; padding:10px; margin-bottom:10px; border-radius:5px;">
        {{ session('success') }}
    </div>
@endif
<div class="container py-4">

    <h3 class="fw-bold mb-3">📦 Inventory Management</h3>

    <!-- 🔍 SEARCH + ADD -->
    <div style="display:flex; justify-content:space-between; margin-bottom:15px;">

        <!-- Search -->
        <form method="GET" action="{{ route('jmv.inventory.index') }}">
            <input 
                type="text" 
                name="search" 
                placeholder="Search item or unit..." 
                value="{{ request('search') }}"
                style="padding:8px; width:250px; border:1px solid #ccc; border-radius:5px;"
            >
            <button type="submit" 
                style="padding:8px 12px; background:#0d6efd; color:white; border:none; border-radius:5px;">
                Search
            </button>
        </form>

        <!-- Add Button -->
        <button onclick="openModal()" 
            style="padding:8px 15px; background:#198754; color:white; border:none; border-radius:5px;">
            + Add Item
        </button>

    </div>

    <!-- 📋 TABLE -->
    <div class="card shadow-sm">
        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Unit</th>
                        <th>Max</th>
                        <th>Min</th>
                        <th>Stock</th>
                        <th>Added By</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                    <tr>
                        <td>{{ $items->firstItem() + $index }}</td>
                        <td>{{ $item->item_name }}</td>
                        <td>{{ $item->unit }}</td>
                        <td>{{ $item->maximum_quantity }}</td>
                        <td>{{ $item->minimum_quantity }}</td>
                        <td>{{ $item->stock_on_hand }}</td>
                        <td>
                            {{ $item->user 
                                ? $item->user->name . ' ' . $item->user->lastname 
                                : 'N/A' 
                            }}
                        </td>
                        <td>{{ $item->created_at->format('Y-m-d') }}</td>
                        <td>
                            @if($item->status == 1)
                                <span style="color:green;">Active</span>
                            @else
                                <span style="color:red;">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center">No data found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            {{ $items->links() }}

        </div>
    </div>

</div>

<!-- 🧾 MODAL -->
<div id="itemModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
    <div style="background:white; width:400px; margin:10% auto; padding:20px; border-radius:10px;">

        <h5>Add Item</h5>

        <form method="POST" action="{{ route('jmv.inventory.store') }}">
            @csrf

            <input type="text" name="item_name" placeholder="Item Name" required class="form-control mb-2">
            <input type="text" name="unit" placeholder="Unit" required class="form-control mb-2">
            <input type="number" name="maximum_quantity" placeholder="Max Qty" required class="form-control mb-2">
            <input type="number" name="minimum_quantity" placeholder="Min Qty" required class="form-control mb-2">
            <input type="number" name="stock_on_hand" placeholder="Stock" required class="form-control mb-2">

            <button type="submit" class="btn btn-success">Save</button>
            <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
        </form>

    </div>
</div>

<script>
function openModal() {
    document.getElementById('itemModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('itemModal').style.display = 'none';
}
</script>

@endsection