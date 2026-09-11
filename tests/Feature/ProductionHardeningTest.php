<?php

namespace Tests\Feature;

use App\Models\ItemInventoryHeader;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('lastname');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('cell_number')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->string('role');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('item_inventory_header', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_name');
            $table->string('normalized_name')->nullable();
            $table->text('description')->nullable();
            $table->string('unit');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('default_location_id')->nullable();
            $table->integer('maximum_quantity');
            $table->integer('minimum_quantity');
            $table->integer('stock_on_hand');
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('preferred_supplier')->nullable();
            $table->timestamp('date_added');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
        Schema::create('jmv_inventory_locations', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('division_id'); $table->string('code'); $table->string('name'); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('jmv_inventory_balances', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('item_inventory_header_id'); $table->unsignedBigInteger('location_id'); $table->unsignedInteger('quantity')->default(0); $table->timestamps();
        });
        Schema::create('item_inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('item_inventory_header_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('type');
            $table->string('movement_kind')->default('STANDARD');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('balance_before');
            $table->unsignedInteger('balance_after');
            $table->string('reference_no')->nullable();
            $table->string('supplier_name')->nullable(); $table->string('issued_to')->nullable(); $table->unsignedBigInteger('department_id')->nullable(); $table->string('project_equipment')->nullable(); $table->string('purpose')->nullable(); $table->decimal('unit_cost',15,2)->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('transacted_at');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable(); $table->timestamp('approved_at')->nullable(); $table->unsignedBigInteger('reversal_of_id')->nullable(); $table->string('attachment_path')->nullable(); $table->string('attachment_original_name')->nullable();
            $table->timestamps();
        });
        Schema::create('jmv_inventory_audits', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('item_inventory_header_id')->nullable(); $table->unsignedBigInteger('item_inventory_transaction_id')->nullable(); $table->unsignedBigInteger('user_id')->nullable(); $table->string('action'); $table->json('changes')->nullable(); $table->string('ip_address')->nullable(); $table->text('user_agent')->nullable(); $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('item_inventory_transactions');
        Schema::dropIfExists('jmv_inventory_audits');
        Schema::dropIfExists('jmv_inventory_balances');
        Schema::dropIfExists('jmv_inventory_locations');
        Schema::dropIfExists('item_inventory_header');
        Schema::dropIfExists('users');
        Schema::dropIfExists('divisions');

        parent::tearDown();
    }

    public function test_temporary_password_blocks_direct_module_access(): void
    {
        $user = $this->user(['must_change_password' => true]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/change-password');
    }

    public function test_user_cannot_access_another_division_or_user_admin_routes(): void
    {
        $yatiraId = Schema::getConnection()->table('divisions')->insertGetId([
            'name' => 'Yatira', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = $this->user(['division_id' => $yatiraId]);

        $this->actingAs($user)->get('/shipping/vessels')->assertForbidden();
        $this->actingAs($user)->post('/users/'.$user->id.'/update', [])->assertForbidden();
    }

    public function test_legacy_voyage_endpoints_are_not_registered(): void
    {
        $admin = $this->user(['is_admin' => true]);

        $this->actingAs($admin)->get('/shipping/voyage-logs')->assertNotFound();
        $this->actingAs($admin)->get('/shipping/vessels/1/logs/1/edit')->assertNotFound();
    }

    public function test_stock_out_is_atomic_and_cannot_make_stock_negative(): void
    {
        $admin = $this->user(['is_admin' => true]);
        $jmvId = Schema::getConnection()->table('divisions')->insertGetId(['name'=>'JMV','created_at'=>now(),'updated_at'=>now()]);
        $locationId = Schema::getConnection()->table('jmv_inventory_locations')->insertGetId(['division_id'=>$jmvId,'code'=>'MAIN','name'=>'Main Warehouse','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $item = ItemInventoryHeader::create([
            'division_id' => $jmvId, 'item_code' => 'JMV-TEST', 'normalized_name' => 'ENGINEOIL', 'default_location_id' => $locationId,
            'item_name' => 'Engine Oil',
            'unit' => 'L',
            'maximum_quantity' => 100,
            'minimum_quantity' => 10,
            'stock_on_hand' => 20,
            'date_added' => now(),
            'created_by' => $admin->id,
            'status' => 1,
        ]);
        Schema::getConnection()->table('jmv_inventory_balances')->insert(['item_inventory_header_id'=>$item->id,'location_id'=>$locationId,'quantity'=>20,'created_at'=>now(),'updated_at'=>now()]);

        $this->actingAs($admin)->post(route('jmv.stockout.store'), [
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => 5,
            'reference_no' => 'ISSUE-1', 'issued_to' => 'Maintenance', 'purpose' => 'Service',
            'transacted_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('jmv.stockout.index'));

        $this->assertDatabaseHas('item_inventory_header', ['id' => $item->id, 'stock_on_hand' => 15]);
        $this->assertDatabaseHas('item_inventory_transactions', [
            'item_inventory_header_id' => $item->id,
            'type' => 'OUT',
            'quantity' => 5,
            'balance_before' => 20,
            'balance_after' => 15,
        ]);

        $this->actingAs($admin)->from(route('jmv.stockout.index'))->post(route('jmv.stockout.store'), [
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => 99,
            'reference_no' => 'ISSUE-2', 'issued_to' => 'Maintenance', 'purpose' => 'Service',
            'transacted_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('item_inventory_header', ['id' => $item->id, 'stock_on_hand' => 15]);
        $this->assertDatabaseCount('item_inventory_transactions', 1);
    }

    public function test_certificate_alert_trigger_is_not_a_get_endpoint(): void
    {
        $admin = $this->user(['is_admin' => true]);

        $this->actingAs($admin)->get('/certificate-alerts/send')->assertMethodNotAllowed();
    }

    public function test_private_local_storage_has_no_generic_web_route(): void
    {
        $this->get('/storage/certificates/example.pdf')->assertNotFound();
    }

    private function user(array $overrides = []): User
    {
        static $number = 0;
        $number++;

        return User::create(array_merge([
            'name' => 'Test',
            'lastname' => 'User',
            'username' => 'user'.$number,
            'email' => 'user'.$number.'@example.test',
            'cell_number' => '09000000000',
            'password' => Hash::make('password-for-tests'),
            'is_admin' => false,
            'role' => 'staff',
            'department_id' => null,
            'division_id' => null,
            'must_change_password' => false,
            'status' => true,
        ], $overrides));
    }
}
