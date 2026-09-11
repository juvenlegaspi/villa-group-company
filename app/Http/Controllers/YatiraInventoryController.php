<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\User;
use App\Models\YatiraConsumable;
use App\Models\YatiraConsumableTransaction;
use App\Models\YatiraFixedAsset;
use App\Models\YatiraFixedAssetAudit;
use App\Models\YatiraFixedAssetDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class YatiraInventoryController extends Controller
{
    private const CATEGORIES = ['HEAVY EQPT/MACHINERY', 'TRANSPORTATION EQUIPMENT', 'TOOLS AND SMALL EQPT', 'BUILDING AND FACILITY', 'FURNITURE AND OFFICE EQUIPMENTS', 'IT & SYSTEMS INFRASTRUCTURE', 'OTHER / LEGACY'];
    private const CONDITIONS = ['Good', 'Needs Repair', 'Damaged', 'Retired'];
    private const STATUSES = ['Active', 'In Use', 'Under Maintenance', 'Disposed'];
    private const DOCUMENT_RULES = 'mimes:pdf,jpg,jpeg,png|max:5120';

    public function index(Request $request)
    {
        $this->authorizeAnyInventoryView();
        $divisionId = $this->yatiraDivisionId();
        $canViewAssets = $this->can('yatira.assets.view');
        $canCreateAssets = $this->can('yatira.assets.create');
        $canUpdateAssets = $this->can('yatira.assets.update');
        $canDisposeAssets = $this->can('yatira.assets.dispose');
        $canManageAssets = $canCreateAssets || $canUpdateAssets;
        $canViewConsumables = $this->can('yatira.consumables.view');
        $canManageConsumables = $this->can('yatira.consumables.manage');
        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        $fixedAssets = $canViewAssets
            ? YatiraFixedAsset::with(['user:id,name,lastname', 'assignedUser:id,name,lastname', 'assignedDepartment:id,name'])
                ->where('division_id', $divisionId)
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(fn (Builder $nested) => $nested->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('asset_name', 'like', "%{$search}%")->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")->orWhere('assigned_to', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%"));
                })->when($category !== '', fn (Builder $query) => $query->where('category', $category))
                ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
                ->latest()->paginate(10, ['*'], 'assets_page')->withQueryString()
            : $this->emptyPaginator('assets_page');

        $conditionCounts = $canViewAssets ? YatiraFixedAsset::where('division_id', $divisionId)->selectRaw('asset_condition, COUNT(*) total')->groupBy('asset_condition')->pluck('total', 'asset_condition')->map(fn ($value) => (int) $value)->all() : [];
        $statusCounts = $canViewAssets ? YatiraFixedAsset::where('division_id', $divisionId)->selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status')->map(fn ($value) => (int) $value)->all() : [];
        $fixedAssetStats = ['total' => array_sum($conditionCounts), 'condition_counts' => $conditionCounts, 'status_counts' => $statusCounts];

        $consumableSearch = $request->string('consumable_search')->trim()->toString();
        $consumables = $canViewConsumables
            ? YatiraConsumable::where('division_id', $divisionId)
                ->when($consumableSearch !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('item_code', 'like', "%{$consumableSearch}%")->orWhere('item_name', 'like', "%{$consumableSearch}%")))
                ->orderBy('item_name')->paginate(10, ['*'], 'consumables_page')->withQueryString()
            : $this->emptyPaginator('consumables_page');

        $users = User::where('division_id', $divisionId)->where('status', true)->orderBy('name')->get(['id', 'name', 'lastname', 'department_id']);
        $departments = Department::where('division_id', $divisionId)->orderBy('name')->get(['id', 'name']);

        return view('yatira.inventory.index', compact('fixedAssets', 'fixedAssetStats', 'consumables', 'users', 'departments', 'canViewAssets', 'canCreateAssets', 'canUpdateAssets', 'canDisposeAssets', 'canManageAssets', 'canViewConsumables', 'canManageConsumables'));
    }

    public function storeFixedAsset(Request $request)
    {
        $this->authorizePermission('yatira.assets.create');
        if (is_string($request->input('asset_code'))) $request->merge(['asset_code' => trim($request->input('asset_code'))]);
        $data = $this->validateAsset($request, true);
        if ($data['asset_entry_type'] === 'new') $data['asset_code'] = $this->generateAssetCode();
        unset($data['asset_entry_type'], $data['document']);
        $data = $this->applyStructuredAssignment($data);
        $upload = $request->hasFile('document') ? $this->storeDocument($request) : [];
        try {
            $asset = DB::transaction(function () use ($data, $upload): YatiraFixedAsset {
                $asset = YatiraFixedAsset::create([...$data, ...$this->currentDocumentFields($upload), 'division_id' => $this->yatiraDivisionId(), 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                $this->recordDocumentVersion($asset, $upload);
                $this->recordAudit($asset, 'created', ['asset_code' => $asset->asset_code]);
                return $asset;
            });
        } catch (\Throwable $exception) {
            if (isset($upload['document_path'])) Storage::disk('local')->delete($upload['document_path']);
            throw $exception;
        }
        return redirect()->route('yatira.inventory.fixed-assets.show', $asset)->with('success', 'Fixed asset added successfully.');
    }

    public function showFixedAsset(YatiraFixedAsset $fixedAsset)
    {
        $this->authorizePermission('yatira.assets.view');
        $this->ensureYatiraAsset($fixedAsset);
        $fixedAsset->load(['user:id,name,lastname', 'updater:id,name,lastname', 'assignedUser:id,name,lastname', 'assignedDepartment:id,name', 'audits.user:id,name,lastname', 'documents.uploader:id,name,lastname']);
        $barcodeSvg = $this->barcodeSvgDataUri($fixedAsset->asset_code);
        $divisionId = $this->yatiraDivisionId();
        $users = User::where('division_id', $divisionId)->where('status', true)->orderBy('name')->get(['id', 'name', 'lastname', 'department_id']);
        $departments = Department::where('division_id', $divisionId)->orderBy('name')->get(['id', 'name']);
        $canUpdateAssets = $this->can('yatira.assets.update');
        $canDisposeAssets = $this->can('yatira.assets.dispose');
        $canManageAssets = $canUpdateAssets;
        return view('yatira.inventory.show', compact('fixedAsset', 'barcodeSvg', 'users', 'departments', 'canManageAssets', 'canUpdateAssets', 'canDisposeAssets'));
    }

    public function updateFixedAsset(Request $request, YatiraFixedAsset $fixedAsset)
    {
        $this->authorizePermission('yatira.assets.update');
        $this->ensureYatiraAsset($fixedAsset);
        $data = $this->validateAsset($request, false);
        unset($data['asset_entry_type'], $data['asset_code'], $data['document']);
        $data = $this->applyStructuredAssignment($data);
        if (($data['status'] ?? null) === 'Disposed') {
            $this->authorizePermission('yatira.assets.dispose');
            if (blank($data['disposal_reason'] ?? null)) throw ValidationException::withMessages(['disposal_reason' => 'Provide the disposal reason before marking an asset as disposed.']);
        }
        $upload = $request->hasFile('document') ? $this->storeDocument($request) : [];
        $before = $fixedAsset->only(['asset_name', 'serial_number', 'category', 'assigned_to', 'assigned_user_id', 'assigned_department_id', 'location', 'asset_condition', 'status', 'date_acquired', 'acquisition_cost', 'remarks', 'document_path', 'disposal_reason']);
        if (($data['status'] ?? null) === 'Disposed' && ! $fixedAsset->disposed_at) $data['disposed_at'] = now();
        if (($data['status'] ?? null) !== 'Disposed') $data['disposed_at'] = null;
        try {
            DB::transaction(function () use ($fixedAsset, $data, $upload, $before): void {
                $fixedAsset->update([...$data, ...$this->currentDocumentFields($upload), 'updated_by' => auth()->id()]);
                $this->recordDocumentVersion($fixedAsset, $upload);
                $this->recordAudit($fixedAsset, 'updated', ['before' => $before, 'after' => $fixedAsset->fresh()->only(array_keys($before))]);
            });
        } catch (\Throwable $exception) {
            if (isset($upload['document_path'])) Storage::disk('local')->delete($upload['document_path']);
            throw $exception;
        }
        return redirect()->route('yatira.inventory.fixed-assets.show', $fixedAsset)->with('success', 'Fixed asset updated successfully.');
    }

    public function downloadAssetDocument(YatiraFixedAsset $fixedAsset): BinaryFileResponse
    {
        $this->authorizePermission('yatira.assets.view');
        $this->ensureYatiraAsset($fixedAsset);
        abort_unless($fixedAsset->document_path && Storage::disk('local')->exists($fixedAsset->document_path), 404);
        $this->recordAudit($fixedAsset, 'document_downloaded');
        return response()->download(Storage::disk('local')->path($fixedAsset->document_path), $fixedAsset->document_original_name ?: basename($fixedAsset->document_path));
    }

    public function downloadAssetDocumentVersion(YatiraFixedAsset $fixedAsset, YatiraFixedAssetDocument $document): BinaryFileResponse
    {
        $this->authorizePermission('yatira.assets.view');
        $this->ensureYatiraAsset($fixedAsset);
        abort_unless((int) $document->yatira_fixed_asset_id === (int) $fixedAsset->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        $this->recordAudit($fixedAsset, 'document_version_downloaded', ['document_id' => $document->id]);
        return response()->download(Storage::disk('local')->path($document->path), $document->original_name);
    }

    public function storeConsumable(Request $request)
    {
        $this->authorizePermission('yatira.consumables.manage');
        $request->merge(['normalized_name' => $this->normalizeItemName((string) $request->input('item_name'))]);
        $data = $request->validate(['item_name' => 'required|string|max:255', 'normalized_name' => ['required', Rule::unique('yatira_consumables', 'normalized_name')->where(fn ($query) => $query->where('division_id', $this->yatiraDivisionId()))], 'unit' => 'required|string|max:50', 'opening_stock' => 'required|integer|min:0', 'reorder_level' => 'required|integer|min:0']);
        DB::transaction(function () use ($data): void {
            $item = YatiraConsumable::create(['division_id' => $this->yatiraDivisionId(), 'item_code' => $this->generateConsumableCode(), 'item_name' => trim($data['item_name']), 'normalized_name' => $data['normalized_name'], 'unit' => trim($data['unit']), 'stock_on_hand' => $data['opening_stock'], 'reorder_level' => $data['reorder_level'], 'status' => true, 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
            if ($data['opening_stock'] > 0) $item->transactions()->create(['type' => 'IN', 'quantity' => $data['opening_stock'], 'balance_before' => 0, 'balance_after' => $data['opening_stock'], 'reference_no' => 'OPENING-BALANCE', 'remarks' => 'Opening stock balance', 'created_by' => auth()->id(), 'transacted_at' => now()]);
        });
        return redirect()->route('yatira.inventory.index', ['tab' => 'consumables'])->with('success', 'Consumable item added successfully.');
    }

    public function moveConsumable(Request $request, YatiraConsumable $consumable)
    {
        $this->authorizePermission('yatira.consumables.manage');
        abort_unless((int) $consumable->division_id === $this->yatiraDivisionId(), 404);
        $data = $request->validate(['type' => ['required', Rule::in(['IN', 'OUT', 'ADJUSTMENT'])], 'quantity' => 'nullable|required_unless:type,ADJUSTMENT|integer|min:1', 'adjusted_balance' => 'nullable|required_if:type,ADJUSTMENT|integer|min:0', 'reference_no' => 'required|string|max:100', 'remarks' => 'required_if:type,ADJUSTMENT|nullable|string|max:2000']);
        DB::transaction(function () use ($consumable, $data): void {
            $locked = YatiraConsumable::whereKey($consumable->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status) throw ValidationException::withMessages(['type' => 'Stock movements are disabled for an inactive item.']);
            $before = (int) $locked->stock_on_hand;
            if ($data['type'] === 'OUT' && $data['quantity'] > $before) throw ValidationException::withMessages(['quantity' => 'Stock out cannot exceed the available balance.']);
            $after = match ($data['type']) { 'IN' => $before + $data['quantity'], 'OUT' => $before - $data['quantity'], default => (int) $data['adjusted_balance'] };
            if ($data['type'] === 'ADJUSTMENT' && $after === $before) throw ValidationException::withMessages(['adjusted_balance' => 'The adjusted balance must be different from the current balance.']);
            $quantity = $data['type'] === 'ADJUSTMENT' ? abs($after - $before) : (int) $data['quantity'];
            $locked->update(['stock_on_hand' => $after, 'updated_by' => auth()->id()]);
            YatiraConsumableTransaction::create(['type' => $data['type'], 'quantity' => $quantity, 'reference_no' => trim($data['reference_no']), 'remarks' => $data['remarks'] ?? null, 'yatira_consumable_id' => $locked->id, 'balance_before' => $before, 'balance_after' => $after, 'created_by' => auth()->id(), 'transacted_at' => now()]);
        });
        return redirect()->route('yatira.inventory.consumables.show', $consumable)->with('success', 'Stock movement recorded successfully.');
    }

    public function showConsumable(YatiraConsumable $consumable)
    {
        $this->authorizePermission('yatira.consumables.view');
        $this->ensureYatiraConsumable($consumable);
        $consumable->load(['creator:id,name,lastname', 'updater:id,name,lastname']);
        $transactions = $consumable->transactions()->with('user:id,name,lastname')->paginate(20);
        $canManageConsumables = $this->can('yatira.consumables.manage');
        return view('yatira.inventory.consumable-show', compact('consumable', 'transactions', 'canManageConsumables'));
    }

    public function updateConsumable(Request $request, YatiraConsumable $consumable)
    {
        $this->authorizePermission('yatira.consumables.manage');
        $this->ensureYatiraConsumable($consumable);
        $request->merge(['normalized_name' => $this->normalizeItemName((string) $request->input('item_name'))]);
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'normalized_name' => ['required', Rule::unique('yatira_consumables', 'normalized_name')->where(fn ($query) => $query->where('division_id', $this->yatiraDivisionId()))->ignore($consumable->id)],
            'unit' => 'required|string|max:50',
            'reorder_level' => 'required|integer|min:0',
            'status' => 'required|boolean',
        ]);
        $consumable->update([...$data, 'item_name' => trim($data['item_name']), 'unit' => trim($data['unit']), 'updated_by' => auth()->id()]);
        return back()->with('success', 'Consumable item updated successfully.');
    }

    private function validateAsset(Request $request, bool $creating): array
    {
        $divisionId = $this->yatiraDivisionId();
        return $request->validate([
            'asset_entry_type' => $creating ? ['required', Rule::in(['new', 'existing'])] : ['nullable'],
            'asset_code' => $creating ? ['exclude_unless:asset_entry_type,existing', 'required', 'string', 'max:255', Rule::unique('yatira_fixed_assets', 'asset_code')] : ['nullable'],
            'asset_name' => 'required|string|max:255', 'serial_number' => 'nullable|string|max:255', 'category' => ['required', Rule::in(self::CATEGORIES)],
            'assigned_user_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('division_id', $divisionId)->where('status', true))],
            'assigned_department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('division_id', $divisionId))],
            'location' => 'nullable|string|max:255', 'asset_condition' => ['required', Rule::in(self::CONDITIONS)], 'status' => ['required', Rule::in($creating ? array_values(array_diff(self::STATUSES, ['Disposed'])) : self::STATUSES)],
            'date_acquired' => 'nullable|date|before_or_equal:today', 'acquisition_cost' => 'nullable|numeric|min:0|max:999999999999.99',
            'remarks' => 'nullable|string|max:5000', 'disposal_reason' => 'nullable|string|max:2000', 'document' => 'nullable|'.self::DOCUMENT_RULES,
        ]);
    }

    private function storeDocument(Request $request): array
    {
        $file = $request->file('document');
        return ['document_path' => $file->store('yatira/assets', 'local'), 'document_original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize()];
    }

    private function generateAssetCode(): string { do { $code = 'YC-'.strtoupper(bin2hex(random_bytes(3))); } while (YatiraFixedAsset::withTrashed()->where('asset_code', $code)->exists()); return $code; }
    private function generateConsumableCode(): string { do { $code = 'YCI-'.strtoupper(bin2hex(random_bytes(3))); } while (YatiraConsumable::withTrashed()->where('item_code', $code)->exists()); return $code; }
    private function recordAudit(YatiraFixedAsset $asset, string $action, array $changes = []): void { YatiraFixedAssetAudit::create(['yatira_fixed_asset_id' => $asset->id, 'user_id' => auth()->id(), 'action' => $action, 'changes' => $changes ?: null, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null, 'created_at' => now()]); }
    private function can(string $permission): bool { return auth()->user()->isSystemAdministrator() || auth()->user()->hasPermission($permission); }
    private function authorizePermission(string $permission): void { abort_unless($this->can($permission), 403, 'Your department or position is not authorized for this Yatira action.'); }
    private function authorizeAnyInventoryView(): void { abort_unless($this->can('yatira.assets.view') || $this->can('yatira.consumables.view'), 403, 'Inventory access is not assigned to your position.'); }
    private function ensureYatiraAsset(YatiraFixedAsset $asset): void { abort_unless((int) $asset->division_id === $this->yatiraDivisionId(), 404); }
    private function ensureYatiraConsumable(YatiraConsumable $consumable): void { abort_unless((int) $consumable->division_id === $this->yatiraDivisionId(), 404); }
    private function yatiraDivisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', ['yatira'])->value('id'); }
    private function emptyPaginator(string $pageName): LengthAwarePaginator { return new LengthAwarePaginator([], 0, 10, 1, ['path' => request()->url(), 'query' => request()->query(), 'pageName' => $pageName]); }
    private function normalizeItemName(string $name): string { return (string) preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim($name))); }
    private function barcodeSvgDataUri(string $value): string
    {
        $patterns = [
            '0' => 'nnwwnwnnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw',
            '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
            'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn',
            'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
            'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn',
            'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
            'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn',
            'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
        ];
        $encoded = '*'.strtoupper($value).'*';
        $x = 12;
        $bars = '';
        foreach (str_split($encoded) as $character) {
            $pattern = $patterns[$character] ?? $patterns['-'];
            foreach (str_split($pattern) as $index => $widthType) {
                $width = $widthType === 'w' ? 4 : 2;
                if ($index % 2 === 0) $bars .= '<rect x="'.$x.'" y="8" width="'.$width.'" height="62" fill="#0f172a"/>';
                $x += $width;
            }
            $x += 2;
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.($x + 12).' 92" role="img"><rect width="100%" height="100%" fill="white"/>'.$bars.'<text x="50%" y="86" text-anchor="middle" font-family="Arial,sans-serif" font-size="11" fill="#0f172a">'.htmlspecialchars($value, ENT_QUOTES | ENT_XML1).'</text></svg>';
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
    private function currentDocumentFields(array $upload): array { return array_intersect_key($upload, array_flip(['document_path', 'document_original_name'])); }
    private function recordDocumentVersion(YatiraFixedAsset $asset, array $upload): void { if (! isset($upload['document_path'])) return; $asset->documents()->create(['path' => $upload['document_path'], 'original_name' => $upload['document_original_name'], 'mime_type' => $upload['mime_type'] ?? null, 'size_bytes' => $upload['size_bytes'] ?? null, 'uploaded_by' => auth()->id()]); }
    private function applyStructuredAssignment(array $data): array
    {
        $user = ! empty($data['assigned_user_id']) ? User::whereKey($data['assigned_user_id'])->first() : null;
        if ($user) $data['assigned_department_id'] = $user->department_id;
        $department = ! empty($data['assigned_department_id']) ? Department::find($data['assigned_department_id']) : null;
        $data['assigned_to'] = $user ? trim($user->name.' '.$user->lastname) : ($department?->name);
        return $data;
    }
}
