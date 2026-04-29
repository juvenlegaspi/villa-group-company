<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ItemInventoryHeader;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
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
        //  validation
        $request->validate([
            'item_name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'maximum_quantity' => 'required|integer',
            'minimum_quantity' => 'required|integer',
            'stock_on_hand' => 'required|integer',
        ]);

        //  save
        ItemInventoryHeader::create([
            'item_name' => $request->item_name,
            'unit' => $request->unit,
            'maximum_quantity' => $request->maximum_quantity,
            'minimum_quantity' => $request->minimum_quantity,
            'stock_on_hand' => $request->stock_on_hand,
            'date_added' => now(),
            'created_by' => Auth::id(), // 👈 important
            'status' => 1,
        ]);

        return redirect()->route('jmv.inventory.index')
            ->with('success', 'Item added successfully!');
    }
}
