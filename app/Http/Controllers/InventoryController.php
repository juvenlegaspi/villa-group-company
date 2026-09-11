<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\ItemInventoryHeader;
use App\Models\JmvInventoryAudit;
use App\Models\JmvInventoryCategory;
use App\Models\JmvInventoryLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('jmv.inventory.view');
        $divisionId = $this->jmvDivisionId();
        $search = $request->string('search')->trim()->toString();
        $stockStatus = $request->string('stock_status')->toString();
        $items = ItemInventoryHeader::with(['user:id,name,lastname', 'category:id,name', 'defaultLocation:id,name', 'balances.location:id,name'])
            ->where('division_id', $divisionId)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('item_code', 'like', "%{$search}%")->orWhere('item_name', 'like', "%{$search}%")->orWhere('unit', 'like', "%{$search}%")))
            ->when($stockStatus === 'low', fn (Builder $query) => $query->where('stock_on_hand', '>', 0)->whereColumn('stock_on_hand', '<=', 'minimum_quantity'))
            ->when($stockStatus === 'out', fn (Builder $query) => $query->where('stock_on_hand', '<=', 0))
            ->when($stockStatus === 'active', fn (Builder $query) => $query->where('status', true))
            ->orderBy('item_name')->paginate(12)->withQueryString();
        $base = ItemInventoryHeader::where('division_id', $divisionId)->where('status', true);
        $stats = [
            'items' => (clone $base)->count(), 'stock' => (int) (clone $base)->sum('stock_on_hand'),
            'low' => (clone $base)->where('stock_on_hand', '>', 0)->whereColumn('stock_on_hand', '<=', 'minimum_quantity')->count(),
            'out' => (clone $base)->where('stock_on_hand', '<=', 0)->count(),
            'value' => (float) (clone $base)->selectRaw('COALESCE(SUM(stock_on_hand * COALESCE(unit_cost, 0)), 0) total')->value('total'),
        ];
        $categories = JmvInventoryCategory::where('division_id', $divisionId)->where('is_active', true)->orderBy('name')->get();
        $locations = JmvInventoryLocation::where('division_id', $divisionId)->where('is_active', true)->orderBy('name')->get();
        return view('jmv.inventory.index', compact('items', 'stats', 'categories', 'locations') + ['canManageItems' => $this->can('jmv.inventory.items.manage'), 'canMoveStock' => $this->can('jmv.inventory.movements.manage'), 'canAdjust' => $this->can('jmv.inventory.adjustments.manage'), 'canReport' => $this->can('jmv.inventory.reports.view')]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('jmv.inventory.items.manage');
        $divisionId = $this->jmvDivisionId();
        $request->merge(['normalized_name' => $this->normalize((string) $request->input('item_name'))]);
        $data = $this->validateItem($request, $divisionId);
        DB::transaction(function () use ($data, $divisionId): void {
            $item = ItemInventoryHeader::create([...$data, 'item_code' => $this->generateCode(), 'division_id' => $divisionId, 'date_added' => now(), 'created_by' => auth()->id(), 'updated_by' => auth()->id(), 'status' => true]);
            $item->balances()->create(['location_id' => $data['default_location_id'], 'quantity' => $data['stock_on_hand']]);
            if ($data['stock_on_hand'] > 0) {
                $transaction = $item->transactions()->create(['location_id' => $data['default_location_id'], 'type' => 'IN', 'movement_kind' => 'OPENING', 'quantity' => $data['stock_on_hand'], 'balance_before' => 0, 'balance_after' => $data['stock_on_hand'], 'reference_no' => 'OPENING-BALANCE', 'remarks' => 'Opening stock balance', 'unit_cost' => $data['unit_cost'] ?? null, 'transacted_at' => now(), 'created_by' => auth()->id()]);
                $this->audit($item, 'opening_stock_recorded', ['transaction_id' => $transaction->id]);
            }
            $this->audit($item, 'item_created', ['item_code' => $item->item_code]);
        });
        return redirect()->route('jmv.inventory.index')->with('success', 'Inventory item created successfully.');
    }

    public function update(Request $request, ItemInventoryHeader $item)
    {
        $this->authorizePermission('jmv.inventory.items.manage'); $this->ensureJmvItem($item);
        $request->merge(['normalized_name' => $this->normalize((string) $request->input('item_name'))]);
        $data = $this->validateItem($request, $this->jmvDivisionId(), $item);
        $before = $item->only(array_keys($data)); unset($data['stock_on_hand']);
        $item->update([...$data, 'updated_by' => auth()->id()]);
        $this->audit($item, 'item_updated', ['before' => $before, 'after' => $item->fresh()->only(array_keys($before))]);
        return back()->with('success', 'Item details updated successfully.');
    }

    public function report(): StreamedResponse
    {
        $this->authorizePermission('jmv.inventory.reports.view');
        $items = ItemInventoryHeader::with(['category', 'defaultLocation'])->where('division_id', $this->jmvDivisionId())->orderBy('item_name')->get();
        return response()->streamDownload(function () use ($items): void {
            $output = fopen('php://output', 'w'); fputcsv($output, ['Item Code','Item Name','Category','Location','Unit','Minimum','Maximum','Stock','Unit Cost','Inventory Value','Status']);
            foreach ($items as $item) fputcsv($output, [$item->item_code,$item->item_name,$item->category?->name,$item->defaultLocation?->name,$item->unit,$item->minimum_quantity,$item->maximum_quantity,$item->stock_on_hand,$item->unit_cost,number_format($item->stock_on_hand * (float) $item->unit_cost, 2, '.', ''),$item->status ? 'Active' : 'Inactive']);
            fclose($output);
        }, 'jmv-inventory-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validateItem(Request $request, int $divisionId, ?ItemInventoryHeader $item = null): array
    {
        return $request->validate([
            'item_name' => 'required|string|max:255',
            'normalized_name' => ['required', Rule::unique('item_inventory_header', 'normalized_name')->where(fn ($query) => $query->where('division_id', $divisionId)->where('unit', $request->input('unit')))->ignore($item?->id)],
            'description' => 'nullable|string|max:3000', 'unit' => 'required|string|max:50',
            'category_id' => ['required', Rule::exists('jmv_inventory_categories', 'id')->where(fn ($query) => $query->where('division_id', $divisionId)->where('is_active', true))],
            'default_location_id' => ['required', Rule::exists('jmv_inventory_locations', 'id')->where(fn ($query) => $query->where('division_id', $divisionId)->where('is_active', true))],
            'maximum_quantity' => 'required|integer|min:0|gte:minimum_quantity'.($item ? '|gte:stock_on_hand' : ''), 'minimum_quantity' => 'required|integer|min:0',
            'stock_on_hand' => $item ? 'required|integer|in:'.$item->stock_on_hand : 'required|integer|min:0|lte:maximum_quantity',
            'unit_cost' => 'nullable|numeric|min:0|max:999999999999.99', 'preferred_supplier' => 'nullable|string|max:255',
            'status' => $item ? 'required|boolean' : 'nullable',
        ]);
    }
    private function can(string $permission): bool { return auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission($permission); }
    private function authorizePermission(string $permission): void { abort_unless($this->can($permission), 403, 'Your department or position is not authorized for this JMV inventory action.'); }
    private function jmvDivisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['jmv'])->value('id'); }
    private function normalize(string $value): string { return (string) preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($value))); }
    private function generateCode(): string { do { $code = 'JMV-'.strtoupper(bin2hex(random_bytes(3))); } while (ItemInventoryHeader::where('item_code', $code)->exists()); return $code; }
    private function ensureJmvItem(ItemInventoryHeader $item): void { abort_unless((int) $item->division_id === $this->jmvDivisionId(), 404); }
    private function audit(ItemInventoryHeader $item, string $action, array $changes = []): void { JmvInventoryAudit::create(['item_inventory_header_id' => $item->id, 'user_id' => auth()->id(), 'action' => $action, 'changes' => $changes ?: null, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null, 'created_at' => now()]); }
}
