<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index()
{
    $suppliers = Supplier::all();
    return view('yatira.suppliers.index', compact('suppliers'));
}
public function store(Request $request)
    {
        $supplier = Supplier::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $supplier
        ]);
    }
}
