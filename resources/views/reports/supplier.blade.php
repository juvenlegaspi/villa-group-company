<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        h2 {
            margin-bottom: 5px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .summary {
            margin-bottom: 20px;
        }

        .summary div {
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }

        /* 🔥 important for multi-page */
        tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<img src="{{ public_path('logo.jpg') }}" width="80">
<div class="header">
    <h2>📦 Supplier Dashboard Report</h2>
    <small>Date: {{ now()->format('F d, Y') }}</small>
</div>

<!-- SUMMARY -->
<div class="summary">
    <div><strong>Total Suppliers:</strong> {{ $metrics['totalSuppliers'] }}</div>
    <div><strong>Added Today:</strong> {{ $metrics['todaySuppliers'] }}</div>
    <div><strong>This Month:</strong> {{ $metrics['thisMonthSuppliers'] }}</div>
</div>

<!-- TOP PRODUCTS -->
<div>
    <strong>Top Products:</strong>
    <ul>
        @foreach($metrics['topProducts'] as $product)
            <li>{{ $product }}</li>
        @endforeach
    </ul>
</div>

<!-- TABLE -->
<h3 style="margin-top:20px;">📋 Supplier Details</h3>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Products</th>
            <th>Added By</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($suppliers as $index => $supplier)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $supplier->name }}</td>
            <td>{{ $supplier->products }}</td>
            <td>
                {{ $supplier->user 
                    ? $supplier->user->name . ' ' . $supplier->user->lastname 
                    : 'N/A' 
                }}
            </td>
            <td>{{ $supplier->created_at->format('Y-m-d') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>