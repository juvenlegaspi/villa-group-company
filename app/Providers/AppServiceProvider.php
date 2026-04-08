<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Department;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

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
                    $departments = Department::all();
                } else {
                    // USER → iya ra department
                    $departments = Department::where('id', $user->department_id)->get();
                }
                $view->with('allDepartments', $departments);
            }
            Paginator::useBootstrap();
        });
    }
}
