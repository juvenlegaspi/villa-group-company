<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    /**
     * Display list
     */
    public function index(Request $request)
    {
        $this->authorizeSupplierAccess();

        $search = $request->search;
        $date = $request->date;

        $suppliers = Supplier::with('user:id,name,lastname')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('products', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search) {
                            $u->where('name', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%");
                        });
                });
            })
            ->when($date, function ($query) use ($date) {
                $query->whereDate('created_at', $date);
            })
            ->orderBy('name', 'asc')
            ->paginate(10)
            ->withQueryString();

        $suppliers->getCollection()->transform(function ($supplier) {
            $supplier->added_by_name = $supplier->user
                ? $supplier->user->name.' '.$supplier->user->lastname
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
        $this->authorizeSupplierAccess();

        $data = $this->validatedData($request);
        $data['added_by'] = Auth::id();

        Supplier::create($data);

        return redirect()->back()->with('success', 'Supplier added successfully!');
    }

    /**
     * Update supplier
     */
    public function update(Request $request, $id)
    {
        $this->authorizeSupplierAccess();

        $supplier = Supplier::findOrFail($id);
        $supplier->update($this->validatedData($request));
        $supplier->load('user');

        return redirect()->back()->with('success', 'Supplier updated!');
    }

    /**
     * Reusable validation
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
            'status' => 'required|boolean',
        ]);
    }

    /**
     * Reusable response
     */
    private function responseWithUser($supplier)
    {
        $supplier->load('user');

        $supplier->added_by_name = $supplier->user
            ? $supplier->user->name.' '.$supplier->user->lastname
            : 'N/A';

        return response()->json([
            'success' => true,
            'data' => $supplier,
        ]);
    }

    private function authorizeSupplierAccess(): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $user->loadMissing('division');

        abort_unless(
            strcasecmp((string) $user->division?->name, 'yatira') === 0,
            403
        );
    }
}
