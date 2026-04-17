<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateNotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DryDockingHeaderController;
use App\Http\Controllers\TechDefectController;
use App\Http\Controllers\ThirdPartyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VesselCertificateController;
use App\Http\Controllers\VesselController;
use App\Http\Controllers\VoyageLogController;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('/login', 'login')->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', function () {
    Auth::logout();

    return redirect('/login');
})->name('logout');

Route::middleware('auth')->group(function () {
    Route::view('/change-password', 'auth.change-password');
    Route::post('/change-password', [AuthController::class, 'updatePassword']);

    Route::view('/profile', 'profile');
    Route::post('/profile', [AuthController::class, 'updateProfile']);

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/division/{division}', [DashboardController::class, 'divisionDashboard'])->name('division.dashboard');

    Route::get('/departments/{id}', function ($id) {
        $department = Department::findOrFail($id);
        $users = User::where('department_id', $id)->get();
        $totalUsers = $users->count();
        $totalAdmins = $users->where('role', 'admin')->count();
        $totalStaff = $users->where('role', 'staff')->count();

        return view('departments.show', compact(
            'department',
            'users',
            'totalUsers',
            'totalAdmins',
            'totalStaff'
        ));
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/create', [UserController::class, 'create']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}/edit', [UserController::class, 'edit']);
        Route::post('/{id}/update', [UserController::class, 'update']);
        Route::post('/{id}/delete', [UserController::class, 'destroy']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::post('/{id}/change-password', [UserController::class, 'changePassword']);
    });

    Route::get('/send-certificate-alerts', [CertificateNotificationController::class, 'sendAlerts'])
        ->name('certificate-alerts.send');

    Route::prefix('shipping')->group(function () {
        Route::prefix('vessels')->group(function () {
            Route::get('/', [VesselController::class, 'index'])->name('vessels.index');
            Route::get('/create', [VesselController::class, 'create']);
            Route::post('/', [VesselController::class, 'store']);
            Route::get('/{id}', [VesselController::class, 'show']);
            Route::get('/{id}/edit', [VesselController::class, 'edit'])->name('vessels.edit');
            Route::put('/{id}', [VesselController::class, 'update'])->name('vessels.update');

            Route::get('/{id}/logs/create', [VoyageLogController::class, 'create']);
            Route::post('/{id}/logs', [VoyageLogController::class, 'store']);
            Route::get('/{vessel}/logs/{log}/edit', [VoyageLogController::class, 'edit']);
            Route::put('/{vessel}/logs/{log}', [VoyageLogController::class, 'update']);
            Route::delete('/{vessel}/logs/{log}', [VoyageLogController::class, 'destroy']);
            Route::get('/{vesselId}/logs', [VoyageLogController::class, 'index']);
        });

        Route::prefix('voyage-logs')->group(function () {
            Route::get('/', [VoyageLogController::class, 'index']);
            Route::get('/create/{vessel}', [VoyageLogController::class, 'create']);
            Route::post('/store', [VoyageLogController::class, 'store']);
            Route::get('/dashboard', [VoyageLogController::class, 'dashboard'])->name('voyage-logs.dashboard');
            Route::get('/{id}', [VoyageLogController::class, 'show']);
            Route::get('/{id}/pdf', [VoyageLogController::class, 'exportPdf']);
            Route::post('/{id}/add-detail', [VoyageLogController::class, 'addDetail']);
            Route::post('/{id}/start', [VoyageLogController::class, 'startTrail']);
            Route::post('/{id}/pause', [VoyageLogController::class, 'pauseTrail']);
            Route::post('/{id}/resume', [VoyageLogController::class, 'resumeTrail']);
            Route::post('/{id}/complete-voyage', [VoyageLogController::class, 'completeVoyage']);
            Route::post('/{detail}/end', [VoyageLogController::class, 'endTrail']);
            Route::post('/{detail}/complete', [VoyageLogController::class, 'completeTrail']);
            Route::post('/{detail}/update-trail', [VoyageLogController::class, 'updateTrail']);
            Route::post('/{detailId}/update-trail', [VoyageLogController::class, 'updateTrail'])
                ->name('voyage-logs.update-trail');
        });

        Route::prefix('tech-defects')->group(function () {
            Route::get('/', [TechDefectController::class, 'index'])->name('tech-defects.index');
            Route::get('/create', [TechDefectController::class, 'create'])->name('tech-defects.create');
            Route::post('/create/{id?}', [TechDefectController::class, 'store'])->name('tech-defects.store');
            Route::get('/dashboard', [TechDefectController::class, 'dashboard'])->name('tech-defects.dashboard');
            Route::get('/{id}', [TechDefectController::class, 'show'])->name('tech-defects.show');
            Route::get('/{id}/edit', [TechDefectController::class, 'edit'])->name('tech-defects.edit');
            Route::put('/{id}', [TechDefectController::class, 'update'])->name('tech-defects.update');
            Route::post('/{id}/third-party', [ThirdPartyController::class, 'store'])->name('third-party.store');
        });

        Route::prefix('vessel-certificates')->group(function () {
            Route::get('/dashboard', [VesselCertificateController::class, 'dashboard'])->name('vessel-certificates.dashboard');
        });

        Route::prefix('dry-docking')->group(function () {
            Route::get('/', [DryDockingHeaderController::class, 'index']);
            Route::get('/create', [DryDockingHeaderController::class, 'create']);
            Route::post('/store', [DryDockingHeaderController::class, 'store']);
            Route::get('/{id}/details', [DryDockingHeaderController::class, 'details']);
            Route::post('/{id}/details/store', [DryDockingHeaderController::class, 'storeDetails']);
        });
    });

    Route::get('/tech-defects/create/{id?}', [TechDefectController::class, 'create'])
        ->name('tech-defects.create.legacy');
    Route::post('/third-party/{id}', [ThirdPartyController::class, 'store'])
        ->name('third-party.store.legacy');

    Route::prefix('vessel-certificates')->group(function () {
        Route::get('/', [VesselCertificateController::class, 'index'])->name('vessel-certificates.index');
        Route::post('/', [VesselCertificateController::class, 'store'])->name('vessel-certificates.store');
        Route::get('/add/{vessel}', [VesselCertificateController::class, 'create'])->name('vessel-certificates.add');
        Route::get('/{id}', [VesselCertificateController::class, 'show'])->name('vessel.certificates.show');
        Route::get('/{id}/edit', [VesselCertificateController::class, 'edit'])->name('vessel-certificates.edit');
        Route::post('/{id}/update', [VesselCertificateController::class, 'update'])->name('vessel-certificates.update');
    });
});
