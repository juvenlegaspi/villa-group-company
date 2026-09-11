<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('division_id')->nullable()->after('id')->constrained('divisions')->nullOnDelete();
            $table->string('normalized_tin')->nullable()->after('tin');
            $table->foreignId('updated_by')->nullable()->after('added_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->index(['division_id', 'status'], 'supplier_division_status_index');
        });

        Schema::table('yatira_fixed_assets', function (Blueprint $table): void {
            $table->foreignId('division_id')->nullable()->after('id')->constrained('divisions')->nullOnDelete();
            $table->string('serial_number')->nullable()->after('asset_name');
            $table->decimal('acquisition_cost', 14, 2)->nullable()->after('date_acquired');
            $table->foreignId('assigned_user_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_department_id')->nullable()->after('assigned_user_id')->constrained('departments')->nullOnDelete();
            $table->string('document_path')->nullable()->after('remarks');
            $table->string('document_original_name')->nullable()->after('document_path');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('disposed_at')->nullable()->after('updated_by');
            $table->text('disposal_reason')->nullable()->after('disposed_at');
            $table->softDeletes();
            $table->index(['division_id', 'status', 'asset_condition'], 'yatira_asset_status_index');
        });

        Schema::create('supplier_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['supplier_id', 'created_at']);
        });

        Schema::create('yatira_fixed_asset_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('yatira_fixed_asset_id')->constrained('yatira_fixed_assets')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['yatira_fixed_asset_id', 'created_at'], 'yatira_asset_audit_timeline_index');
        });

        Schema::create('yatira_consumables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('item_code')->unique();
            $table->string('item_name');
            $table->string('unit', 50);
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['division_id', 'status', 'stock_on_hand'], 'yatira_consumable_stock_index');
        });

        Schema::create('yatira_consumable_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('yatira_consumable_id')->constrained('yatira_consumables')->restrictOnDelete();
            $table->enum('type', ['IN', 'OUT', 'ADJUSTMENT']);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('balance_before');
            $table->unsignedInteger('balance_after');
            $table->string('reference_no', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('transacted_at');
            $table->timestamps();
            $table->index(['yatira_consumable_id', 'transacted_at'], 'yatira_consumable_timeline_index');
        });

        $yatiraId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['yatira'])->value('id');
        if ($yatiraId) {
            DB::table('suppliers')->whereNull('division_id')->update(['division_id' => $yatiraId]);
            DB::table('yatira_fixed_assets')->whereNull('division_id')->update(['division_id' => $yatiraId]);
        }

        $usedNormalizedTins = [];
        DB::table('suppliers')->orderBy('id')->each(function (object $supplier) use (&$usedNormalizedTins): void {
            $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $supplier->tin)));
            if ($normalized !== '' && isset($usedNormalizedTins[$normalized])) {
                $normalized = 'LEGACYDUP'.$supplier->id.$normalized;
            }
            if ($normalized !== '') {
                $usedNormalizedTins[$normalized] = true;
            }
            DB::table('suppliers')->where('id', $supplier->id)->update(['normalized_tin' => $normalized ?: null]);
            DB::table('supplier_audits')->insert([
                'supplier_id' => $supplier->id, 'user_id' => $supplier->added_by,
                'action' => 'legacy_record_registered',
                'changes' => json_encode(['source' => 'pre-audit supplier record'], JSON_THROW_ON_ERROR),
                'created_at' => $supplier->created_at ?? now(),
            ]);
        });
        Schema::table('suppliers', fn (Blueprint $table) => $table->unique('normalized_tin', 'suppliers_normalized_tin_unique'));

        DB::table('yatira_fixed_assets')->orderBy('id')->each(function (object $asset): void {
            DB::table('yatira_fixed_asset_audits')->insert([
                'yatira_fixed_asset_id' => $asset->id, 'user_id' => $asset->created_by,
                'action' => 'legacy_record_registered',
                'changes' => json_encode(['source' => 'pre-audit fixed asset record'], JSON_THROW_ON_ERROR),
                'created_at' => $asset->created_at ?? now(),
            ]);
        });

        $permissions = [
            'yatira.suppliers.view' => 'View Yatira suppliers',
            'yatira.suppliers.manage' => 'Create and update Yatira suppliers',
            'yatira.assets.view' => 'View Yatira fixed assets',
            'yatira.assets.manage' => 'Create and update Yatira fixed assets',
            'yatira.consumables.view' => 'View Yatira consumables',
            'yatira.consumables.manage' => 'Manage Yatira consumables and stock movements',
            'yatira.reports.view' => 'View and export Yatira reports',
        ];
        foreach ($permissions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'module' => 'yatira', 'description' => $name.'.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys($permissions))->pluck('id', 'slug');
        foreach (['system-administrator', 'company-administrator'] as $roleSlug) {
            $roleId = DB::table('access_roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) continue;
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        if ($yatiraId) {
            $allPositionIds = DB::table('positions')->where('division_id', $yatiraId)->where('is_active', true)->pluck('id');
            foreach ($allPositionIds as $positionId) {
                foreach (['yatira.suppliers.view', 'yatira.assets.view', 'yatira.consumables.view'] as $slug) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$slug]]);
                }
            }

            $supplierManagers = DB::table('positions')->where('division_id', $yatiraId)
                ->whereIn('code', ['procurement-manager', 'purchaser', 'department-manager'])->pluck('id');
            foreach ($supplierManagers as $positionId) {
                foreach (['yatira.suppliers.manage', 'yatira.reports.view'] as $slug) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$slug]]);
                }
            }

            $assetManagers = DB::table('positions')->where('division_id', $yatiraId)
                ->whereIn('code', ['inventory-manager', 'maintenance-manager', 'department-manager'])->pluck('id');
            foreach ($assetManagers as $positionId) {
                foreach (['yatira.assets.manage', 'yatira.reports.view'] as $slug) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds[$slug]]);
                }
            }

            $stockManagers = DB::table('positions')->where('division_id', $yatiraId)
                ->whereIn('code', ['inventory-manager', 'warehouse-staff', 'department-manager'])->pluck('id');
            foreach ($stockManagers as $positionId) {
                DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionIds['yatira.consumables.manage']]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->where('module', 'yatira')->pluck('id');
        DB::table('permission_position')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('access_role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('yatira_consumable_transactions');
        Schema::dropIfExists('yatira_consumables');
        Schema::dropIfExists('yatira_fixed_asset_audits');
        Schema::dropIfExists('supplier_audits');

        Schema::table('yatira_fixed_assets', function (Blueprint $table): void {
            $table->dropIndex('yatira_asset_status_index');
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('assigned_department_id');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['serial_number', 'acquisition_cost', 'document_path', 'document_original_name', 'disposed_at', 'disposal_reason', 'deleted_at']);
        });
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique('suppliers_normalized_tin_unique');
            $table->dropIndex('supplier_division_status_index');
            $table->dropConstrainedForeignId('division_id');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['normalized_tin', 'deleted_at']);
        });
    }
};
