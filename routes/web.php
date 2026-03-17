<?php
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VesselController;
use App\Http\Controllers\VoyageLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TechDefectController;
use App\Http\Controllers\VesselCertificateController;


Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', [AuthController::class, 'login']);



Route::post('/logout', function () {
    Auth::logout();
    return redirect('/login');
})->name('logout');

Route::middleware(['auth', 'role:admin'])->group(function () {

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/create', [UserController::class, 'create']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}/edit', [UserController::class, 'edit']);
    Route::post('/users/{id}/update', [UserController::class, 'update']);
    Route::post('/users/{id}/delete', [UserController::class, 'destroy']);

});

Route::get('/change-password', function () {
    return view('auth.change-password');
})->middleware('auth');

Route::post('/change-password', [AuthController::class, 'updatePassword'])->middleware('auth');

Route::get('/profile', function () {
    return view('profile');
})->middleware('auth');

Route::post('/profile', [AuthController::class, 'updateProfile'])->middleware('auth');

Route::get('/dashboard', function () {

    if (Auth::user()->must_change_password == 1) {
        return redirect('/change-password');
    }

    $totalUsers = User::count();
    $totalDepartments = Department::count();
    $totalAdmins = User::where('role','admin')->count();
    $totalStaff = User::where('role','staff')->count();
    $recentUsers = User::latest()->take(5)->get();

    // ADD THESE
    $totalVessels = \App\Models\Vessel::count();
    $totalVoyageLogs = \App\Models\VoyageLog::count();
    $totalDefects = \App\Models\TechDefect::count();

    return view('dashboard', compact(
        'totalUsers',
        'totalDepartments',
        'totalAdmins',
        'totalStaff',
        'recentUsers',
        'totalVessels',
        'totalVoyageLogs',
        'totalDefects'
    ));

})->middleware('auth');

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

})->middleware(['auth']);


Route::get('/shipping/tech-defects/dashboard', [TechDefectController::class,'dashboard'])->name('tech-defects.dashboard');
Route::get('/shipping/vessels', [VesselController::class, 'index'])->name('vessels.index')->middleware('auth');
Route::get('/shipping/vessels/create', [VesselController::class, 'create'])->middleware('auth');
Route::post('/shipping/vessels', [VesselController::class, 'store'])->middleware('auth');
Route::get('/shipping/vessels/{id}', [VesselController::class, 'show'])->middleware('auth');

Route::get('/shipping/vessels/{id}/logs/create', [VoyageLogController::class, 'create'])->middleware('auth');

Route::post('/shipping/vessels/{id}/logs', [VoyageLogController::class, 'store'])->middleware('auth');

Route::get('/shipping/vessels/{vessel}/logs/{log}/edit', [VoyageLogController::class, 'edit']);
Route::put('/shipping/vessels/{vessel}/logs/{log}', [VoyageLogController::class, 'update']);

Route::delete('/shipping/vessels/{vessel}/logs/{log}', [VoyageLogController::class, 'destroy']);
Route::get('/shipping/vessels/{id}',[VesselController::class, 'show'])->middleware('auth');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth');
// // // // 

Route::get('/shipping/tech-defects', [TechDefectController::class,'index'])->name('tech-defects.index');
Route::get('/shipping/tech-defects/create', [TechDefectController::class,'create'])->name('tech-defects.create');
// tech-defect add
Route::post('/shipping/tech-defects/create/{id?}', [TechDefectController::class,'store'])->name('tech-defects.store');
Route::get('/tech-defects/create/{id?}', [TechDefectController::class, 'create'])
    ->name('tech-defects.create');

Route::get('/shipping/tech-defects/{id}/edit', [TechDefectController::class,'edit'])->name('tech-defects.edit');
Route::put('/shipping/tech-defects/{id}', [TechDefectController::class,'update'])->name('tech-defects.update');

//Route::delete('/shipping/tech-defects/{id}', [TechDefectController::class,'destroy'])->name('tech-defects.destroy');

//Route::get('/shipping/tech-defects/{id}', 
//[TechDefectController::class,'show'])->name('tech-defects.show');

Route::get('/shipping/tech-defects/{id}', [App\Http\Controllers\TechDefectController::class,'show'])->name('tech-defects.show');
Route::post('/shipping/tech-defects/{id}/third-party', [TechDefectController::class,'storeThirdParty'])->name('thirdparty.store');
Route::post('/shipping/tech-defects/{id}/third-party', [App\Http\Controllers\ThirdPartyController::class,'store'])->name('third-party.store');
Route::post('/third-party/{id}', [App\Http\Controllers\ThirdPartyController::class,'store'])->name('third-party.store');

Route::prefix('shipping')->group(function(){
    Route::get('/voyage-logs', [VoyageLogController::class,'index']);
    Route::get('/voyage-logs/create/{vessel}', [VoyageLogController::class,'create']);
    Route::post('/voyage-logs/store', [VoyageLogController::class,'store']);
    Route::get('/voyage-logs/{id}', [VoyageLogController::class,'show']);
    Route::post('/voyage-logs/{id}/add-detail', [VoyageLogController::class,'addDetail']);
    Route::post('/voyage-logs/{id}/start', [VoyageLogController::class,'startTrail']);
    Route::post('/voyage-logs/{detail}/end', [VoyageLogController::class,'endTrail']);
    Route::post('/voyage-logs/{detail}/complete', [VoyageLogController::class,'completeTrail']);
   // Route::post('/shipping/voyage-logs/{id}/complete-voyage', [VoyageLogController::class,'completeVoyage']);
    //Route::post('/shipping/voyage-logs/{detail}/update-trail', [VoyageLogController::class,'updateTrail']);
    Route::get('/shipping/voyage-logs/dashboard', [VoyageLogController::class, 'dashboard'])->name('voyage-logs.dashboard');
    Route::get('/shipping/vessels/{vesselId}/logs', [VoyageLogController::class, 'index']);
    
});
Route::get('/shipping/voyage-logs/{id}/pdf', [VoyageLogController::class,'exportPdf']);
Route::post('/shipping/voyage-logs/{id}/complete-voyage', [VoyageLogController::class,'completeVoyage']);
Route::post('/shipping/voyage-logs/{detail}/update-trail', [VoyageLogController::class,'updateTrail']);
Route::post('/shipping/voyage-logs/{detailId}/update-trail', [VoyageLogController::class, 'updateTrail'])
    ->name('voyage-logs.update-trail');
Route::resource('vessel-certificates', VesselCertificateController::class);
Route::get('/vessel-certificates/{id}', [VesselCertificateController::class,'vesselCertificates'])
    ->name('vessel.certificates');
Route::get('/vessel-certificates/{id}', [VesselCertificateController::class,'show'])
    ->name('vessel.certificates.show');

Route::get('/vessel-certificates/add/{vessel}', 
[VesselCertificateController::class,'create'])
->name('vessel-certificates.add');

Route::get('/vessel-certificates/{id}/edit', [VesselCertificateController::class, 'edit'])
    ->name('vessel-certificates.edit');

Route::post('/vessel-certificates/{id}/update', [VesselCertificateController::class, 'update'])
    ->name('vessel-certificates.update');

Route::get('/shipping/vessel-certificates/dashboard', [VesselCertificateController::class,'dashboard'])->name('vessel-certificates.dashboard');










