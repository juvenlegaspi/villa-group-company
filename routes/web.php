<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CertificateNotificationController;
use App\Http\Controllers\CrewVesselLocationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DryDockingHeaderController;
use App\Http\Controllers\FuelRobMonitoringController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\JmvDailyProductionController;
use App\Http\Controllers\JmvWorkspaceController;
use App\Http\Controllers\JmvStockRequestController;
use App\Http\Controllers\LayoutController;
use App\Http\Controllers\MapGeocodingController;
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

Route::prefix('vessel-location/{token}')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [CrewVesselLocationController::class, 'show'])->name('crew-location.show');
    Route::post('/', [CrewVesselLocationController::class, 'update'])
        ->middleware('throttle:12,1')
        ->name('crew-location.update');
    Route::post('/automatic', [CrewVesselLocationController::class, 'automaticUpdate'])
        ->middleware('throttle:12,1')
        ->name('crew-location.automatic');
    Route::get('/reverse', [CrewVesselLocationController::class, 'reverse'])
        ->middleware('throttle:30,1')
        ->name('crew-location.reverse');
});

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
        Route::get('/division/{division}/live-fleet', [DashboardController::class, 'shippingLiveFleet'])
            ->name('division.dashboard.live-fleet');
        Route::get('/division/{division}/weather', [DashboardController::class, 'shippingWeather'])
            ->middleware('throttle:120,1')
            ->name('division.dashboard.weather');

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
                    Route::post('/{event}/attachments', [ShippingCalendarController::class, 'storeAttachment'])->name('attachments.store');
                    Route::delete('/{event}/attachments', [ShippingCalendarController::class, 'destroyAttachments'])->name('attachments.destroy-scope');
                    Route::get('/{event}/attachments/{attachment}', [ShippingCalendarController::class, 'downloadAttachment'])->name('attachments.download');
                    Route::delete('/{event}/attachments/{attachment}', [ShippingCalendarController::class, 'destroyAttachment'])->name('attachments.destroy');
                    Route::put('/{event}', [ShippingCalendarController::class, 'update'])->name('update');
                    Route::patch('/{event}/move', [ShippingCalendarController::class, 'move'])->name('move');
                    Route::patch('/{event}/complete', [ShippingCalendarController::class, 'complete'])->name('complete');
                    Route::patch('/{event}/cancel', [ShippingCalendarController::class, 'cancel'])->name('cancel');
                    Route::delete('/{event}', [ShippingCalendarController::class, 'destroy'])->name('destroy');
                });
                Route::get('/applications/operations', [ShippingWorkspaceController::class, 'operations'])
                    ->middleware('module.access:shipping.operations.access')
                    ->name('shipping.operations');
                Route::get('/pms', [ShippingWorkspaceController::class, 'pms'])
                    ->middleware('module.access:shipping.operations.access')
                    ->name('shipping.pms.index');
                Route::middleware('module.access:shipping.operations.access')->prefix('pms/vessels/{vessel}')->name('shipping.pms.')->group(function (): void {
                    Route::get('/', [\App\Http\Controllers\ShippingPmsController::class, 'show'])->name('vessels.show');
                    Route::post('/headers', [\App\Http\Controllers\ShippingPmsController::class, 'storeHeader'])->name('headers.store');
                    Route::put('/headers/{header}', [\App\Http\Controllers\ShippingPmsController::class, 'updateHeader'])->name('headers.update');
                    Route::get('/headers/{header}', [\App\Http\Controllers\ShippingPmsController::class, 'details'])->name('headers.show');
                    Route::post('/headers/{header}/details', [\App\Http\Controllers\ShippingPmsController::class, 'storeDetail'])->name('details.store');
                });
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
                    Route::post('/location-portal/regenerate', [VesselController::class, 'regenerateLocationPortalLink'])
                        ->name('vessels.location-portal.regenerate');
                    Route::get('/{id}/logs/create', [VoyageLogController::class, 'create'])
                        ->middleware('module.access:shipping.voyages.access')
                        ->name('voyages.create');
                });

                Route::middleware('module.access:shipping.voyages.access')->prefix('voyage-logs')->group(function (): void {
                    Route::get('/dashboard', [VoyageLogController::class, 'dashboard'])->name('voyage-logs.dashboard');
                    Route::get('/fleet-map', [VoyageLogController::class, 'fleetMap'])->name('voyage-logs.fleet-map');
                    Route::get('/map/search', [MapGeocodingController::class, 'search'])->name('voyage-map.search');
                    Route::get('/map/reverse', [MapGeocodingController::class, 'reverse'])->name('voyage-map.reverse');
                    Route::post('/map/suggest-route', [VoyageLogController::class, 'suggestRoute'])
                        ->middleware('throttle:20,1')
                        ->name('voyage-map.suggest-route');
                    Route::post('/store', [VoyageLogController::class, 'store'])->name('voyages.store');
                    Route::get('/{id}/pdf', [VoyageLogController::class, 'exportPdf'])->name('voyage.pdf');
                    Route::get('/{id}', [VoyageLogController::class, 'show'])->name('voyages.show');
                    Route::post('/{id}/current-location', [VoyageLogController::class, 'updateCurrentLocation'])->name('voyage.current-location.update');
                    Route::post('/{id}/add-detail', [VoyageLogController::class, 'addDetail'])->name('voyage.addDetail');
                    Route::post('/{id}/complete-voyage', [VoyageLogController::class, 'completeVoyage'])->name('voyage.complete');
                    Route::post('/{detail}/add-activity', [VoyageLogController::class, 'addActivity'])->name('voyage.addActivity');
                    Route::post('/activity/{id}/end', [VoyageLogController::class, 'endActivity'])->name('voyage.activity.end');
                    Route::post('/activity/{id}/remarks', [VoyageLogController::class, 'updateOngoingActivityRemarks'])->name('voyage.activity.remarks');
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
                    Route::get('/reports/summary/excel', [TechDefectController::class, 'exportSummaryExcel'])->name('tech-defects.reports.excel');
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
                Route::get('/expiry-calendar', [VesselCertificateController::class, 'calendar'])->name('vessel-certificates.calendar');
                Route::get('/certificate/{certificate}/sub/create', [VesselCertificateController::class, 'createSub'])->name('vessel-certificates.sub.create');
                Route::post('/certificate/{certificate}/sub', [VesselCertificateController::class, 'storeSub'])->name('vessel-certificates.sub.store');
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
                Route::get('/inventory/fixed-assets/print/tags', [YatiraInventoryController::class, 'printAssetTags'])->middleware('module.access:yatira.assets.view')->name('yatira.inventory.fixed-assets.print-tags');
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
            Route::prefix('operations/daily-production')->name('jmv.operations.production.')->group(function (): void {
                Route::get('/', [JmvDailyProductionController::class, 'index'])->name('index');
                Route::post('/', [JmvDailyProductionController::class, 'store'])->name('store');
                Route::get('/export', [JmvDailyProductionController::class, 'export'])->name('export');
                Route::post('/targets', [JmvDailyProductionController::class, 'storeTarget'])->name('targets.store');
                Route::put('/targets/{target}', [JmvDailyProductionController::class, 'updateTarget'])->name('targets.update');
                Route::post('/{productionLog}/submit', [JmvDailyProductionController::class, 'submit'])->name('submit');
                Route::post('/{productionLog}/approve', [JmvDailyProductionController::class, 'approve'])->name('approve');
                Route::post('/{productionLog}/return', [JmvDailyProductionController::class, 'returnForCorrection'])->name('return');
                Route::get('/{productionLog}', [JmvDailyProductionController::class, 'show'])->name('show');
                Route::get('/{productionLog}/edit', [JmvDailyProductionController::class, 'edit'])->name('edit');
                Route::put('/{productionLog}', [JmvDailyProductionController::class, 'update'])->name('update');
                Route::get('/{productionLog}/attachments/{attachment}', [JmvDailyProductionController::class, 'attachment'])->name('attachments.show');
                Route::get('/{productionLog}/attachments/{attachment}/preview', [JmvDailyProductionController::class, 'previewAttachment'])->name('attachments.preview');
            });
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

        Route::middleware('division:Villa Group')->prefix('villa-group')->name('villa-group.')->group(function (): void {
            Route::get('/applications', [\App\Http\Controllers\VillaGroupWorkspaceController::class, 'applications'])->name('applications');
            Route::get('/it', [\App\Http\Controllers\VillaGroupWorkspaceController::class, 'it'])->middleware('villa-group.access:it')->name('it');
            Route::get('/hr', [\App\Http\Controllers\VillaGroupWorkspaceController::class, 'hr'])->middleware('villa-group.access:hr')->name('hr');
            Route::middleware('villa-group.access:properties')->prefix('properties')->name('properties.')->group(function (): void {
                Route::get('/', [\App\Http\Controllers\HaruhayPropertyController::class, 'index'])->name('index');
                Route::get('/create', [\App\Http\Controllers\HaruhayPropertyController::class, 'create'])->name('create');
                Route::post('/', [\App\Http\Controllers\HaruhayPropertyController::class, 'store'])->name('store');
                Route::get('/{property}/edit', [\App\Http\Controllers\HaruhayPropertyController::class, 'edit'])->name('edit');
                Route::put('/{property}', [\App\Http\Controllers\HaruhayPropertyController::class, 'update'])->name('update');
                Route::get('/{property}/attachments/{attachment}', [\App\Http\Controllers\HaruhayPropertyController::class, 'attachment'])->name('attachment');
                Route::get('/{property}', [\App\Http\Controllers\HaruhayPropertyController::class, 'show'])->name('show');
            });
        });

        foreach (['jmv' => 'JMV', 'shipping' => 'Villa shipping Lines'] as $companyKey => $companyName) {
            Route::middleware('division:'.$companyName)->prefix($companyKey.'/inventory')->name($companyKey.'.inventory.')->group(function () use ($companyKey): void {
                Route::get('/workspace', [\App\Http\Controllers\CompanyFixedAssetController::class, 'workspace'])->name('home');
                Route::prefix('fixed-assets')->name('fixed-assets.')->group(function () use ($companyKey): void {
                    Route::get('/', [\App\Http\Controllers\CompanyFixedAssetController::class, 'index'])->middleware('module.access:'.$companyKey.'.assets.view')->name('index');
                    Route::post('/', [\App\Http\Controllers\CompanyFixedAssetController::class, 'storeFixedAsset'])->middleware('module.access:'.$companyKey.'.assets.create')->name('store');
                    Route::get('/print/tags', [\App\Http\Controllers\CompanyFixedAssetController::class, 'printAssetTags'])->middleware('module.access:'.$companyKey.'.assets.view')->name('print-tags');
                    Route::get('/{fixedAsset}', [\App\Http\Controllers\CompanyFixedAssetController::class, 'showFixedAsset'])->middleware('module.access:'.$companyKey.'.assets.view')->name('show');
                    Route::put('/{fixedAsset}', [\App\Http\Controllers\CompanyFixedAssetController::class, 'updateFixedAsset'])->middleware('module.access:'.$companyKey.'.assets.update')->name('update');
                    Route::get('/{fixedAsset}/document', [\App\Http\Controllers\CompanyFixedAssetController::class, 'downloadAssetDocument'])->middleware('module.access:'.$companyKey.'.assets.view')->name('document');
                    Route::get('/{fixedAsset}/documents/{document}', [\App\Http\Controllers\CompanyFixedAssetController::class, 'downloadAssetDocumentVersion'])->middleware('module.access:'.$companyKey.'.assets.view')->name('documents.show');
                });
            });
        }
        Route::middleware('division:Haruhay')->prefix('haruhay/reservations')->name('haruhay.reservations.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayReservationController::class, 'index'])->name('index');
            Route::get('/live', [\App\Http\Controllers\HaruhayReservationController::class, 'live'])->middleware('throttle:60,1')->name('live');
            Route::get('/{reservation}', [\App\Http\Controllers\HaruhayReservationController::class, 'show'])->name('show');
            Route::post('/{reservation}/approve', [\App\Http\Controllers\HaruhayReservationController::class, 'approve'])->name('approve');
            Route::post('/{reservation}/close', [\App\Http\Controllers\HaruhayReservationController::class, 'close'])->name('close');
            Route::post('/{reservation}/message', [\App\Http\Controllers\HaruhayReservationController::class, 'updateMessage'])->name('message');
            Route::post('/{reservation}/reset-access', [\App\Http\Controllers\HaruhayReservationController::class, 'resetAccess'])->name('reset-access');
            Route::post('/{reservation}/cash', [\App\Http\Controllers\HaruhayReservationController::class, 'cash'])->name('cash');
            Route::post('/{reservation}/payments/{payment}/review', [\App\Http\Controllers\HaruhayReservationController::class, 'review'])->name('review');
            Route::get('/{reservation}/payments/{payment}/proof', [\App\Http\Controllers\HaruhayReservationController::class, 'proof'])->name('proof');
        });

        Route::view('/haruhay/applications', 'haruhay.applications')
            ->middleware('division:Haruhay')
            ->name('haruhay.applications');

        Route::middleware('division:Haruhay')->prefix('haruhay/units')->name('haruhay.units.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayUnitController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\HaruhayUnitController::class, 'store'])->name('store');
            Route::get('/{unit}/edit', [\App\Http\Controllers\HaruhayUnitController::class, 'edit'])->name('edit');
            Route::put('/{unit}', [\App\Http\Controllers\HaruhayUnitController::class, 'update'])->name('update');
            Route::get('/{unit}', [\App\Http\Controllers\HaruhayUnitController::class, 'show'])->name('show');
        });

        Route::middleware('division:Haruhay')->prefix('haruhay/tenants')->name('haruhay.tenants.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayTenantController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\HaruhayTenantController::class, 'store'])->name('store');
            Route::post('/{tenant}/confirm-move-in', [\App\Http\Controllers\HaruhayTenantController::class, 'confirm'])->name('confirm');
            Route::post('/{tenant}/cancel-reservation', [\App\Http\Controllers\HaruhayTenantController::class, 'cancel'])->name('cancel');
            Route::get('/{tenant}/edit', [\App\Http\Controllers\HaruhayTenantController::class, 'edit'])->name('edit');
            Route::put('/{tenant}', [\App\Http\Controllers\HaruhayTenantController::class, 'update'])->name('update');
            Route::get('/{tenant}', [\App\Http\Controllers\HaruhayTenantController::class, 'show'])->name('show');
        });

        Route::middleware('division:Haruhay')->prefix('haruhay/lease-contracts')->name('haruhay.contracts.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayLeaseContractController::class, 'index'])->name('index');
            Route::get('/tenants/{tenant}', [\App\Http\Controllers\HaruhayLeaseContractController::class, 'tenant'])->name('tenant');
            Route::post('/tenants/{tenant}', [\App\Http\Controllers\HaruhayLeaseContractController::class, 'store'])->name('store');
            Route::get('/tenants/{tenant}/{contract}/document', [\App\Http\Controllers\HaruhayLeaseContractController::class, 'document'])->name('document');
            Route::post('/tenants/{tenant}/{contract}/document', [\App\Http\Controllers\HaruhayLeaseContractController::class, 'upload'])->name('upload');
            Route::post('/tenants/{tenant}/{contract}/terminate', [\App\Http\Controllers\HaruhayLeaseContractController::class, 'terminate'])->name('terminate');
        });

        Route::middleware('division:Haruhay')->prefix('haruhay/billing')->name('haruhay.billing.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayBillingController::class, 'index'])->name('index');
            Route::get('/tenants/{tenant}', [\App\Http\Controllers\HaruhayBillingController::class, 'tenant'])->name('tenant');
            Route::post('/', [\App\Http\Controllers\HaruhayBillingController::class, 'store'])->name('store');
            Route::put('/{billing}', [\App\Http\Controllers\HaruhayBillingController::class, 'update'])->name('update');
            Route::post('/{billing}/issue', [\App\Http\Controllers\HaruhayBillingController::class, 'issue'])->name('issue');
            Route::post('/{billing}/retry-notifications', [\App\Http\Controllers\HaruhayBillingController::class, 'retryNotifications'])->name('notifications.retry');
            Route::get('/{billing}', [\App\Http\Controllers\HaruhayBillingController::class, 'show'])->name('show');
        });

        Route::middleware('division:Haruhay')->prefix('haruhay/collections')->name('haruhay.collections.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayCollectionController::class, 'index'])->name('index');
            Route::get('/transactions', [\App\Http\Controllers\HaruhayCollectionController::class, 'transactions'])->name('transactions');
            Route::get('/tenants/{tenant}', [\App\Http\Controllers\HaruhayCollectionController::class, 'tenant'])->name('tenant');
            Route::post('/tenants/{tenant}', [\App\Http\Controllers\HaruhayCollectionController::class, 'store'])->name('store');
            Route::post('/{collection}/apply', [\App\Http\Controllers\HaruhayCollectionController::class, 'apply'])->name('apply');
            Route::post('/{collection}/reverse', [\App\Http\Controllers\HaruhayCollectionController::class, 'reverse'])->name('reverse');
            Route::post('/{collection}/retry-notifications', [\App\Http\Controllers\HaruhayCollectionController::class, 'retryNotifications'])->name('notifications.retry');
            Route::get('/{collection}/proof', [\App\Http\Controllers\HaruhayCollectionController::class, 'proof'])->name('proof');
            Route::get('/{collection}', [\App\Http\Controllers\HaruhayCollectionController::class, 'show'])->name('show');
        });

        Route::middleware('division:Haruhay')->group(function (): void {
            Route::get('/haruhay/tenants/{tenant}/room-transfer', [\App\Http\Controllers\HaruhayRoomTransferController::class,'create'])->name('haruhay.transfers.create');
            Route::post('/haruhay/tenants/{tenant}/room-transfer', [\App\Http\Controllers\HaruhayRoomTransferController::class,'store'])->name('haruhay.transfers.store');
            Route::get('/haruhay/tenants/{tenant}/deposits', [\App\Http\Controllers\HaruhayDepositController::class,'index'])->name('haruhay.deposits.index');
            Route::post('/haruhay/tenants/{tenant}/deposits', [\App\Http\Controllers\HaruhayDepositController::class,'store'])->name('haruhay.deposits.store');
            Route::post('/haruhay/tenants/{tenant}/deposits/{entry}/reverse', [\App\Http\Controllers\HaruhayDepositController::class,'reverse'])->name('haruhay.deposits.reverse');
            Route::get('/haruhay/tenants/{tenant}/deposits/{entry}/proof', [\App\Http\Controllers\HaruhayDepositController::class,'proof'])->name('haruhay.deposits.proof');
            Route::get('/haruhay/staff-access', [\App\Http\Controllers\HaruhayStaffAccessController::class,'index'])->name('haruhay.access.index');
            Route::put('/haruhay/staff-access/{user}', [\App\Http\Controllers\HaruhayStaffAccessController::class,'update'])->name('haruhay.access.update');
        });

        Route::middleware('division:Haruhay')->prefix('haruhay/reports')->name('haruhay.reports.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\HaruhayReportController::class, 'index'])->name('index');
            Route::get('/export', [\App\Http\Controllers\HaruhayReportController::class, 'export'])->name('export');
        });

        Route::get('/haruhay/database-management/{section?}', function (string $section = 'tenants') {
            if ($section === 'reports') return to_route('haruhay.reports.index');
            if ($section === 'collections') return to_route('haruhay.collections.index');
            if ($section === 'billing') return to_route('haruhay.billing.index');
            if ($section === 'lease-contracts') return to_route('haruhay.contracts.index');
            if ($section === 'tenants') return to_route('haruhay.tenants.index');
            if ($section === 'units') return to_route('haruhay.units.index');
            return view('haruhay.database', compact('section'));
        })
            ->whereIn('section', ['tenants', 'units', 'lease-contracts', 'billing', 'collections', 'reports'])
            ->middleware('division:Haruhay')
            ->name('haruhay.database');

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
