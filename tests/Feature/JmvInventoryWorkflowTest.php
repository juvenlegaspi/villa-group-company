<?php
namespace Tests\Feature;
use App\Models\ItemInventoryHeader;use App\Models\ItemInventoryTransaction;use App\Models\JmvStockRequest;use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Http\UploadedFile;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Hash;use Illuminate\Support\Facades\Notification;use Illuminate\Support\Facades\Storage;use Tests\TestCase;
class JmvInventoryWorkflowTest extends TestCase{
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed();Notification::fake();}
 public function test_jmv_permissions_item_creation_and_controlled_release():void{
  $manager=$this->positionUser('inventory-manager');$warehouse=$this->positionUser('warehouse-staff');$locationId=DB::table('jmv_inventory_locations')->value('id');$categoryId=DB::table('jmv_inventory_categories')->where('name','General')->value('id');
  $this->actingAs($warehouse)->post(route('jmv.inventory.store'),[])->assertForbidden();
  $this->actingAs($manager)->post(route('jmv.inventory.store'),['item_name'=>'Hydraulic Oil','unit'=>'L','category_id'=>$categoryId,'default_location_id'=>$locationId,'minimum_quantity'=>10,'maximum_quantity'=>100,'stock_on_hand'=>50,'unit_cost'=>125.50])->assertRedirect(route('jmv.inventory.index'));
  $item=ItemInventoryHeader::where('item_name','Hydraulic Oil')->firstOrFail();$this->assertNotNull($item->item_code);$this->assertDatabaseHas('jmv_inventory_balances',['item_inventory_header_id'=>$item->id,'location_id'=>$locationId,'quantity'=>50]);
  $this->actingAs($warehouse)->post(route('jmv.requests.store'),['item_id'=>$item->id,'location_id'=>$locationId,'quantity'=>5,'purpose'=>'Excavator preventive maintenance'])->assertSessionHasNoErrors();$request=JmvStockRequest::firstOrFail();$this->assertSame('PENDING',$request->status);
  $this->actingAs($manager)->post(route('jmv.requests.approve',$request),['approval_remarks'=>'Authorized'])->assertSessionHasNoErrors();$this->actingAs($warehouse)->post(route('jmv.requests.release',$request))->assertSessionHasNoErrors();
  $this->assertSame(45,$item->fresh()->stock_on_hand);$this->assertDatabaseHas('jmv_stock_requests',['id'=>$request->id,'status'=>'RELEASED']);$this->assertDatabaseHas('item_inventory_transactions',['item_inventory_header_id'=>$item->id,'movement_kind'=>'REQUEST','quantity'=>5,'balance_after'=>45]);
 }
 public function test_stock_in_cannot_exceed_maximum_and_stock_out_cannot_exceed_location_balance():void{
  $manager=$this->positionUser('inventory-manager');$locationId=DB::table('jmv_inventory_locations')->value('id');$categoryId=DB::table('jmv_inventory_categories')->where('name','General')->value('id');
  $this->actingAs($manager)->post(route('jmv.inventory.store'),['item_name'=>'Grease','unit'=>'kg','category_id'=>$categoryId,'default_location_id'=>$locationId,'minimum_quantity'=>2,'maximum_quantity'=>10,'stock_on_hand'=>8])->assertSessionHasNoErrors();$item=ItemInventoryHeader::where('item_name','Grease')->firstOrFail();
  $this->actingAs($manager)->from(route('jmv.stockin.index'))->post(route('jmv.stockin.store'),['item_id'=>$item->id,'location_id'=>$locationId,'quantity'=>3,'reference_no'=>'DR-1','supplier_name'=>'Supplier','transacted_at'=>now()->format('Y-m-d H:i:s')])->assertSessionHasErrors('quantity');
  $this->actingAs($manager)->from(route('jmv.stockout.index'))->post(route('jmv.stockout.store'),['item_id'=>$item->id,'location_id'=>$locationId,'quantity'=>9,'reference_no'=>'ISS-1','issued_to'=>'Mining','purpose'=>'Operations','transacted_at'=>now()->format('Y-m-d H:i:s')])->assertSessionHasErrors('quantity');$this->assertSame(8,$item->fresh()->stock_on_hand);
 }
 public function test_stock_receipt_attachment_balances_and_private_download_work():void{
  Storage::fake('local');$manager=$this->positionUser('inventory-manager');[$item,$locationId]=$this->item($manager,20,100);
  $this->actingAs($manager)->post(route('jmv.stockin.store'),['item_id'=>$item->id,'location_id'=>$locationId,'quantity'=>15,'reference_no'=>'DR-100','supplier_name'=>'Mining Supply Co.','unit_cost'=>50,'transacted_at'=>now()->format('Y-m-d H:i:s'),'attachment'=>UploadedFile::fake()->image('delivery.jpg')])->assertRedirect(route('jmv.stockin.index'));
  $transaction=ItemInventoryTransaction::where('reference_no','DR-100')->firstOrFail();$this->assertSame(35,$item->fresh()->stock_on_hand);$this->assertDatabaseHas('jmv_inventory_balances',['item_inventory_header_id'=>$item->id,'location_id'=>$locationId,'quantity'=>35]);Storage::disk('local')->assertExists($transaction->attachment_path);$this->actingAs($manager)->get(route('jmv.transactions.attachment',$transaction))->assertOk();
 }
 public function test_adjustment_is_audited_and_does_not_rewrite_history():void{
  $manager=$this->positionUser('inventory-manager');[$item,$locationId]=$this->item($manager,50,100);$opening=ItemInventoryTransaction::where('item_inventory_header_id',$item->id)->firstOrFail();
  $this->actingAs($manager)->post(route('jmv.inventory.adjust',$item),['location_id'=>$locationId,'adjusted_balance'=>47,'reference_no'=>'COUNT-1','remarks'=>'Physical count variance'])->assertSessionHasNoErrors();
  $this->assertSame(47,$item->fresh()->stock_on_hand);$this->assertDatabaseHas('item_inventory_transactions',['item_inventory_header_id'=>$item->id,'movement_kind'=>'ADJUSTMENT','type'=>'OUT','quantity'=>3,'balance_after'=>47]);$this->assertDatabaseHas('item_inventory_transactions',['id'=>$opening->id,'movement_kind'=>'OPENING']);$this->assertDatabaseHas('jmv_inventory_audits',['item_inventory_header_id'=>$item->id,'action'=>'stock_adjusted']);
 }
 public function test_reversal_restores_balance_and_cannot_be_repeated():void{
  $manager=$this->positionUser('inventory-manager');[$item,$locationId]=$this->item($manager,50,100);
  $this->actingAs($manager)->post(route('jmv.stockout.store'),['item_id'=>$item->id,'location_id'=>$locationId,'quantity'=>10,'reference_no'=>'ISS-REV','issued_to'=>'Mine Site','purpose'=>'Repair','transacted_at'=>now()->format('Y-m-d H:i:s')])->assertSessionHasNoErrors();$original=ItemInventoryTransaction::where('reference_no','ISS-REV')->firstOrFail();$this->assertSame(40,$item->fresh()->stock_on_hand);
  $this->actingAs($manager)->post(route('jmv.transactions.reverse',$original),['reason'=>'Wrong item issued'])->assertSessionHasNoErrors();$this->assertSame(50,$item->fresh()->stock_on_hand);$this->assertDatabaseHas('item_inventory_transactions',['reversal_of_id'=>$original->id,'movement_kind'=>'REVERSAL','type'=>'IN','quantity'=>10]);
  $this->actingAs($manager)->post(route('jmv.transactions.reverse',$original),['reason'=>'Repeat'])->assertStatus(422);$this->assertSame(50,$item->fresh()->stock_on_hand);
 }
 public function test_report_and_application_visibility_follow_position_permissions():void{
  $warehouse=$this->positionUser('warehouse-staff');$procurement=$this->positionUser('purchaser');
  $this->actingAs($warehouse)->get(route('jmv.applications'))->assertOk()->assertSee('Stock In')->assertSee('Stock Requests')->assertDontSee('Inventory Report');
  $this->actingAs($warehouse)->get(route('jmv.inventory.report'))->assertForbidden();
  $this->actingAs($procurement)->get(route('jmv.applications'))->assertOk()->assertSee('Inventory Report')->assertDontSee('Stock In');
  $this->actingAs($procurement)->get(route('jmv.inventory.report'))->assertOk()->assertHeader('content-type','text/csv; charset=UTF-8');
 }
 private function item(User $manager,int $stock,int $maximum):array{$locationId=DB::table('jmv_inventory_locations')->value('id');$categoryId=DB::table('jmv_inventory_categories')->where('name','General')->value('id');static$i=0;$i++;$this->actingAs($manager)->post(route('jmv.inventory.store'),['item_name'=>'Test Item '.$i,'unit'=>'pcs','category_id'=>$categoryId,'default_location_id'=>$locationId,'minimum_quantity'=>5,'maximum_quantity'=>$maximum,'stock_on_hand'=>$stock,'unit_cost'=>10])->assertSessionHasNoErrors();return[ItemInventoryHeader::where('item_name','Test Item '.$i)->firstOrFail(),$locationId];}
 private function positionUser(string $code):User{$position=DB::table('positions')->where('code',$code)->where('division_id',DB::table('divisions')->where('name','JMV')->value('id'))->first();static$n=0;$n++;return User::create(['name'=>'JMV','lastname'=>'User','username'=>'jmv'.$n,'email'=>'jmv'.$n.'@example.test','cell_number'=>'09000000000','password'=>Hash::make('password'),'role'=>$position->legacy_role,'division_id'=>$position->division_id,'department_id'=>$position->department_id,'position_id'=>$position->id,'is_admin'=>false,'must_change_password'=>false,'status'=>true]);}
}
