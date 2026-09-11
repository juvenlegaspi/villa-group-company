<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CertificateNotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DryDockingHeaderController;
use App\Http\Controllers\FuelRobMonitoringController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JmvWorkspaceController;
use App\Http\Controllers\JmvStockRequestController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ShippingWorkspaceController;
use App\Http\Controllers\ShippingCalendarController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TechDefectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VesselCertificateController;
use App\Http\Controllers\VesselController;
use App\Http\Controllers\VoyageLogController;
use App\Http\Controllers\YatiraInventoryController;
use App\Http\Controllers\YatiraSalesController;
use App\Http\Controllers\YatiraWorkspaceController;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('/login', 'login')->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:5,1']);
Route::middleware('guest')->group(function (): void {
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:3,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.store');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::view('/change-password', 'auth.change-password')->name('password.change');
    Route::post('/change-password', [AuthController::class, 'updatePassword'])->name('password.update');
    Route::get('/profile/avatar', [AuthController::class, 'avatar'])->name('profile.avatar');

    Route::middleware(['password.changed', 'owner.dashboard-only'])->group(function (): void {
        Route::view('/profile', 'profile')->name('profile');
        Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/companies', [DashboardController::class, 'companies'])->name('companies');
        Route::get('/division/{division}', [DashboardController::class, 'divisionDashboard'])
            ->name('division.dashboard');

        Route::middleware('admin')->group(function (): void {
            Route::get('/departments/{id}', function ($id) {
                $department = Department::findOrFail($id);
                $users = User::where('department_id', $id)->get();

                return view('departments.show', [
                    'department' => $department,
                    'users' => $users,
                    'totalUsers' => $users->count(),
                    'totalAdmins' => $users->where('role', 'admin')->count(),
                    'totalStaff' => $users->where('role', 'staff')->count(),
                ]);
            })->name('departments.show');

            Route::middleware('user.manager')->prefix('users')->name('users.')->group(function (): void {
                Route::get('/', [UserController::class, 'index'])->name('index');
                Route::get('/create', [UserController::class, 'create'])->name('create');
                Route::post('/', [UserController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [UserController::class, 'edit'])->name('edit');
                Route::post('/{id}/update', [UserController::class, 'update'])->name('update');
                Route::post('/{id}/delete', [UserController::class, 'destroy'])->name('destroy');
                Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
                Route::post('/{id}/change-password', [UserController::class, 'changePassword'])->name('change-password');
                Route::get('/organization/setup', [OrganizationController::class, 'index'])->name('organization.index');
                Route::post('/organization/departments', [OrganizationController::class, 'storeDepartment'])->name('organization.departments.store');
                Route::post('/organization/positions', [OrganizationController::class, 'storePosition'])->name('organization.positions.store');
            });
        });

        Route::middleware('division:Villa shipping Lines')->group(function (): void {
            Route::prefix('shipping')->group(function (): void {
                Route::get('/dashboard', [DashboardController::class, 'shippingDashboard'])
                    ->middleware('admin')
                    ->name('shipping.dashboard');
                Route::get('/applications', [ShippingWorkspaceController::class, 'applications'])
                    ->name('shipping.applications');
                Route::get('/calendar', [ShippingCalendarController::class, 'index'])->name('shipping.calendar');
                Route::prefix('calendar/events')->name('shipping.calendar.events.')->group(function (): void {
                    Route::get('/', [ShippingCalendarController::class, 'events'])->name('index');
                    Route::post('/', [ShippingCalendarController::class, 'store'])->name('store');
                    Route::get('/{event}', [ShippingCalendarController::class, 'show'])->name('show');
                    Route::get('/{event}/attachments/{attachment}', [ShippingCalendarController::class, 'downloadAttachment'])->name('attachments.download');
                    Route::delete('/{event}/attachments/{attachment}', [ShippingCalendarController::class, 'destroyAttachment'])->name('attachments.destroy');
                    Route::put('/{event}', [ShippingCalendarController::class, 'update'])->name('update');
                    Route::patch('/{event}/move', [ShippingCalendarController::class, 'move'])->name('move');
                    Route::patch('/{event}/cancel', [ShippingCalendarController::class, 'cancel'])->name('cancel');
                    Route::delete('/{event}', [ShippingCalendarController::class, 'destroy'])->name('destroy');
                });
                Route::get('/applications/operations', [ShippingWorkspaceController::class, 'operations'])
                    ->middleware('module.access:shipping.operations.access')
                    ->name('shipping.operations');
                Route::get('/applications/{application}', [ShippingWorkspaceController::class, 'comingSoon'])
                    ->whereIn('application', ['procurement', 'inventory'])
                    ->name('shipping.application.placeholder');

                Route::middleware('module.access:shipping.vessel_management.access')->prefix('vessels')->group(function (): void {
                    Route::get('/', [VesselController::class, 'index'])->name('vessels.index');
                    Route::get('/create', [VesselController::class, 'create'])->name('vessels.create');
                    Route::post('/', [VesselController::class, 'store'])->name('vessels.store');
                    Route::get('/{id}', [VesselController::class, 'show'])->name('vessels.show');
                    Route::get('/{id}/edit', [VesselController::class, 'edit'])->name('vessels.edit');
                    Route::put('/{id}', [VesselController::class, 'update'])->name('vessels.update');
                    Route::get('/{id}/logs/create', [VoyageLogController::class, 'create'])
                        ->middleware('module.access:shipping.voyages.access')
                        ->name('voyages.create');
                });

                Route::middleware('module.access:shipping.voyages.access')->prefix('voyage-logs')->group(function (): void {
                    Route::get('/dashboard', [VoyageLogController::class, 'dashboard'])->name('voyage-logs.dashboard');
                    Route::post('/store', [VoyageLogController::class, 'store'])->name('voyages.store');
                    Route::get('/{id}/pdf', [VoyageLogController::class, 'exportPdf'])->name('voyage.pdf');
                    Route::get('/{id}', [VoyageLogController::class, 'show'])->name('voyages.show');
                    Route::post('/{id}/add-detail', [VoyageLogController::class, 'addDetail'])->name('voyage.addDetail');
                    Route::post('/{id}/complete-voyage', [VoyageLogController::class, 'completeVoyage'])->name('voyage.complete');
                    Route::post('/{detail}/add-activity', [VoyageLogController::class, 'addActivity'])->name('voyage.addActivity');
                    Route::post('/activity/{id}/end', [VoyageLogController::class, 'endActivity'])->name('voyage.activity.end');
                    Route::post('/activity/{id}/update', [VoyageLogController::class, 'updateActivity'])->name('voyage.activity.update');
                    Route::get('/activity/{id}/attachment', [VoyageLogController::class, 'downloadActivityAttachment'])
                        ->name('voyage.activity.attachment');
                    Route::post('/detail/{id}/complete', [VoyageLogController::class, 'completeStatus'])->name('voyage.status.complete');
                    Route::post('/{detail}/update-status', [VoyageLogController::class, 'updateStatus'])->name('voyage.status.update');
                });

                Route::middleware('module.access:shipping.technical_defects.access')->prefix('tech-defects')->group(function (): void {
                    Route::get('/', [TechDefectController::class, 'index'])->name('tech-defects.index');
                    Route::get('/create', [TechDefectController::class, 'create'])->name('tech-defects.create');
                    Route::post('/', [TechDefectController::class, 'store'])->name('tech-defects.store');
                    Route::get('/dashboard', [TechDefectController::class, 'dashboard'])->name('tech-defects.dashboard');
                    Route::get('/reports/summary/pdf', [TechDefectController::class, 'exportSummaryPdf'])->name('tech-defects.reports.pdf');
                    Route::get('/reports/summary/csv', [TechDefectController::class, 'exportSummaryCsv'])->name('tech-defects.reports.csv');
                    Route::post('/{id}/attachments', [TechDefectController::class, 'storeAttachment'])->name('tech-defects.attachments.store');
                    Route::get('/{id}/attachments/{attachment}', [TechDefectController::class, 'attachment'])->name('tech-defects.attachments.show');
                    Route::get('/{id}/pdf', [TechDefectController::class, 'exportPdf'])->name('tech-defects.pdf');
                    Route::get('/{id}', [TechDefectController::class, 'show'])->name('tech-defects.show');
                    Route::get('/{id}/edit', [TechDefectController::class, 'edit'])->name('tech-defects.edit');
                    Route::get('/{id}/repair-closeout', [TechDefectController::class, 'editRepairCloseout'])->name('tech-defects.repair-closeout');
                    Route::put('/{id}', [TechDefectController::class, 'update'])->name('tech-defects.update');
                    Route::delete('/{id}', [TechDefectController::class, 'destroy'])->name('tech-defects.destroy');
                    Route::post('/{id}/restore', [TechDefectController::class, 'restore'])->name('tech-defects.restore');
                });

                Route::get('/vessel-certificates/dashboard', [VesselCertificateController::class, 'dashboard'])
                    ->middleware('module.access:shipping.certificates.access')
                    ->name('vessel-certificates.dashboard');

                Route::middleware('module.access:shipping.dry_docking.access')->prefix('dry-docking')->group(function (): void {
                    Route::get('/', [DryDockingHeaderController::class, 'index'])->name('dry-docking.index');
                    Route::get('/create', [DryDockingHeaderController::class, 'create'])->name('dry-docking.create');
                    Route::post('/', [DryDockingHeaderController::class, 'store'])->name('dry-docking.store');
                    Route::get('/{id}/details', [DryDockingHeaderController::class, 'details'])->name('dry-docking.details');
                    Route::post('/{id}/details', [DryDockingHeaderController::class, 'storeDetails'])->name('dry-docking.details.store');
                });
            });

            Route::middleware('module.access:shipping.certificates.access')->prefix('vessel-certificates')->group(function (): void {
                Route::get('/', [VesselCertificateController::class, 'index'])->name('vessel-certificates.index');
                Route::post('/', [VesselCertificateController::class, 'store'])->name('vessel-certificates.store');
                Route::get('/add/{vessel}', [VesselCertificateController::class, 'create'])->name('vessel-certificates.add');
                Route::get('/certificate/{certificate}/renew', [VesselCertificateController::class, 'renew'])->name('vessel-certificates.renew');
                Route::post('/certificate/{certificate}/renew', [VesselCertificateController::class, 'storeRenewal'])->name('vessel-certificates.renew.store');
                Route::get('/certificate/{certificate}/documents/{document}', [VesselCertificateController::class, 'downloadVersion'])
                    ->name('vessel-certificates.documents.version');
                Route::get('/{id}', [VesselCertificateController::class, 'show'])->name('vessel.certificates.show');
                Route::get('/{id}/edit', [VesselCertificateController::class, 'edit'])->name('vessel-certificates.edit');
                Route::post('/{id}/update', [VesselCertificateController::class, 'update'])->name('vessel-certificates.update');
                Route::get('/{id}/document', [VesselCertificateController::class, 'downloadDocument'])
                    ->name('vessel-certificates.document');
            });

            Route::post('/certificate-alerts/send', [CertificateNotificationController::class, 'sendAlerts'])
                ->middleware(['module.access:shipping.certificates.access', 'throttle:2,1'])
                ->name('certificate-alerts.send');

            Route::post('/fuel-rob/store', [FuelRobMonitoringController::class, 'store'])->middleware('module.access:shipping.voyages.access')->name('fuel.rob.store');
            Route::post('/fuel-bunkering/store', [FuelRobMonitoringController::class, 'fuelBunkering'])->middleware('module.access:shipping.voyages.access')->name('fuel.bunkering.store');
        });

        Route::middleware('division:Yatira')->group(function (): void {
            Route::prefix('yatira')->group(function (): void {
                Route::get('/applications', [YatiraWorkspaceController::class, 'applications'])->name('yatira.applications');
                Route::get('/sales', [YatiraSalesController::class, 'index'])->name('yatira.sales.index');
                Route::post('/sales', [YatiraSalesController::class, 'store'])->name('yatira.sales.store');
                Route::get('/sales/criteria', [YatiraSalesController::class, 'criteria'])->name('yatira.sales.criteria');
                Route::get('/sales/{lead}', [YatiraSalesController::class, 'show'])->name('yatira.sales.show');
                Route::put('/sales/{lead}', [YatiraSalesController::class, 'update'])->name('yatira.sales.update');
                Route::post('/sales/{lead}/stage', [YatiraSalesController::class, 'updateStage'])->name('yatira.sales.stage');
                Route::post('/sales/{lead}/cancel', [YatiraSalesController::class, 'cancel'])->name('yatira.sales.cancel');
                Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('module.access:yatira.suppliers.view')->name('suppliers.index');
                Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('module.access:yatira.suppliers.manage')->name('suppliers.store');
                Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('module.access:yatira.suppliers.view')->name('suppliers.show');
                Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('module.access:yatira.suppliers.manage')->name('suppliers.update');
                Route::get('/inventory', [YatiraInventoryController::class, 'index'])->name('yatira.inventory.index');
                Route::post('/inventory/fixed-assets', [YatiraInventoryController::class, 'storeFixedAsset'])->middleware('module.access:yatira.assets.create')->name('yatira.inventory.fixed-assets.store');
                Route::get('/inventory/fixed-assets/{fixedAsset}', [YatiraInventoryController::class, 'showFixedAsset'])->middleware('module.access:yatira.assets.view')->name('yatira.inventory.fixed-assets.show');
                Route::put('/inventory/fixed-assets/{fixedAsset}', [YatiraInventoryController::class, 'updateFixedAsset'])->middleware('module.access:yatira.assets.update')->name('yatira.inventory.fixed-assets.update');
                Route::get('/inventory/fixed-assets/{fixedAsset}/document', [YatiraInventoryController::class, 'downloadAssetDocument'])->middleware('module.access:yatira.assets.view')->name('yatira.inventory.fixed-assets.document');
                Route::get('/inventory/fixed-assets/{fixedAsset}/documents/{document}', [YatiraInventoryController::class, 'downloadAssetDocumentVersion'])->middleware('module.access:yatira.assets.view')->name('yatira.inventory.fixed-assets.documents.show');
                Route::post('/inventory/consumables', [YatiraInventoryController::class, 'storeConsumable'])->middleware('module.access:yatira.consumables.manage')->name('yatira.inventory.consumables.store');
                Route::get('/inventory/consumables/{consumable}', [YatiraInventoryController::class, 'showConsumable'])->middleware('module.access:yatira.consumables.view')->name('yatira.inventory.consumables.show');
                Route::put('/inventory/consumables/{consumable}', [YatiraInventoryController::class, 'updateConsumable'])->middleware('module.access:yatira.consumables.manage')->name('yatira.inventory.consumables.update');
                Route::post('/inventory/consumables/{consumable}/movement', [YatiraInventoryController::class, 'moveConsumable'])->middleware('module.access:yatira.consumables.manage')->name('yatira.inventory.consumables.movement');
            });
        });
        Route::get('/supplier/report', [DashboardController::class, 'exportSupplierReport'])->name('supplier.report');

        Route::middleware('division:JMV')->prefix('jmv')->group(function (): void {
            Route::get('/applications', [JmvWorkspaceController::class, 'applications'])->name('jmv.applications');
            Route::get('/inventory', [InventoryController::class, 'index'])->name('jmv.inventory.index');
            Route::post('/inventory', [InventoryController::class, 'store'])->name('jmv.inventory.store');
            Route::put('/inventory/{item}', [InventoryController::class, 'update'])->name('jmv.inventory.update');
            Route::get('/inventory-report', [InventoryController::class, 'report'])->name('jmv.inventory.report');
            Route::post('/inventory/{item}/adjust', [StockMovementController::class, 'adjust'])->name('jmv.inventory.adjust');
            Route::post('/transactions/{transaction}/reverse', [StockMovementController::class, 'reverse'])->name('jmv.transactions.reverse');
            Route::get('/transactions/{transaction}/attachment', [StockMovementController::class, 'attachment'])->name('jmv.transactions.attachment');
            Route::get('/stock-requests', [JmvStockRequestController::class, 'index'])->name('jmv.requests.index');
            Route::post('/stock-requests', [JmvStockRequestController::class, 'store'])->name('jmv.requests.store');
            Route::post('/stock-requests/{stockRequest}/approve', [JmvStockRequestController::class, 'approve'])->name('jmv.requests.approve');
            Route::post('/stock-requests/{stockRequest}/reject', [JmvStockRequestController::class, 'reject'])->name('jmv.requests.reject');
            Route::post('/stock-requests/{stockRequest}/release', [JmvStockRequestController::class, 'release'])->name('jmv.requests.release');
            Route::get('/stock-in', [StockMovementController::class, 'stockIn'])->name('jmv.stockin.index');
            Route::post('/stock-in', [StockMovementController::class, 'storeStockIn'])->name('jmv.stockin.store');
            Route::get('/stock-out', [StockMovementController::class, 'stockOut'])->name('jmv.stockout.index');
            Route::post('/stock-out', [StockMovementController::class, 'storeStockOut'])->name('jmv.stockout.store');
        });

        Route::middleware('division:HYVE')->prefix('hyve')->name('hyve.')->group(function (): void {
            Route::get('/booking', [BookingController::class, 'index'])->name('projects.index');
            Route::post('/booking/{bookingHeader}/approve', [BookingController::class, 'approve'])
                ->middleware('throttle:20,1')
                ->name('projects.approve');
            Route::get('/booking/{bookingHeader}/proof', [BookingController::class, 'proof'])->name('projects.proof');
            Route::get('/layout', [LayoutController::class, 'index'])->name('layout.index');
        });
    });
});
