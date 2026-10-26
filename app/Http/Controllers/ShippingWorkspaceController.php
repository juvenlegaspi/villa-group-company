<?php

namespace App\Http\Controllers;

use App\Models\Vessel;
use App\Services\VesselAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShippingWorkspaceController extends Controller
{
    public function applications(): View
    {
        return view('shipping.workspace.applications');
    }

    public function operations(): RedirectResponse
    {
        return redirect()->route('vessels.index');
    }

    public function pms(VesselAccessService $vesselAccess): View
    {
        abort_unless(auth()->user()->canAccessPms(), 403);

        $vessels = $vesselAccess->scopeAccessible(Vessel::query(), auth()->user())
            ->with('captain')
            ->orderBy('id', 'asc')
            ->paginate(10);

        return view('shipping.pms.index', compact('vessels'));
    }

    public function comingSoon(string $application): View
    {
        $applications = [
            'procurement' => [
                'name' => 'Procurement',
                'icon' => 'bi-cart-check',
                'description' => 'Purchase orders, supplier coordination and material sourcing.',
            ],
            'inventory' => [
                'name' => 'Inventory',
                'icon' => 'bi-clipboard-data',
                'description' => 'Stock levels, item movements and warehouse management.',
            ],
        ];

        abort_unless(isset($applications[$application]), 404);
        abort_unless(auth()->user()->hasPermission("shipping.{$application}.access"), 403, 'Your department or position is not authorized to access this application.');

        return view('shipping.workspace.coming-soon', [
            'application' => $applications[$application],
        ]);
    }
}
