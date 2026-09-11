<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class YatiraWorkspaceController extends Controller
{
    public function applications(): View
    {
        return view('yatira.workspace.applications');
    }
}
