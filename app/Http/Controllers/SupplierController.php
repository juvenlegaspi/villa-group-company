<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierAudit;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display list
     */
    public function index(Request $request)
    {
        $this->authorizeSupplierAccess('yatira.suppliers.view');

        $search = $request->search;
        $date = $request->date;
        $status = $request->input('status');

        $suppliers = Supplier::with(['user:id,name,lastname', 'updater:id,name,lastname'])
            ->where('division_id', $this->yatiraDivisionId())
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
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', (bool) $status))
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
        $this->authorizeSupplierAccess('yatira.suppliers.manage');

        $data = $this->validatedData($request);
        $data['added_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $data['division_id'] = $this->yatiraDivisionId();

        $supplier = DB::transaction(function () use ($data): Supplier {
            $supplier = Supplier::create($data);
            $this->recordAudit($supplier, 'created', ['supplier_name' => $supplier->name]);
            return $supplier;
        });

        return redirect()->back()->with('success', 'Supplier added successfully!');
    }

    /**
     * Update supplier
     */
    public function update(Request $request, $id)
    {
        $this->authorizeSupplierAccess('yatira.suppliers.manage');

        $supplier = Supplier::where('division_id', $this->yatiraDivisionId())->findOrFail($id);
        DB::transaction(function () use ($request, $supplier): void {
            $before = $supplier->only(['name', 'business_type', 'tin', 'address', 'products', 'tax_type', 'lead_time', 'credit_term', 'limit_advances', 'contact_person', 'telephone', 'mobile', 'email', 'status']);
            $supplier->update([...$this->validatedData($request, $supplier->id), 'updated_by' => Auth::id()]);
            $this->recordAudit($supplier, 'updated', ['before' => $before, 'after' => $supplier->fresh()->only(array_keys($before))]);
        });
        $supplier->load('user');

        return redirect()->back()->with('success', 'Supplier updated!');
    }

    /**
     * Reusable validation
     */
    public function show(Supplier $supplier)
    {
        $this->authorizeSupplierAccess('yatira.suppliers.view');
        abort_unless((int) $supplier->division_id === $this->yatiraDivisionId(), 404);
        $supplier->load(['user:id,name,lastname', 'updater:id,name,lastname', 'audits.user:id,name,lastname']);

        return view('yatira.suppliers.show', compact('supplier'));
    }

    private function validatedData(Request $request, ?int $supplierId = null): array
    {
        $normalizedTin = $this->normalizeTin((string) $request->input('tin'));
        $request->merge(['normalized_tin' => $normalizedTin]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'tin' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'tin')->ignore($supplierId)],
            'normalized_tin' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'normalized_tin')->ignore($supplierId)],
            'address' => 'nullable|string',
            'products' => 'required|string',
            'tax_type' => 'nullable|string|max:255',
            'lead_time' => 'nullable|integer|min:0',
            'credit_term' => 'nullable|integer|min:0',
            'limit_advances' => 'nullable|numeric|min:0',
            'contact_person' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'required|boolean',
        ], [
            'name.required' => 'Enter the supplier name.',
            'business_type.required' => 'Enter the supplier business type.',
            'tin.required' => 'Enter the supplier TIN.',
            'tin.unique' => 'A supplier with this TIN already exists.',
            'normalized_tin.required' => 'Enter a valid supplier TIN.',
            'normalized_tin.unique' => 'A supplier with this TIN already exists.',
            'products.required' => 'Enter the products or services supplied.',
            'contact_person.required' => 'Enter the supplier contact person.',
            'email.email' => 'Enter a valid email address.',
            'lead_time.integer' => 'Lead time must be a whole number.',
            'lead_time.min' => 'Lead time cannot be negative.',
            'credit_term.integer' => 'Credit term must be a whole number.',
            'credit_term.min' => 'Credit term cannot be negative.',
            'limit_advances.numeric' => 'Advance limit must be a valid amount.',
            'limit_advances.min' => 'Advance limit cannot be negative.',
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (blank($request->input('telephone'))
                && blank($request->input('mobile'))
                && blank($request->input('email'))) {
                $validator->errors()->add(
                    'contact_details',
                    'Provide at least one contact detail: telephone, mobile number, or email address.'
                );
            }
        });

        return $validator->validate();
    }

    private function authorizeSupplierAccess(string $permission): void
    {
        $user = auth()->user();

        if ($user->isSystemAdministrator() || $user->hasPermission($permission)) {
            return;
        }

        $user->loadMissing('division');

        abort(403, 'Your department or position is not authorized for this supplier action.');
    }

    private function yatiraDivisionId(): int
    {
        return (int) Division::whereRaw('LOWER(name) = ?', ['yatira'])->value('id');
    }

    private function normalizeTin(string $tin): string
    {
        return (string) preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($tin)));
    }

    private function recordAudit(Supplier $supplier, string $action, array $changes = []): void
    {
        SupplierAudit::create([
            'supplier_id' => $supplier->id, 'user_id' => auth()->id(), 'action' => $action,
            'changes' => $changes ?: null, 'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null,
            'created_at' => now(),
        ]);
    }
}
