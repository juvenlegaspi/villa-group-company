<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YatiraProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_seed_assigns_least_privilege_yatira_permissions(): void
    {
        $this->seed();

        $this->assertPositionHas('Procurement', 'procurement-manager', 'yatira.suppliers.manage');
        $this->assertPositionHas('Finance and Accounting', 'finance-manager', 'yatira.suppliers.view');
        $this->assertPositionLacks('Finance and Accounting', 'finance-manager', 'yatira.suppliers.manage');
        $this->assertPositionHas('Inventory and Warehouse', 'inventory-manager', 'yatira.assets.create');
        $this->assertPositionHas('Inventory and Warehouse', 'inventory-manager', 'yatira.assets.dispose');
        $this->assertPositionHas('Equipment and Maintenance', 'maintenance-manager', 'yatira.assets.update');
        $this->assertPositionLacks('Equipment and Maintenance', 'maintenance-manager', 'yatira.assets.dispose');
        $this->assertPositionHas('Inventory and Warehouse', 'warehouse-staff', 'yatira.consumables.manage');
        $this->assertPositionLacks('Human Resources', 'hr-manager', 'yatira.assets.view');
        $this->assertDatabaseMissing('permissions', ['slug' => 'yatira.assets.manage']);
    }

    public function test_supplier_tin_is_normalized_and_creation_is_audited(): void
    {
        $this->seed();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('suppliers.store'), $this->supplierPayload())
            ->assertSessionHasNoErrors();

        $supplierId = (int) DB::table('suppliers')->value('id');
        $this->assertDatabaseHas('suppliers', ['id' => $supplierId, 'normalized_tin' => '123456789']);
        $this->assertDatabaseHas('supplier_audits', ['supplier_id' => $supplierId, 'action' => 'created', 'user_id' => $admin->id]);

        $this->actingAs($admin)->from(route('suppliers.index'))->post(route('suppliers.store'), $this->supplierPayload([
            'name' => 'Duplicate Supplier',
            'tin' => '123 456 789',
        ]))->assertSessionHasErrors('normalized_tin');
        $this->assertDatabaseCount('suppliers', 1);
    }

    public function test_supplier_creation_returns_clear_field_and_contact_validation_errors(): void
    {
        $this->seed();
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->from(route('suppliers.index'))
            ->post(route('suppliers.store'), [
                '_form_context' => 'create_supplier',
                'status' => 1,
                'email' => 'not-an-email',
            ]);

        $response->assertRedirect(route('suppliers.index'))
            ->assertSessionHasErrors([
                'name',
                'business_type',
                'tin',
                'products',
                'contact_person',
                'email',
            ]);

        $this->actingAs($admin)
            ->from(route('suppliers.index'))
            ->post(route('suppliers.store'), [
                ...$this->supplierPayload(),
                '_form_context' => 'create_supplier',
                'telephone' => '',
                'mobile' => '',
                'email' => '',
            ])
            ->assertSessionHasErrors('contact_details')
            ->assertSessionDoesntHaveErrors(['telephone', 'mobile', 'email']);
    }

    public function test_consumable_movements_preserve_balances_and_prevent_negative_stock(): void
    {
        $this->seed();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('yatira.inventory.consumables.store'), [
            'item_name' => 'Safety Gloves',
            'unit' => 'pair',
            'opening_stock' => 10,
            'reorder_level' => 3,
        ])->assertSessionHasNoErrors();

        $itemId = (int) DB::table('yatira_consumables')->value('id');
        $this->actingAs($admin)->post(route('yatira.inventory.consumables.movement', $itemId), [
            'type' => 'OUT',
            'quantity' => 4,
            'reference_no' => 'ISSUE-001',
            'remarks' => 'Issued to project site',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('yatira_consumables', ['id' => $itemId, 'stock_on_hand' => 6]);
        $this->assertDatabaseHas('yatira_consumable_transactions', [
            'yatira_consumable_id' => $itemId,
            'type' => 'OUT',
            'quantity' => 4,
            'balance_before' => 10,
            'balance_after' => 6,
            'reference_no' => 'ISSUE-001',
        ]);

        $this->actingAs($admin)->from(route('yatira.inventory.consumables.show', $itemId))
            ->post(route('yatira.inventory.consumables.movement', $itemId), [
                'type' => 'OUT',
                'quantity' => 7,
                'reference_no' => 'ISSUE-002',
            ])->assertSessionHasErrors('quantity');
        $this->assertDatabaseHas('yatira_consumables', ['id' => $itemId, 'stock_on_hand' => 6]);
    }

    public function test_fixed_asset_can_be_saved_with_an_image_document(): void
    {
        $this->seed();
        $admin = $this->admin();
        Storage::fake('local');
        $otherDepartmentId = (int) DB::table('departments')
            ->where('division_id', $admin->division_id)
            ->where('name', 'Finance and Accounting')
            ->value('id');

        $response = $this->actingAs($admin)->post(route('yatira.inventory.fixed-assets.store'), [
            'asset_entry_type' => 'new',
            'asset_name' => 'Hydraulic Excavator',
            'serial_number' => 'HX-2026-001',
            'category' => 'HEAVY EQPT/MACHINERY',
            'location' => 'Project Site A',
            'asset_condition' => 'Good',
            'status' => 'Active',
            'date_acquired' => today()->toDateString(),
            'acquisition_cost' => 1250000,
            'assigned_user_id' => $admin->id,
            'assigned_department_id' => $otherDepartmentId,
            'document' => UploadedFile::fake()->image('asset-photo.jpg', 1200, 800),
        ]);

        $response->assertSessionHasNoErrors();
        $asset = DB::table('yatira_fixed_assets')->where('asset_name', 'Hydraulic Excavator')->first();
        $this->assertNotNull($asset);
        $this->assertNotNull($asset->document_path);
        $this->assertSame((int) $admin->department_id, (int) $asset->assigned_department_id);
        Storage::disk('local')->assertExists($asset->document_path);
        $this->assertDatabaseHas('yatira_fixed_asset_documents', [
            'yatira_fixed_asset_id' => $asset->id,
            'original_name' => 'asset-photo.jpg',
            'uploaded_by' => $admin->id,
        ]);
    }

    private function admin(): User
    {
        $divisionId = (int) DB::table('divisions')->where('name', 'Yatira')->value('id');
        $departmentId = (int) DB::table('departments')->where('division_id', $divisionId)->where('name', 'Information Technology')->value('id');
        if (! $departmentId) {
            $departmentId = DB::table('departments')->insertGetId([
                'division_id' => $divisionId,
                'name' => 'Information Technology',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return User::create([
            'name' => 'System',
            'lastname' => 'Administrator',
            'username' => 'yatira-admin',
            'email' => 'yatira-admin@example.test',
            'password' => Hash::make('villa@2026'),
            'role' => 'admin',
            'is_admin' => true,
            'must_change_password' => false,
            'status' => true,
            'division_id' => $divisionId,
            'department_id' => $departmentId,
        ]);
    }

    private function supplierPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Production Supply Co.',
            'business_type' => 'Corporation',
            'tin' => '123-456-789',
            'address' => 'Cebu City',
            'products' => 'Construction materials',
            'tax_type' => 'VAT',
            'lead_time' => 7,
            'credit_term' => 30,
            'limit_advances' => 50000,
            'contact_person' => 'Maria Santos',
            'mobile' => '09171234567',
            'status' => 1,
        ], $overrides);
    }

    private function assertPositionHas(string $department, string $positionCode, string $permission): void
    {
        $this->assertTrue($this->positionPermissions($department, $positionCode)->contains($permission), "{$positionCode} should have {$permission}.");
    }

    private function assertPositionLacks(string $department, string $positionCode, string $permission): void
    {
        $this->assertFalse($this->positionPermissions($department, $positionCode)->contains($permission), "{$positionCode} should not have {$permission}.");
    }

    private function positionPermissions(string $department, string $positionCode)
    {
        return DB::table('permissions')
            ->join('permission_position', 'permissions.id', '=', 'permission_position.permission_id')
            ->join('positions', 'permission_position.position_id', '=', 'positions.id')
            ->join('departments', 'positions.department_id', '=', 'departments.id')
            ->join('divisions', 'positions.division_id', '=', 'divisions.id')
            ->where('divisions.name', 'Yatira')
            ->where('departments.name', $department)
            ->where('positions.code', $positionCode)
            ->pluck('permissions.slug');
    }
}
