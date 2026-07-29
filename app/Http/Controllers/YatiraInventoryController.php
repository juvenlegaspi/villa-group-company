<?php

namespace App\Http\Controllers;

use App\Models\YatiraFixedAsset;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class YatiraInventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeYatiraAccess();

        $search = $request->string('search')->toString();
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        $hasFixedAssetsTable = Schema::hasTable('yatira_fixed_assets');

        $fixedAssets = $hasFixedAssetsTable
            ? YatiraFixedAsset::with('user:id,name,lastname')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('asset_name', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%")
                            ->orWhere('assigned_to', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%");
                    });
                })
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(10)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 10, 1, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

        $fixedAssetConditionCounts = $hasFixedAssetsTable
            ? YatiraFixedAsset::query()
                ->selectRaw('asset_condition, COUNT(*) as total')
                ->groupBy('asset_condition')
                ->pluck('total', 'asset_condition')
                ->map(fn ($total) => (int) $total)
                ->all()
            : [];

        $fixedAssetStatusCounts = $hasFixedAssetsTable
            ? YatiraFixedAsset::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($total) => (int) $total)
                ->all()
            : [];

        $fixedAssetStats = [
            'total' => array_sum($fixedAssetConditionCounts),
            'condition_counts' => $fixedAssetConditionCounts,
            'status_counts' => $fixedAssetStatusCounts,
        ];

        return view('yatira.inventory.index', compact('fixedAssets', 'fixedAssetStats'));
    }

    public function storeFixedAsset(Request $request)
    {
        $this->authorizeYatiraAccess();

        if (is_string($request->input('asset_code'))) {
            $request->merge([
                'asset_code' => trim($request->input('asset_code')),
            ]);
        }

        $data = $request->validate([
            'asset_entry_type' => ['required', Rule::in(['new', 'existing'])],
            'asset_code' => [
                'exclude_unless:asset_entry_type,existing',
                'required',
                'string',
                'max:255',
                Rule::unique('yatira_fixed_assets', 'asset_code'),
            ],
            'asset_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'assigned_to' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_condition' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'date_acquired' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if ($data['asset_entry_type'] === 'new') {
            $data['asset_code'] = $this->generateAssetCode();
        }

        unset($data['asset_entry_type']);
        $data['created_by'] = auth()->id();

        YatiraFixedAsset::create($data);

        return redirect()
            ->route('yatira.inventory.index')
            ->with('success', 'Fixed asset added successfully.');
    }

    public function showFixedAsset(YatiraFixedAsset $fixedAsset)
    {
        $this->authorizeYatiraAccess();

        $fixedAsset->load('user:id,name,lastname');

        $qrPayload = $this->buildQrPayload($fixedAsset);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($qrPayload);

        return view('yatira.inventory.show', compact('fixedAsset', 'qrPayload', 'qrCodeUrl'));
    }

    public function updateFixedAsset(Request $request, YatiraFixedAsset $fixedAsset)
    {
        $this->authorizeYatiraAccess();

        $data = $request->validate([
            'asset_name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'assigned_to' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'asset_condition' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'date_acquired' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $fixedAsset->update($data);

        return redirect()
            ->route('yatira.inventory.fixed-assets.show', $fixedAsset->id)
            ->with('success', 'Fixed asset updated successfully.');
    }

    protected function generateAssetCode(): string
    {
        do {
            $letters = chr(random_int(97, 122)) . chr(random_int(97, 122));
            $numbers = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $assetCode = 'YC-' . $letters . $numbers;
        } while (YatiraFixedAsset::where('asset_code', $assetCode)->exists());

        return $assetCode;
    }

    protected function authorizeYatiraAccess(): void
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

    protected function buildQrPayload(YatiraFixedAsset $fixedAsset): string
    {
        return implode("\n", [
            'Asset Code: ' . ($fixedAsset->asset_code ?: 'N/A'),
            'Asset Name: ' . ($fixedAsset->asset_name ?: 'N/A'),
            'Category: ' . ($fixedAsset->category ?: 'N/A'),
            'Assigned To: ' . ($fixedAsset->assigned_to ?: 'N/A'),
            'Location: ' . ($fixedAsset->location ?: 'N/A'),
            'Condition: ' . ($fixedAsset->asset_condition ?: 'N/A'),
            'Status: ' . ($fixedAsset->status ?: 'N/A'),
            'Date Acquired: ' . (optional($fixedAsset->date_acquired)->format('Y-m-d') ?: 'N/A'),
            'Remarks: ' . ($fixedAsset->remarks ?: 'N/A'),
        ]);
    }
}
