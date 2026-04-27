<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    /**
     * Display list
     */
    public function index()
    {
        $suppliers = Supplier::with('user:id,name,lastname')
            ->latest()
            ->paginate(10);

        // add full name (added_by)
        $suppliers->getCollection()->transform(function ($supplier) {
            $supplier->added_by_name = $supplier->user
                ? $supplier->user->name . ' ' . $supplier->user->lastname
                : 'N/A';

            return $supplier;
        });

        return view('yatira.suppliers.index', compact('suppliers'));
    }

    /**
     * Store new supplier
     */
    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $data['added_by'] = Auth::id();

        $supplier = Supplier::create($data);

        return redirect()->back()->with('success', 'Supplier added successfully!');
    }

    /**
     * Update supplier
     */
    public function update(Request $request, $id)
{
    $supplier = Supplier::findOrFail($id);

    $supplier->update($this->validatedData($request));

    $supplier->load('user');

    return redirect()->back()->with('success', 'Supplier updated!');
}

    /**
     * ✅ REUSABLE VALIDATION
     */
    private function validatedData(Request $request)
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'products' => 'nullable|string',
            'tax_type' => 'nullable|string|max:255',
            'lead_time' => 'nullable|integer',
            'credit_term' => 'nullable|string|max:50',
            'limit_advances' => 'nullable|numeric',
            'contact_person' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'required|boolean'
        ]);
    }

    /**
     * ✅ REUSABLE RESPONSE (DRY)
     */
    private function responseWithUser($supplier)
    {
        $supplier->load('user');

        $supplier->added_by_name = $supplier->user
            ? $supplier->user->name . ' ' . $supplier->user->lastname
            : 'N/A';

        return response()->json([
            'success' => true,
            'data' => $supplier
        ]);
    }
}