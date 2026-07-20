<?php

namespace App\Http\Controllers;

use App\Models\YatiraFixedAsset;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class YatiraInventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeYatiraAccess();

        $search = $request->string('search')->toString();
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        $fixedAssets = Schema::hasTable('yatira_fixed_assets')
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

        return view('yatira.inventory.index', compact('fixedAssets'));
    }

    public function storeFixedAsset(Request $request)
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

        $data['asset_code'] = $this->generateAssetCode();
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
