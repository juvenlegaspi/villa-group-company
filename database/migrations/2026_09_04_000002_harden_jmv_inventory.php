<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'jmv.inventory.view' => 'View JMV inventory',
        'jmv.inventory.items.manage' => 'Manage JMV inventory items',
        'jmv.inventory.movements.manage' => 'Record JMV stock movements',
        'jmv.inventory.adjustments.manage' => 'Adjust and reverse JMV stock',
        'jmv.inventory.reports.view' => 'View and export JMV inventory reports',
        'jmv.inventory.requests.create' => 'Create JMV stock requests',
        'jmv.inventory.requests.approve' => 'Approve JMV stock requests',
    ];

    public function up(): void
    {
        Schema::create('jmv_inventory_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['division_id', 'name']);
        });
        Schema::create('jmv_inventory_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['division_id', 'code']);
        });

        Schema::table('item_inventory_header', function (Blueprint $table): void {
            $table->foreignId('division_id')->nullable()->after('id')->constrained('divisions')->restrictOnDelete();
            $table->string('item_code', 50)->nullable()->after('division_id');
            $table->string('normalized_name')->nullable()->after('item_name');
            $table->text('description')->nullable()->after('normalized_name');
            $table->foreignId('category_id')->nullable()->after('unit')->constrained('jmv_inventory_categories')->nullOnDelete();
            $table->foreignId('default_location_id')->nullable()->after('category_id')->constrained('jmv_inventory_locations')->nullOnDelete();
            $table->decimal('unit_cost', 15, 2)->nullable()->after('stock_on_hand');
            $table->string('preferred_supplier')->nullable()->after('unit_cost');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('jmv_inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_inventory_header_id')->constrained('item_inventory_header')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('jmv_inventory_locations')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['item_inventory_header_id', 'location_id'], 'jmv_item_location_unique');
        });

        Schema::table('item_inventory_transactions', function (Blueprint $table): void {
            $table->foreignId('location_id')->nullable()->after('item_inventory_header_id')->constrained('jmv_inventory_locations')->restrictOnDelete();
            $table->string('movement_kind', 30)->default('STANDARD')->after('type');
            $table->string('supplier_name')->nullable()->after('reference_no');
            $table->string('issued_to')->nullable()->after('supplier_name');
            $table->foreignId('department_id')->nullable()->after('issued_to')->constrained('departments')->nullOnDelete();
            $table->string('project_equipment')->nullable()->after('department_id');
            $table->string('purpose')->nullable()->after('project_equipment');
            $table->decimal('unit_cost', 15, 2)->nullable()->after('purpose');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('reversal_of_id')->nullable()->after('approved_at')->constrained('item_inventory_transactions')->restrictOnDelete();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->index(['location_id', 'transacted_at'], 'jmv_location_transaction_index');
        });

        Schema::create('jmv_inventory_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_inventory_header_id')->nullable()->constrained('item_inventory_header')->restrictOnDelete();
            $table->foreignId('item_inventory_transaction_id')->nullable()->constrained('item_inventory_transactions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->json('changes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['item_inventory_header_id', 'created_at'], 'jmv_item_audit_timeline');
        });

        $this->seedAndBackfill();
        $this->seedPermissions();
    }

    private function seedAndBackfill(): void
    {
        $divisionId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['jmv'])->value('id');
        if (! $divisionId) return;
        $categoryId = DB::table('jmv_inventory_categories')->insertGetId(['division_id' => $divisionId, 'name' => 'General', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['Fuel and Lubricants', 'Spare Parts', 'Tools and Equipment', 'Safety Supplies', 'Office Supplies'] as $name) {
            DB::table('jmv_inventory_categories')->insert(['division_id' => $divisionId, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $locationId = DB::table('jmv_inventory_locations')->insertGetId(['division_id' => $divisionId, 'code' => 'MAIN', 'name' => 'Main Warehouse', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('item_inventory_header')->orderBy('id')->each(function (object $item) use ($divisionId, $categoryId, $locationId): void {
            $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $item->item_name))) ?: 'LEGACY'.$item->id;
            if (DB::table('item_inventory_header')->where('normalized_name', $normalized)->exists()) $normalized .= 'DUP'.$item->id;
            DB::table('item_inventory_header')->where('id', $item->id)->update([
                'division_id' => $divisionId, 'item_code' => 'JMV-'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT),
                'normalized_name' => $normalized, 'category_id' => $categoryId, 'default_location_id' => $locationId,
                'updated_by' => $item->created_by, 'updated_at' => now(),
            ]);
            DB::table('jmv_inventory_balances')->insert(['item_inventory_header_id' => $item->id, 'location_id' => $locationId, 'quantity' => max(0, (int) $item->stock_on_hand), 'created_at' => now(), 'updated_at' => now()]);
        });
        DB::table('item_inventory_transactions')->whereNull('location_id')->update(['location_id' => $locationId]);
        Schema::table('item_inventory_header', function (Blueprint $table): void {
            $table->unique('item_code', 'jmv_item_code_unique');
            $table->unique(['division_id', 'normalized_name', 'unit'], 'jmv_item_name_unit_unique');
        });
    }

    private function seedPermissions(): void
    {
        foreach (self::PERMISSIONS as $slug => $name) DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => $name, 'module' => 'jmv', 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()]);
        $ids = DB::table('permissions')->whereIn('slug', array_keys(self::PERMISSIONS))->pluck('id', 'slug');
        foreach (['system-administrator', 'company-administrator'] as $role) {
            $roleId = DB::table('access_roles')->where('slug', $role)->value('id');
            if ($roleId) foreach ($ids as $id) DB::table('access_role_permission')->insertOrIgnore(['access_role_id' => $roleId, 'permission_id' => $id]);
        }
        $jmvId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['jmv'])->value('id');
        if (! $jmvId) return;
        $rules = [
            'inventory-manager' => array_keys(self::PERMISSIONS),
            'warehouse-staff' => ['jmv.inventory.view', 'jmv.inventory.movements.manage', 'jmv.inventory.requests.create'],
            'procurement-manager' => ['jmv.inventory.view', 'jmv.inventory.reports.view'],
            'purchaser' => ['jmv.inventory.view', 'jmv.inventory.reports.view'],
            'finance-manager' => ['jmv.inventory.view', 'jmv.inventory.reports.view'],
            'accountant' => ['jmv.inventory.view', 'jmv.inventory.reports.view'],
        ];
        foreach ($rules as $code => $slugs) foreach (DB::table('positions')->where('division_id', $jmvId)->where('code', $code)->pluck('id') as $positionId) foreach ($slugs as $slug) DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $ids[$slug]]);
    }

    public function down(): void
    {
        Schema::table('item_inventory_header', fn (Blueprint $table) => $table->dropUnique('jmv_item_name_unit_unique'));
        Schema::dropIfExists('jmv_inventory_audits');
        Schema::table('item_inventory_transactions', function (Blueprint $table): void {
            $table->dropForeign(['location_id']); $table->dropForeign(['department_id']); $table->dropForeign(['approved_by']); $table->dropForeign(['reversal_of_id']);
            $table->dropIndex('jmv_location_transaction_index');
            $table->dropColumn(['location_id','movement_kind','supplier_name','issued_to','department_id','project_equipment','purpose','unit_cost','approved_by','approved_at','reversal_of_id','attachment_path','attachment_original_name']);
        });
        Schema::dropIfExists('jmv_inventory_balances');
        Schema::table('item_inventory_header', function (Blueprint $table): void {
            $table->dropUnique('jmv_item_code_unique'); $table->dropForeign(['division_id']); $table->dropForeign(['category_id']); $table->dropForeign(['default_location_id']); $table->dropForeign(['updated_by']);
            $table->dropColumn(['division_id','item_code','normalized_name','description','category_id','default_location_id','unit_cost','preferred_supplier','updated_by']);
        });
        Schema::dropIfExists('jmv_inventory_locations'); Schema::dropIfExists('jmv_inventory_categories');
    }
};
