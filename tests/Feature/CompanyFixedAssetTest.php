<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\User;
use App\Models\YatiraFixedAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyFixedAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_company_asset_migration_supports_imported_schema_without_legacy_unique_index(): void
    {
        \Illuminate\Support\Facades\Schema::table('yatira_fixed_assets', fn ($table) => $table->dropUnique('company_fixed_asset_code_unique'));
        $migration = require database_path('migrations/2026_11_13_094924_enable_company_fixed_assets.php');
        $before = DB::table('yatira_fixed_assets')->count();
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('yatira_fixed_assets', 'company_fixed_asset_code_unique'));
        $migration->up();
        $this->assertSame($before, DB::table('yatira_fixed_assets')->count());
    }

    private function user(string $company, string $position = 'inventory-manager', bool $admin = false): User
    {
        $division = Division::whereRaw('LOWER(name) = ?', [strtolower($company)])->firstOrFail();
        $job = DB::table('positions')->where('division_id', $division->id)->where('code', $position)->first();

        return User::create(['name' => 'Asset Tester', 'lastname' => 'Test', 'username' => (string) str()->uuid(), 'email' => str()->uuid().'@example.test', 'password' => 'test-password-123', 'role' => $admin ? 'admin' : 'staff', 'is_admin' => $admin, 'status' => true, 'must_change_password' => false, 'division_id' => $division->id, 'department_id' => $job->department_id, 'position_id' => $job->id]);
    }

    private function data(array $override = []): array
    {
        return $override + ['asset_entry_type' => 'new', 'asset_name' => 'Company Test Laptop', 'serial_number' => 'TEST-SERIAL', 'category' => 'IT & SYSTEMS INFRASTRUCTURE', 'asset_condition' => 'Good', 'status' => 'Active', 'location' => 'Test Office', 'date_acquired' => today()->toDateString(), 'acquisition_cost' => '45000.50', 'remarks' => 'Test asset'];
    }

    private function createAsset(string $key, array $override = []): YatiraFixedAsset
    {
        $this->post(route($key.'.inventory.fixed-assets.store'), $this->data($override))->assertSessionHasNoErrors()->assertRedirect();

        return YatiraFixedAsset::latest('id')->firstOrFail();
    }

    public function test_both_companies_have_the_same_registration_documents_edit_and_branded_print_flow(): void
    {
        foreach (['jmv' => ['JMV', 'JMV-', 'jmv.webp'], 'shipping' => ['Villa shipping Lines', 'VSL-', 'villa-shipping-lines.webp']] as $key => [$company,$prefix,$logo]) {
            $actor = $this->user($company);
            $this->actingAs($actor);
            $this->get(route($key.'.inventory.home'))->assertOk()->assertSee('Fixed Assets');
            $this->get(route($key.'.inventory.fixed-assets.index'))->assertOk()->assertSee('Fixed Asset Table')->assertSee('Add Fixed Asset');
            $asset = $this->createAsset($key, ['document' => UploadedFile::fake()->image('asset-first.png'), 'assigned_user_id' => $actor->id]);
            $this->assertStringStartsWith($prefix, $asset->asset_code);
            $this->assertSame($actor->division_id, $asset->division_id);
            $this->assertSame($actor->department_id, $asset->assigned_department_id);
            Storage::disk('local')->assertExists($asset->document_path);
            $this->get(route($key.'.inventory.fixed-assets.show', $asset))->assertOk()->assertSee('Audit Trail')->assertSee(route($key.'.inventory.fixed-assets.print-tags', ['from_asset_id' => $asset->id, 'to_asset_id' => $asset->id, 'copies' => 1, 'include_disposed' => 1]));
            $this->put(route($key.'.inventory.fixed-assets.update', $asset), $this->data(['asset_name' => 'Updated Company Laptop', 'document' => UploadedFile::fake()->image('asset-second.png')]))->assertSessionHasNoErrors();
            $this->assertSame(2, $asset->documents()->count());
            foreach ($asset->documents as $document) {
                $this->get(route($key.'.inventory.fixed-assets.documents.show', [$asset, $document]))->assertOk();
            }
            $this->get(route($key.'.inventory.fixed-assets.print-tags', ['from_asset_id' => $asset->id, 'to_asset_id' => $asset->id, 'copies' => 2]))->assertOk()->assertSee('2 total tags')->assertSee($logo)->assertSee('Updated Company Laptop');
        }
    }

    public function test_company_scoping_blocks_foreign_details_updates_documents_prints_and_assignees(): void
    {
        $jmv = $this->user('JMV');
        $shipping = $this->user('Villa shipping Lines');
        $this->actingAs($jmv);
        $asset = $this->createAsset('jmv', ['document' => UploadedFile::fake()->image('private.png')]);
        $this->actingAs($shipping);
        $own = $this->createAsset('shipping');
        $this->get(route('shipping.inventory.fixed-assets.index'))->assertOk()->assertViewHas('fixedAssets', fn ($assets) => $assets->total() === 1 && $assets->first()->id === $own->id);
        $this->get(route('shipping.inventory.fixed-assets.show', $asset))->assertNotFound();
        $this->put(route('shipping.inventory.fixed-assets.update', $asset), $this->data())->assertNotFound();
        $this->get(route('shipping.inventory.fixed-assets.document', $asset))->assertNotFound();
        $this->get(route('shipping.inventory.fixed-assets.documents.show', [$own, $asset->documents()->first()]))->assertNotFound();
        $this->get(route('shipping.inventory.fixed-assets.print-tags', ['from_asset_id' => $asset->id, 'to_asset_id' => $asset->id, 'copies' => 1]))->assertSessionHasErrors('from_asset_id');
        $this->post(route('shipping.inventory.fixed-assets.store'), $this->data(['assigned_user_id' => $jmv->id]))->assertSessionHasErrors('assigned_user_id');
        $this->get(route('jmv.inventory.fixed-assets.index'))->assertForbidden();
    }

    public function test_manual_codes_are_unique_within_each_company_and_may_overlap_across_companies(): void
    {
        foreach (['jmv' => 'JMV', 'shipping' => 'Villa shipping Lines', 'yatira' => 'Yatira'] as $key => $company) {
            $this->actingAs($this->user($company));
            $asset = $this->createAsset($key, ['asset_entry_type' => 'existing', 'asset_code' => 'LEGACY-001']);
            $this->assertSame('LEGACY-001', $asset->asset_code);
            $this->post(route($key.'.inventory.fixed-assets.store'), $this->data(['asset_entry_type' => 'existing', 'asset_code' => 'LEGACY-001']))->assertSessionHasErrors('asset_code');
        }
        $this->assertSame(3, YatiraFixedAsset::where('asset_code', 'LEGACY-001')->count());
    }

    public function test_view_only_positions_cannot_create_update_or_dispose_assets(): void
    {
        foreach (['jmv' => 'JMV', 'shipping' => 'Villa shipping Lines'] as $key => $company) {
            $manager = $this->user($company);
            $this->actingAs($manager);
            $asset = $this->createAsset($key);
            $viewer = $this->user($company, 'accountant');
            $this->actingAs($viewer);
            $this->get(route($key.'.inventory.fixed-assets.index'))->assertOk()->assertDontSee('id="addFixedAssetModal"', false);
            $this->post(route($key.'.inventory.fixed-assets.store'), $this->data())->assertForbidden();
            $this->put(route($key.'.inventory.fixed-assets.update', $asset), $this->data())->assertForbidden();
            $staff = $this->user($company, 'warehouse-staff');
            $this->actingAs($staff);
            $this->put(route($key.'.inventory.fixed-assets.update', $asset), $this->data(['status' => 'Disposed', 'disposal_reason' => 'Old equipment']))->assertForbidden();
            $this->actingAs($manager)->put(route($key.'.inventory.fixed-assets.update', $asset), $this->data(['status' => 'Disposed', 'disposal_reason' => 'Old equipment']))->assertSessionHasNoErrors();
            $this->actingAs($staff)->put(route($key.'.inventory.fixed-assets.update', $asset), $this->data())->assertForbidden();
        }
    }

    public function test_print_limits_disposed_exclusion_and_pagination_match_yatira(): void
    {
        $actor = $this->user('JMV');
        $this->actingAs($actor);
        $first = $this->createAsset('jmv');
        $last = $first;
        for ($i = 0; $i < 24; $i++) {
            $last = $first->replicate(['asset_code']);
            $last->asset_code = 'JMV-TEST-'.$i;
            $last->save();
        }
        $this->get(route('jmv.inventory.fixed-assets.index'))->assertOk()->assertViewHas('fixedAssets', fn ($items) => $items->total() === 25 && $items->perPage() === 10);
        $this->get(route('jmv.inventory.fixed-assets.print-tags', ['from_asset_id' => $first->id, 'to_asset_id' => $last->id, 'copies' => 10]))->assertSessionHasErrors('copies');
        $last->update(['status' => 'Disposed']);
        $this->get(route('jmv.inventory.fixed-assets.print-tags', ['from_asset_id' => $last->id, 'to_asset_id' => $last->id, 'copies' => 1]))->assertSessionHasErrors('from_asset_id');
        $this->get(route('jmv.inventory.fixed-assets.print-tags', ['from_asset_id' => $last->id, 'to_asset_id' => $last->id, 'copies' => 1, 'include_disposed' => 1]))->assertOk()->assertSee('1 total tags');
    }

    public function test_existing_jmv_stock_inventory_and_shipping_inventory_links_remain_available(): void
    {
        $this->actingAs($this->user('JMV'));
        $this->get(route('jmv.inventory.index'))->assertOk()->assertSee('Inventory Control');
        $this->get(route('jmv.applications'))->assertOk()->assertSee(route('jmv.inventory.home'), false);
        $this->actingAs($this->user('Villa shipping Lines'));
        $this->get(route('shipping.applications'))->assertOk()->assertSee(route('shipping.inventory.home'), false);
        $this->get(route('shipping.application.placeholder', 'inventory'))->assertRedirect(route('shipping.inventory.home'));
    }
}
