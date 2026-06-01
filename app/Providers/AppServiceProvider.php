<?php

namespace App\Providers;

use App\Models\Division;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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
        Paginator::useBootstrap();

        view()->composer('layouts.app', function ($view) {
            $divisions = collect();

            if (Auth::check()) {
                $user = Auth::user();

                $divisions = $user->isAdmin()
                    ? Division::orderBy('id')->get()
                    : Division::whereKey($user->division_id)->get();
            }

            $view->with('allDivisions', $divisions);
        });
    }
}
