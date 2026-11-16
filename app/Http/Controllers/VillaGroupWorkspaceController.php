<?php

namespace App\Http\Controllers;

use App\Services\VillaGroupAccessService;

class VillaGroupWorkspaceController extends Controller
{
    public function applications(VillaGroupAccessService $access)
    {
        $user = auth()->user();
        return view('villa_group.applications', [
            'canAccessIt' => $access->canAccess($user, 'it'),
            'canAccessHr' => $access->canAccess($user, 'hr'),
            'canAccessProperties' => $access->canAccess($user, 'properties'),
        ]);
    }
    public function it() { return view('villa_group.it'); }
    public function hr() { return view('villa_group.hr'); }
}
