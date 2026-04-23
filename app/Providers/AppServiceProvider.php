<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Department;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use App\Models\Division;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        view()->composer('*', function ($view) {
            if (Auth::check()) {
                $user = Auth::user();
                if ($user->is_admin == 1) {
                    // ADMIN → makita tanan
                    $divisions = Division::all();
                } else {
                    // USER → iya ra division
                    $divisions = Division::where('id', $user->division_id)->get();
                }
                $view->with('allDivisions', $divisions);
            }
            Paginator::useBootstrap();
        });
    }
}
