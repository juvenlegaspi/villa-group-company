<?php

namespace App\Http\Controllers;

class CompanyFixedAssetController extends YatiraInventoryController
{
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

        return view('fixed-assets.workspace', $this->assetContext() + compact('canAssets', 'canStock'));
    }
}
