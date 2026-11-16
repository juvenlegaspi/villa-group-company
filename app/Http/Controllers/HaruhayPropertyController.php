<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\HaruhayProperty;
use App\Models\HaruhayPropertyAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HaruhayPropertyController extends Controller
{
    public const TYPES = ['Residential', 'Commercial', 'Land', 'Dormitory', 'Apartment', 'House', 'Commercial Building', 'Warehouse', 'Others'];
    public const STATUSES = ['Active', 'Inactive', 'Operational', 'Under Construction', 'Idle', 'For Sale', 'Sold'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'type' => ['nullable', Rule::in(self::TYPES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);
        $base = HaruhayProperty::query()->when(! $this->isCorporate(), fn ($query) => $query->where('division_id', $this->divisionId()));
        $stats = [
            'total' => (clone $base)->count(),
            'operational' => (clone $base)->where('status', 'Operational')->count(),
            'construction' => (clone $base)->where('status', 'Under Construction')->count(),
            'for_sale' => (clone $base)->where('status', 'For Sale')->count(),
        ];
        $roomTotals = \App\Models\HaruhayUnit::whereIn('property_id', (clone $base)->select('id'))
            ->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END), 0) as occupied, COALESCE(SUM(CASE WHEN status = 'Vacant' THEN 1 ELSE 0 END), 0) as vacant")->first();
        $stats += ['total_units' => (int) $roomTotals->total, 'occupied_units' => (int) $roomTotals->occupied, 'vacant_units' => (int) $roomTotals->vacant,
            'occupancy_rate' => $roomTotals->total ? round($roomTotals->occupied / $roomTotals->total * 100, 2) : 0];
        $properties = $base->withCount([
            'units', 'units as occupied_units_count' => fn ($query) => $query->where('status', 'Occupied'),
            'units as vacant_units_count' => fn ($query) => $query->where('status', 'Vacant'),
        ])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
                $query->where(function ($nested) use ($filters) {
                    foreach (['code', 'name', 'location', 'owner', 'street_barangay', 'city', 'province', 'person_in_charge'] as $field) {
                        $nested->orWhere($field, 'like', '%'.$filters['search'].'%');
                    }
                });
            })
            ->when(filled($filters['type'] ?? null), fn ($q) => $q->where('type', $filters['type']))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->latest('id')->paginate(10)->withQueryString();

        return view('haruhay.properties.index', compact('properties', 'stats') + $this->formData());
    }

    public function create()
    {
        $this->authorizeManagement();
        return view('haruhay.properties.form', ['property' => new HaruhayProperty] + $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorizeManagement();
        $property = $this->save($request, new HaruhayProperty);
        return to_route($this->routePrefix().'index')->with('success', 'Property '.$property->code.' added successfully.');
    }

    public function show(HaruhayProperty $property)
    {
        $this->authorizeProperty($property);
        $property->load(['attachments', 'creator', 'updater']);
        return view('haruhay.properties.show', compact('property') + $this->formData());
    }

    public function edit(HaruhayProperty $property)
    {
        $this->authorizeManagement();
        $this->authorizeProperty($property);
        $property->load('attachments');
        return view('haruhay.properties.form', compact('property') + $this->formData());
    }

    public function update(Request $request, HaruhayProperty $property)
    {
        $this->authorizeManagement();
        $this->authorizeProperty($property);
        $this->save($request, $property);
        return to_route($this->routePrefix().'show', $property)->with('success', 'Property updated successfully.');
    }

    public function attachment(HaruhayProperty $property, HaruhayPropertyAttachment $attachment)
    {
        $this->authorizeProperty($property);
        abort_unless((int) $attachment->property_id === (int) $property->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function save(Request $request, HaruhayProperty $property): HaruhayProperty
    {
        if (! $request->has('location') && $request->filled('street_barangay')) {
            $request->merge(['location' => implode(', ', array_filter([$request->input('street_barangay'), $request->input('city'), $request->input('province')], fn ($part) => is_string($part) && filled($part)))]);
        }
        $data = $request->validateWithBag($property->exists ? 'default' : 'propertyCreate', [
            'name' => 'required|string|max:255', 'owner' => 'sometimes|nullable|string|max:255',
            'location' => 'required|string|max:2000',
            'type' => ['required', Rule::in(self::TYPES)],
            'street_barangay' => 'sometimes|nullable|string|max:255', 'city' => 'sometimes|nullable|string|max:255', 'province' => 'sometimes|nullable|string|max:255',
            'lot_area' => 'nullable|numeric|decimal:0,2|min:0|max:999999999999.99',
            'floor_area' => 'nullable|numeric|decimal:0,2|min:0|max:999999999999.99',
            'floors' => 'nullable|integer|min:1|max:999',
            'status' => ['required', Rule::in(self::STATUSES)],
            'person_in_charge' => 'nullable|string|max:255', 'contact_number' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:10000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx|max:10240',
        ]);
        unset($data['attachments']);
        foreach (['owner', 'street_barangay', 'city', 'province'] as $legacyField) {
            if (array_key_exists($legacyField, $data)) $data[$legacyField] ??= '';
            elseif (! $property->exists) $data[$legacyField] = '';
        }
        $paths = [];
        try {
            return DB::transaction(function () use ($request, $property, $data, &$paths) {
                $creating = ! $property->exists;
                if (!$creating) {
                    $property = HaruhayProperty::whereKey($property->id)->lockForUpdate()->firstOrFail();
                    if ($property->attachments()->count() + count($request->file('attachments', [])) > 10) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['attachments' => 'A property can have up to 10 attachments.']);
                    }
                }
                $property->fill($data);
                if ($creating) $property->division_id = $this->divisionId();
                $property->updated_by = auth()->id();
                if ($creating) $property->created_by = auth()->id();
                $property->save();
                if ($creating) $property->update(['code' => 'PROP-'.str_pad((string) $property->id, 4, '0', STR_PAD_LEFT)]);
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('haruhay/properties/'.$property->id, 'local');
                    if (! $path) throw new \RuntimeException('Unable to save property attachment.');
                    $paths[] = $path;
                    $property->attachments()->create([
                        'path' => $path, 'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(), 'uploaded_by' => auth()->id(),
                    ]);
                }
                return $property;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    private function isCorporate(): bool { return request()->routeIs('villa-group.properties.*'); }
    private function routePrefix(): string { return $this->isCorporate() ? 'villa-group.properties.' : 'haruhay.properties.'; }
    private function divisionId(): int { return (int) Division::whereRaw('LOWER(name) = ?', [$this->isCorporate() ? 'villa group' : 'haruhay'])->sole()->id; }
    private function authorizeProperty(HaruhayProperty $property): void { abort_unless($this->isCorporate() || (int) $property->division_id === $this->divisionId(), 404); }
    private function canManage(): bool
    {
        if ($this->isCorporate()) return app(\App\Services\VillaGroupAccessService::class)->canManageProperties(auth()->user());
        return ! auth()->user()->isExecutiveViewer() && (auth()->user()->canManageUsers() || auth()->user()->hasPermission('haruhay.properties.manage'));
    }
    private function authorizeManagement(): void { abort_unless($this->canManage(), 403); }
    private function formData(): array
    {
        return ['types' => self::TYPES, 'statuses' => self::STATUSES, 'canManage' => $this->canManage(),
            'propertyRoutes' => $this->routePrefix(), 'propertyCompany' => $this->isCorporate() ? 'Villa Group' : 'Haruhay Space'];
    }
}
