<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const YATIRA_PERMISSIONS = [
        'yatira.suppliers.view' => 'View Yatira suppliers',
        'yatira.suppliers.manage' => 'Create and update Yatira suppliers',
        'yatira.assets.view' => 'View Yatira fixed assets',
        'yatira.assets.create' => 'Register Yatira fixed assets',
        'yatira.assets.update' => 'Update Yatira fixed assets',
        'yatira.assets.dispose' => 'Dispose Yatira fixed assets',
        'yatira.consumables.view' => 'View Yatira consumables',
        'yatira.consumables.manage' => 'Manage Yatira consumables and stock movements',
        'yatira.reports.view' => 'View and export Yatira reports',
    ];

    public function up(): void
    {
        Schema::table('yatira_consumables', function (Blueprint $table): void {
            $table->string('normalized_name')->nullable()->after('item_name');
        });

        Schema::create('yatira_fixed_asset_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('yatira_fixed_asset_id')->constrained('yatira_fixed_assets')->restrictOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['yatira_fixed_asset_id', 'created_at'], 'yatira_asset_document_timeline_index');
        });

        $this->ensurePermissionCatalog();
        $this->normalizeConsumables();

        $yatiraId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['yatira'])->value('id');
        if (! $yatiraId) {
            return;
        }

        $this->repairLegacyOrganizationLinks((int) $yatiraId);
        $this->normalizeLegacyYatiraData((int) $yatiraId);
        $this->seedPermissions((int) $yatiraId);
    }

    public function down(): void
    {
        Schema::dropIfExists('yatira_fixed_asset_documents');
        Schema::table('yatira_consumables', function (Blueprint $table): void {
            $table->dropUnique('yatira_consumable_name_unique');
            $table->dropColumn('normalized_name');
        });

        $permissionIds = DB::table('permissions')->whereIn('slug', ['yatira.assets.create', 'yatira.assets.update', 'yatira.assets.dispose'])->pluck('id');
        DB::table('permission_position')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('access_role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    private function repairLegacyOrganizationLinks(int $yatiraId): void
    {
        $mapping = [
            'accounting' => 'Finance and Accounting',
            'hr' => 'Human Resources',
            'it' => 'Information Technology',
        ];

        foreach ($mapping as $legacyName => $targetName) {
            $targetDepartmentId = DB::table('departments')->where('division_id', $yatiraId)
                ->whereRaw('LOWER(name) = ?', [strtolower($targetName)])->value('id');
            if (! $targetDepartmentId) {
                $targetDepartmentId = DB::table('departments')->insertGetId([
                    'division_id' => $yatiraId,
                    'name' => $targetName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $positionIds = DB::table('positions')->join('departments', 'positions.department_id', '=', 'departments.id')
                ->where('positions.division_id', $yatiraId)->whereRaw('LOWER(departments.name) = ?', [$legacyName])
                ->pluck('positions.id');

            if ($positionIds->isEmpty()) {
                continue;
            }

            DB::table('positions')->whereIn('id', $positionIds)->update(['department_id' => $targetDepartmentId, 'updated_at' => now()]);
            DB::table('users')->where('division_id', $yatiraId)->whereIn('position_id', $positionIds)
                ->update(['department_id' => $targetDepartmentId, 'updated_at' => now()]);
        }
    }

    private function normalizeLegacyYatiraData(int $yatiraId): void
    {
        DB::table('suppliers')->where('division_id', $yatiraId)->whereNull('normalized_tin')->orderBy('id')
            ->each(function (object $supplier): void {
                DB::table('suppliers')->where('id', $supplier->id)->update(['normalized_tin' => 'LEGACYMISSING'.$supplier->id]);
                DB::table('supplier_audits')->insert([
                    'supplier_id' => $supplier->id,
                    'user_id' => null,
                    'action' => 'legacy_tin_flagged',
                    'changes' => json_encode(['reason' => 'Original legacy TIN is missing and requires review'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            });

        $allowedCategories = ['HEAVY EQPT/MACHINERY', 'TRANSPORTATION EQUIPMENT', 'TOOLS AND SMALL EQPT', 'BUILDING AND FACILITY', 'FURNITURE AND OFFICE EQUIPMENTS', 'IT & SYSTEMS INFRASTRUCTURE', 'OTHER / LEGACY'];
        DB::table('yatira_fixed_assets')->where('division_id', $yatiraId)->whereNotIn('category', $allowedCategories)->orderBy('id')
            ->each(function (object $asset): void {
                $oldCategory = $asset->category;
                DB::table('yatira_fixed_assets')->where('id', $asset->id)->update(['category' => 'OTHER / LEGACY', 'updated_at' => now()]);
                DB::table('yatira_fixed_asset_audits')->insert([
                    'yatira_fixed_asset_id' => $asset->id,
                    'user_id' => null,
                    'action' => 'legacy_category_normalized',
                    'changes' => json_encode(['before' => $oldCategory, 'after' => 'OTHER / LEGACY'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            });

        DB::table('yatira_fixed_assets')->whereNotNull('document_path')->orderBy('id')->each(function (object $asset): void {
            DB::table('yatira_fixed_asset_documents')->insertOrIgnore([
                'yatira_fixed_asset_id' => $asset->id,
                'path' => $asset->document_path,
                'original_name' => $asset->document_original_name ?: basename($asset->document_path),
                'uploaded_by' => $asset->updated_by ?: $asset->created_by,
                'created_at' => $asset->updated_at ?: $asset->created_at ?: now(),
                'updated_at' => $asset->updated_at ?: $asset->created_at ?: now(),
            ]);
        });

    }

    private function normalizeConsumables(): void
    {
        DB::table('yatira_consumables')->orderBy('id')->each(function (object $item): void {
            $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $item->item_name)));
            if ($normalized === '') {
                $normalized = 'LEGACYITEM'.$item->id;
            }
            if (DB::table('yatira_consumables')->where('normalized_name', $normalized)->where('id', '!=', $item->id)->exists()) {
                $normalized .= 'DUP'.$item->id;
            }
            DB::table('yatira_consumables')->where('id', $item->id)->update(['normalized_name' => $normalized]);
        });
        Schema::table('yatira_consumables', fn (Blueprint $table) => $table->unique(['division_id', 'normalized_name'], 'yatira_consumable_name_unique'));
    }

    private function seedPermissions(int $yatiraId): void
    {
        $this->ensurePermissionCatalog();
        $permissionIds = DB::table('permissions')->whereIn('slug', array_keys(self::YATIRA_PERMISSIONS))->pluck('id', 'slug');
        $yatiraPermissionIds = $permissionIds->values();
        $positionIds = DB::table('positions')->where('division_id', $yatiraId)->pluck('id');
        DB::table('permission_position')->whereIn('position_id', $positionIds)->whereIn('permission_id', $yatiraPermissionIds)->delete();

        foreach (['system-administrator', 'company-administrator'] as $roleSlug) {
            $roleId = DB::table('access_roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) continue;
            foreach ($permissionIds as $permissionId) {
                DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }

        $rules = [
            'yatira.suppliers.view' => ['Procurement', 'Purchasing', 'Finance and Accounting'],
            'yatira.suppliers.manage' => ['Procurement', 'Purchasing'],
            'yatira.assets.view' => ['Project Operations', 'Engineering', 'Equipment and Maintenance', 'Inventory and Warehouse', 'Finance and Accounting'],
            'yatira.assets.create' => ['Inventory and Warehouse'],
            'yatira.assets.update' => ['Inventory and Warehouse', 'Equipment and Maintenance'],
            'yatira.assets.dispose' => ['Inventory and Warehouse'],
            'yatira.consumables.view' => ['Project Operations', 'Engineering', 'Equipment and Maintenance', 'Inventory and Warehouse'],
            'yatira.consumables.manage' => ['Inventory and Warehouse'],
            'yatira.reports.view' => ['Procurement', 'Purchasing', 'Finance and Accounting', 'Inventory and Warehouse'],
        ];

        foreach ($rules as $slug => $departments) {
            $query = DB::table('positions')->join('departments', 'positions.department_id', '=', 'departments.id')
                ->where('positions.division_id', $yatiraId)->where('positions.is_active', true)
                ->whereIn('departments.name', $departments)->select('positions.id', 'positions.code');

            foreach ($query->get() as $position) {
                $allowed = match ($slug) {
                    'yatira.suppliers.manage' => in_array($position->code, ['procurement-manager', 'purchaser'], true),
                    'yatira.assets.create', 'yatira.assets.dispose' => $position->code === 'inventory-manager',
                    'yatira.assets.update' => in_array($position->code, ['inventory-manager', 'maintenance-manager'], true),
                    'yatira.consumables.manage' => in_array($position->code, ['inventory-manager', 'warehouse-staff'], true),
                    default => true,
                };
                if ($allowed) {
                    DB::table('permission_position')->insertOrIgnore(['position_id' => $position->id, 'permission_id' => $permissionIds[$slug]]);
                }
            }
        }
    }

    private function ensurePermissionCatalog(): void
    {
        foreach (self::YATIRA_PERMISSIONS as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'module' => 'yatira',
                'description' => $name.'.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

    }
};
