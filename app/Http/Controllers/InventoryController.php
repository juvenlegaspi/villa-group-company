<?php

namespace App\Http\Controllers;

use App\Models\ItemInventoryHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeInventoryAccess();

        $search = $request->search;

        $items = ItemInventoryHeader::with('user')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('item_name', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%");
                });
            })
            ->orderBy('item_name', 'asc')
            ->paginate(10)
            ->withQueryString();

        return view('jmv.inventory.index', compact('items'));
    }

    public function store(Request $request)
    {
        $this->authorizeInventoryAccess();

        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'maximum_quantity' => 'required|integer',
            'minimum_quantity' => 'required|integer',
            'stock_on_hand' => 'required|integer',
        ]);

        ItemInventoryHeader::create([
            'item_name' => $data['item_name'],
            'unit' => $data['unit'],
            'maximum_quantity' => $data['maximum_quantity'],
            'minimum_quantity' => $data['minimum_quantity'],
            'stock_on_hand' => $data['stock_on_hand'],
            'date_added' => now(),
            'created_by' => Auth::id(),
            'status' => 1,
        ]);

        return redirect()->route('jmv.inventory.index')
            ->with('success', 'Item added successfully!');
    }

    protected function authorizeInventoryAccess(): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $user->loadMissing('division');

        abort_unless(
            strcasecmp((string) $user->division?->name, 'jmv') === 0,
            403
        );
    }
}
