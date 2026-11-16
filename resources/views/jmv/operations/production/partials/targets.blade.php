<section id="production-targets" class="mb-4 rounded-2xl border border-orange-200 bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 class="text-xs font-black uppercase tracking-wide text-orange-700">General production target</h2><span class="text-[11px] text-slate-500">Applies to all mines and shifts | 1 Bagon = {{ number_format(config('jmv.production_mt_per_bagon'), 3) }} MT</span></div>
    @if($errors->productionTarget->any())<div class="mb-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-800" role="alert">@foreach($errors->productionTarget->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-3 text-xs">
            <div class="flex overflow-hidden rounded-lg border border-orange-400"><span class="bg-orange-100 px-3 py-2 font-bold text-orange-900">DAILY TARGET</span><strong class="border-l border-orange-300 px-3 py-2">{{ $productionTarget ? number_format($productionTarget->coal_target).' Bagons' : 'Not set' }}</strong><span class="border-l border-orange-300 px-3 py-2">{{ $productionTarget ? number_format($productionTarget->dailyMt(), 3).' MT' : '—' }}</span></div>
            <div class="flex overflow-hidden rounded-lg border border-orange-400"><span class="bg-orange-100 px-3 py-2 font-bold text-orange-900">WEEKLY TARGET</span><strong class="border-l border-orange-300 px-3 py-2">{{ $productionTarget ? number_format($productionTarget->weeklyBagons()).' Bagons' : 'Not set' }}</strong><span class="border-l border-orange-300 px-3 py-2">{{ $productionTarget ? number_format($productionTarget->weeklyMt(), 3).' MT' : '—' }}</span></div>
        </div>
        @if($canManageTargets)<button type="button" class="rounded-lg border border-orange-300 bg-orange-50 px-3 py-2 text-xs font-bold text-orange-900" data-bs-toggle="modal" data-bs-target="#generalProductionTarget">{{ $productionTarget ? 'Edit target' : 'Set target' }}</button>@endif
    </div>
</section>
@if($canManageTargets)
<div class="modal fade" id="generalProductionTarget" tabindex="-1" aria-labelledby="generalProductionTargetTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ $productionTarget ? route('jmv.operations.production.targets.update', $productionTarget) : route('jmv.operations.production.targets.store') }}" class="modal-content rounded-4">@csrf @if($productionTarget) @method('PUT') @endif
    <div class="modal-header"><h2 id="generalProductionTargetTitle" class="text-lg font-black">{{ $productionTarget ? 'Edit general target' : 'Set general target' }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><p class="mb-3 text-sm text-slate-600">One general target for all mines and shifts.</p>
        <label class="block text-sm font-bold text-slate-700">Daily coal target (Bagons)<input type="number" name="coal_target" required min="0" max="999999999" step="1" value="{{ old('coal_target', $productionTarget?->coal_target) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="mt-4 block text-sm font-bold text-slate-700">Weekly coal target (Bagons)<input type="number" name="weekly_coal_target" required min="0" max="6999999993" step="1" value="{{ old('weekly_coal_target', $productionTarget?->weeklyBagons()) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <p class="mt-3 text-xs text-slate-500">Daily and weekly targets are set separately. MT is calculated from Bagons. Admin, manager and supervisor can edit. Changes are recorded in history.</p>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save target</button></div>
</form></div></div>
@endif
