<?php

namespace App\Http\Controllers;

use App\Models\ShippingPmsHeader;
use App\Models\Vessel;
use App\Services\VesselAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShippingPmsController extends Controller
{
    public const STATUSES = ['In Queue', 'On-Going', 'Completed Early', 'Completed On Time', 'Completed Late', 'Overdue'];

    public const FREQUENCIES = ['Daily', 'Weekly', 'Bi-Weekly', 'Monthly', 'Quarterly', 'Semi-annual', 'Annually', 'Five-year', 'Every Abandon Ship Drill', 'Every Fire Drill'];

    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    private function authorizeVessel(Vessel $vessel): void
    {
        $user = auth()->user();
        abort_unless($user->canAccessPms(), 403);
        $this->vesselAccess->authorize($user, $vessel);
    }

    public function show(Vessel $vessel): View
    {
        $this->authorizeVessel($vessel);
        $headers = ShippingPmsHeader::where('vessel_id', $vessel->id)->withCount('details')->orderBy('id')->paginate(10);

        return view('shipping.pms.headers', compact('vessel', 'headers'));
    }

    public function storeHeader(Request $request, Vessel $vessel): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('shipping_pms_headers')->where('vessel_id', $vessel->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        ShippingPmsHeader::create(['vessel_id' => $vessel->id, ...$data]);

        return to_route('shipping.pms.vessels.show', $vessel)->with('success', 'Equipment header added.');
    }

    public function updateHeader(Request $request, Vessel $vessel, ShippingPmsHeader $header): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        abort_unless($header->vessel_id === $vessel->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('shipping_pms_headers')->where('vessel_id', $vessel->id)->ignore($header->id)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $header->update($data);

        return to_route('shipping.pms.vessels.show', $vessel)->with('success', 'Equipment updated.');
    }

    public function details(Vessel $vessel, ShippingPmsHeader $header): View
    {
        $this->authorizeVessel($vessel);
        abort_unless($header->vessel_id === $vessel->id, 404);
        $details = $header->details()->orderBy('id')->get();
        $statuses = self::STATUSES;
        $frequencies = self::FREQUENCIES;

        return view('shipping.pms.details', compact('vessel', 'header', 'details', 'statuses', 'frequencies'));
    }

    public function storeDetail(Request $request, Vessel $vessel, ShippingPmsHeader $header): RedirectResponse
    {
        $this->authorizeVessel($vessel);
        abort_unless($header->vessel_id === $vessel->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'frequency' => ['nullable', Rule::in(self::FREQUENCIES)],
            'in_charge' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);
        $header->details()->create($data);

        return to_route('shipping.pms.headers.show', [$vessel, $header])->with('success', 'Equipment detail added.');
    }
}
