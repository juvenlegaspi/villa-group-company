<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Models\YatiraFixedAsset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyFixedAssetController extends YatiraInventoryController
{
    protected function assetContext(): array
    {
        return parent::assetContext() + ['assetVessels' => $this->assetCompanyKey() === 'shipping'
            ? Vessel::orderBy('vessel_name')->get(['id', 'vessel_name']) : collect()];
    }

    protected function validateAsset(Request $request, bool $creating, ?YatiraFixedAsset $asset = null): array
    {
        $data = parent::validateAsset($request, $creating, $asset);
        if ($this->assetCompanyKey() !== 'shipping') {
            return $data;
        }
        $vessels = Vessel::pluck('vessel_name', 'id');
        $choices = ['office', ...$vessels->keys()->map(fn ($id) => 'vessel:'.$id)->all()];
        if ($asset && ! $asset->vessel_id && filled($asset->location) && $asset->location !== 'Villa Shipping Office') {
            $choices[] = 'current';
        }
        $selection = $request->validate(['asset_location' => ['required', Rule::in($choices)]])['asset_location'];
        if ($selection === 'current') {
            $data['location'] = $asset->location;
            $data['vessel_id'] = null;
        } elseif ($selection === 'office') {
            $data['location'] = 'Villa Shipping Office';
            $data['vessel_id'] = null;
        } else {
            $id = (int) substr($selection, 7);
            $data['location'] = $vessels[$id];
            $data['vessel_id'] = $id;
        }

        return $data;
    }

    protected function assetCompanyKey(): string
    {
        if (request()->routeIs('jmv.*')) {
            return 'jmv';
        }
        if (request()->routeIs('shipping.*')) {
            return 'shipping';
        }
        abort(404);
    }

    public function workspace()
    {
        $key = $this->assetCompanyKey();
        $user = auth()->user();
        $canAssets = $user->isSystemAdministrator() || $user->hasPermission($key.'.assets.view');
        $canStock = $key === 'jmv' && ($user->isSystemAdministrator() || $user->hasPermission('jmv.inventory.view'));
        abort_unless($canAssets || $canStock, 403);

        if ($key === 'shipping') {
            return to_route('shipping.application.placeholder', 'procurement');
        }

        return view('fixed-assets.workspace', $this->assetContext() + compact('canAssets', 'canStock'));
    }
}
