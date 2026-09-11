<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class JmvWorkspaceController extends Controller
{
    public function applications(): View
    {
        return view('jmv.workspace.applications');
    }
}
