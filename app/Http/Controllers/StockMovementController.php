<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\ItemInventoryHeader;
use App\Models\ItemInventoryTransaction;
use App\Models\JmvInventoryAudit;
use App\Models\JmvInventoryBalance;
use App\Models\JmvInventoryLocation;
use App\Models\User;
use App\Notifications\JmvInventoryNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function stockIn(Request $request) { return $this->index($request, 'IN'); }
    public function stockOut(Request $request) { return $this->index($request, 'OUT'); }
    public function storeStockIn(Request $request) { return $this->storeMovement($request, 'IN'); }
    public function storeStockOut(Request $request) { return $this->storeMovement($request, 'OUT'); }

    private function index(Request $request, string $type)
    {
        $this->authorizePermission('jmv.inventory.view'); $divisionId = $this->jmvDivisionId();
        $items = ItemInventoryHeader::where('division_id', $divisionId)->where('status', true)->orderBy('item_name')->get();
        $locations = JmvInventoryLocation::where('division_id', $divisionId)->where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('division_id', $divisionId)->orderBy('name')->get();
        $movements = ItemInventoryTransaction::with(['item','user:id,name,lastname','location:id,name','department:id,name'])
            ->whereHas('item', fn ($query) => $query->where('division_id', $divisionId))->where('type', $type)
            ->when($request->filled('item_id'), fn ($query) => $query->where('item_inventory_header_id', $request->integer('item_id')))
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->integer('location_id')))
            ->latest('transacted_at')->latest('id')->paginate(15)->withQueryString();
        return view('jmv.stock_movements.index', compact('items','locations','departments','movements','type') + ['canManage' => $this->can('jmv.inventory.movements.manage'), 'canAdjust' => $this->can('jmv.inventory.adjustments.manage')]);
    }

    private function storeMovement(Request $request, string $type)
    {
        $this->authorizePermission('jmv.inventory.movements.manage'); $divisionId = $this->jmvDivisionId();
        $rules = [
            'item_id' => ['required', Rule::exists('item_inventory_header','id')->where(fn ($query) => $query->where('division_id',$divisionId)->where('status',true))],
            'location_id' => ['required', Rule::exists('jmv_inventory_locations','id')->where(fn ($query) => $query->where('division_id',$divisionId)->where('is_active',true))],
            'quantity' => 'required|integer|min:1', 'reference_no' => 'required|string|max:100', 'remarks' => 'nullable|string|max:2000',
            'transacted_at' => 'required|date|before_or_equal:now', 'unit_cost' => 'nullable|numeric|min:0|max:999999999999.99',
            'attachment' => 'nullable|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
        if ($type === 'IN') $rules['supplier_name'] = 'required|string|max:255';
        else { $rules['issued_to'] = 'required|string|max:255'; $rules['department_id'] = ['nullable', Rule::exists('departments','id')->where(fn ($query) => $query->where('division_id',$divisionId))]; $rules['project_equipment'] = 'nullable|string|max:255'; $rules['purpose'] = 'required|string|max:1000'; }
        $data = $request->validate($rules); $upload = [];
        if ($request->hasFile('attachment')) { $file = $request->file('attachment'); $upload = ['attachment_path' => $file->store('jmv/inventory','local'), 'attachment_original_name' => $file->getClientOriginalName()]; }
        try {
            DB::transaction(function () use ($data, $type, $upload): void {
                $item = ItemInventoryHeader::whereKey($data['item_id'])->lockForUpdate()->firstOrFail(); abort_unless($item->status, 422, 'Only active items can receive stock movements.');
                $balanceId = JmvInventoryBalance::firstOrCreate(['item_inventory_header_id'=>$item->id,'location_id'=>$data['location_id']], ['quantity'=>0])->id;
                $balance = JmvInventoryBalance::whereKey($balanceId)->lockForUpdate()->firstOrFail(); $before = (int) $item->stock_on_hand; $locationBefore = (int) $balance->quantity;
                if ($type === 'OUT' && $data['quantity'] > $locationBefore) throw ValidationException::withMessages(['quantity' => "Only {$locationBefore} {$item->unit} are available at this location."]);
                $after = $type === 'IN' ? $before + $data['quantity'] : $before - $data['quantity']; $locationAfter = $type === 'IN' ? $locationBefore + $data['quantity'] : $locationBefore - $data['quantity'];
                if ($type === 'IN' && $after > $item->maximum_quantity) throw ValidationException::withMessages(['quantity' => "This receipt exceeds the maximum stock level of {$item->maximum_quantity} {$item->unit}."]);
                $item->update(['stock_on_hand'=>$after,'unit_cost'=>$data['unit_cost'] ?? $item->unit_cost,'updated_by'=>auth()->id()]); $balance->update(['quantity'=>$locationAfter]);
                unset($data['item_id'], $data['attachment']);
                $transaction = ItemInventoryTransaction::create([...$data,...$upload,'item_inventory_header_id'=>$item->id,'type'=>$type,'movement_kind'=>'STANDARD','balance_before'=>$before,'balance_after'=>$after,'approved_by'=>auth()->id(),'approved_at'=>now(),'created_by'=>auth()->id()]);
                $this->audit($item, $transaction, $type === 'IN' ? 'stock_received' : 'stock_issued', ['location_before'=>$locationBefore,'location_after'=>$locationAfter]);
            });
        } catch (\Throwable $exception) { if (isset($upload['attachment_path'])) Storage::disk('local')->delete($upload['attachment_path']); throw $exception; }
        if ($type === 'OUT') $this->notifyLowStock(ItemInventoryHeader::find($request->integer('item_id')));
        return redirect()->route($type === 'IN' ? 'jmv.stockin.index' : 'jmv.stockout.index')->with('success','Stock movement recorded successfully.');
    }

    public function adjust(Request $request, ItemInventoryHeader $item)
    {
        $this->authorizePermission('jmv.inventory.adjustments.manage'); $this->ensureJmvItem($item);
        $data = $request->validate(['location_id'=>'required|exists:jmv_inventory_locations,id','adjusted_balance'=>'required|integer|min:0','reference_no'=>'required|string|max:100','remarks'=>'required|string|max:2000']);
        DB::transaction(function () use ($item,$data): void {
            $locked = ItemInventoryHeader::whereKey($item->id)->lockForUpdate()->firstOrFail(); $balance = JmvInventoryBalance::where('item_inventory_header_id',$item->id)->where('location_id',$data['location_id'])->lockForUpdate()->firstOrFail();
            $difference = $data['adjusted_balance'] - $balance->quantity; if ($difference === 0) throw ValidationException::withMessages(['adjusted_balance'=>'The adjusted balance must differ from the current balance.']);
            $before=$locked->stock_on_hand; $after=$before+$difference; if($after<0) throw ValidationException::withMessages(['adjusted_balance'=>'The adjustment would make total stock negative.']);
            $balance->update(['quantity'=>$data['adjusted_balance']]); $locked->update(['stock_on_hand'=>$after,'updated_by'=>auth()->id()]);
            $transaction=ItemInventoryTransaction::create(['item_inventory_header_id'=>$item->id,'location_id'=>$data['location_id'],'type'=>$difference>0?'IN':'OUT','movement_kind'=>'ADJUSTMENT','quantity'=>abs($difference),'balance_before'=>$before,'balance_after'=>$after,'reference_no'=>$data['reference_no'],'remarks'=>$data['remarks'],'transacted_at'=>now(),'created_by'=>auth()->id(),'approved_by'=>auth()->id(),'approved_at'=>now()]);
            $this->audit($locked,$transaction,'stock_adjusted');
        }); return back()->with('success','Stock balance adjusted with a permanent audit record.');
    }

    public function reverse(Request $request, ItemInventoryTransaction $transaction)
    {
        $this->authorizePermission('jmv.inventory.adjustments.manage'); $request->validate(['reason'=>'required|string|max:2000']); abort_unless((int)$transaction->item?->division_id===$this->jmvDivisionId(),404);
        DB::transaction(function () use ($transaction,$request): void {
            $original=ItemInventoryTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail(); abort_if(ItemInventoryTransaction::where('reversal_of_id',$original->id)->exists(),422,'This transaction has already been reversed.');
            $item=ItemInventoryHeader::whereKey($original->item_inventory_header_id)->lockForUpdate()->firstOrFail(); $balance=JmvInventoryBalance::where('item_inventory_header_id',$item->id)->where('location_id',$original->location_id)->lockForUpdate()->firstOrFail(); $inverse=$original->type==='IN'?'OUT':'IN';
            if($inverse==='OUT'&&($item->stock_on_hand<$original->quantity||$balance->quantity<$original->quantity)) throw ValidationException::withMessages(['reason'=>'The receipt cannot be reversed because its stock has already been issued.']);
            $before=$item->stock_on_hand; $after=$inverse==='IN'?$before+$original->quantity:$before-$original->quantity; $locationAfter=$inverse==='IN'?$balance->quantity+$original->quantity:$balance->quantity-$original->quantity;
            $item->update(['stock_on_hand'=>$after,'updated_by'=>auth()->id()]); $balance->update(['quantity'=>$locationAfter]);
            $reversal=ItemInventoryTransaction::create(['item_inventory_header_id'=>$item->id,'location_id'=>$original->location_id,'type'=>$inverse,'movement_kind'=>'REVERSAL','quantity'=>$original->quantity,'balance_before'=>$before,'balance_after'=>$after,'reference_no'=>'REV-'.$original->id,'remarks'=>$request->string('reason')->toString(),'transacted_at'=>now(),'created_by'=>auth()->id(),'approved_by'=>auth()->id(),'approved_at'=>now(),'reversal_of_id'=>$original->id]); $this->audit($item,$reversal,'transaction_reversed');
        }); return back()->with('success','Transaction reversed successfully.');
    }

    public function attachment(ItemInventoryTransaction $transaction)
    {
        $this->authorizePermission('jmv.inventory.view'); abort_unless((int)$transaction->item?->division_id===$this->jmvDivisionId(),404); abort_unless($transaction->attachment_path&&Storage::disk('local')->exists($transaction->attachment_path),404);
        return response()->download(Storage::disk('local')->path($transaction->attachment_path),$transaction->attachment_original_name);
    }
    private function notifyLowStock(?ItemInventoryHeader $item):void{if(!$item||!$item->status||$item->stock_on_hand>$item->minimum_quantity)return;$users=User::where('division_id',$this->jmvDivisionId())->where('status',true)->whereHas('position',fn($q)=>$q->whereIn('code',['inventory-manager','procurement-manager','purchaser']))->get();$label=$item->stock_on_hand<=0?'out of stock':'at or below its reorder level';try{Notification::send($users,new JmvInventoryNotification('JMV inventory stock alert',"{$item->item_code} — {$item->item_name} is {$label} ({$item->stock_on_hand} {$item->unit} remaining).",route('jmv.inventory.index',['search'=>$item->item_code])));}catch(\Throwable $e){report($e);}}
    private function can(string $permission):bool{return auth()->user()->isSystemAdministrator()||auth()->user()->hasPermission($permission);} private function authorizePermission(string $permission):void{abort_unless($this->can($permission),403,'Your department or position is not authorized for this JMV inventory action.');} private function jmvDivisionId():int{return(int)Division::whereRaw('LOWER(name) = ?',['jmv'])->value('id');} private function ensureJmvItem(ItemInventoryHeader $item):void{abort_unless((int)$item->division_id===$this->jmvDivisionId(),404);} private function audit($item,$transaction,string $action,array $changes=[]):void{JmvInventoryAudit::create(['item_inventory_header_id'=>$item->id,'item_inventory_transaction_id'=>$transaction->id,'user_id'=>auth()->id(),'action'=>$action,'changes'=>$changes?:['balance_before'=>$transaction->balance_before,'balance_after'=>$transaction->balance_after],'ip_address'=>request()->ip(),'user_agent'=>request()->userAgent(),'created_at'=>now()]);}
}
