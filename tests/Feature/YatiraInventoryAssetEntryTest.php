<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class YatiraInventoryAssetEntryTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastname');
            $table->string('role');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('yatira_fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('asset_name');
            $table->string('category');
            $table->string('assigned_to')->nullable();
            $table->string('location')->nullable();
            $table->string('asset_condition');
            $table->string('status');
            $table->date('date_acquired')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        $this->admin = User::query()->create([
            'name' => 'Inventory',
            'lastname' => 'Admin',
            'role' => 'admin',
            'is_admin' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('yatira_fixed_assets');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_new_asset_receives_an_auto_generated_code(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('yatira.inventory.fixed-assets.store'),
            $this->validPayload(['asset_entry_type' => 'new'])
        );

        $response->assertRedirect(route('yatira.inventory.index'));

        $assetCode = Schema::getConnection()
            ->table('yatira_fixed_assets')
            ->value('asset_code');

        $this->assertMatchesRegularExpression('/^YC-[a-z]{2}\d{4}$/', $assetCode);
    }

    public function test_existing_tagged_asset_keeps_its_manual_code(): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('yatira.inventory.fixed-assets.store'),
            $this->validPayload([
                'asset_entry_type' => 'existing',
                'asset_code' => 'OLD-TAG-001',
            ])
        );

        $response->assertRedirect(route('yatira.inventory.index'));
        $this->assertDatabaseHas('yatira_fixed_assets', [
            'asset_code' => 'OLD-TAG-001',
        ]);
    }

    public function test_existing_tagged_asset_requires_a_code(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('yatira.inventory.index'))
            ->post(
                route('yatira.inventory.fixed-assets.store'),
                $this->validPayload([
                    'asset_entry_type' => 'existing',
                    'asset_code' => '',
                ])
            );

        $response->assertRedirect(route('yatira.inventory.index'));
        $response->assertSessionHasErrors('asset_code');
        $this->assertDatabaseCount('yatira_fixed_assets', 0);
    }

    public function test_existing_tagged_asset_code_must_be_unique(): void
    {
        $existingAsset = $this->validPayload();
        unset($existingAsset['asset_entry_type']);

        Schema::getConnection()->table('yatira_fixed_assets')->insert([
            ...$existingAsset,
            'asset_code' => 'OLD-TAG-001',
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('yatira.inventory.index'))
            ->post(
                route('yatira.inventory.fixed-assets.store'),
                $this->validPayload([
                    'asset_entry_type' => 'existing',
                    'asset_code' => 'OLD-TAG-001',
                ])
            );

        $response->assertRedirect(route('yatira.inventory.index'));
        $response->assertSessionHasErrors('asset_code');
        $this->assertDatabaseCount('yatira_fixed_assets', 1);
    }

    public function test_inventory_dashboard_summarizes_asset_conditions(): void
    {
        $this->insertAsset('TAG-001', 'Good');
        $this->insertAsset('TAG-002', 'Good');
        $this->insertAsset('TAG-003', 'Needs Repair');

        $response = $this->actingAs($this->admin)->get(
            route('yatira.inventory.index')
        );

        $response->assertOk();
        $response->assertViewHas('fixedAssetStats', [
            'total' => 3,
            'condition_counts' => [
                'Good' => 2,
                'Needs Repair' => 1,
            ],
            'status_counts' => [
                'Active' => 3,
            ],
        ]);
        $response->assertSee('Condition');
        $response->assertSee('Status');
        $response->assertSee('2 (66.7%)');
        $response->assertSee('1 (33.3%)');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'asset_entry_type' => 'new',
            'asset_name' => 'Office Chair',
            'category' => 'FURNITURE AND OFFICE EQUIPMENTS',
            'assigned_to' => null,
            'location' => 'Main Office',
            'asset_condition' => 'Good',
            'status' => 'Active',
            'date_acquired' => '2026-07-29',
            'remarks' => null,
        ], $overrides);
    }

    private function insertAsset(string $assetCode, string $condition): void
    {
        $asset = $this->validPayload(['asset_condition' => $condition]);
        unset($asset['asset_entry_type']);

        Schema::getConnection()->table('yatira_fixed_assets')->insert([
            ...$asset,
            'asset_code' => $assetCode,
            'created_by' => $this->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
