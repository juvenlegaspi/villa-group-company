<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vessel;
use App\Models\VesselCertificate;
use Illuminate\Support\Carbon;

class CertificateExpiryCalendar
{
    public function __construct(private readonly VesselAccessService $vesselAccess) {}

    public function month(User $user, string $month, ?Vessel $vessel = null): array
    {
        if ($vessel) {
            $this->vesselAccess->authorize($user, $vessel);
        }
        $first = Carbon::createFromFormat('!Y-m', $month, 'Asia/Manila');
        $today = today('Asia/Manila');
        $events = VesselCertificate::effective()->whereIn('vessel_id', $this->vesselAccess->scopeAccessible(Vessel::query(), $user)->select('id'))
            ->when($vessel, fn ($query) => $query->where('vessel_id', $vessel->id))
            ->whereDate('expiry_date', '>=', $first->toDateString())
            ->whereDate('expiry_date', '<=', $first->copy()->endOfMonth()->toDateString())
            ->with(['vessel:id,vessel_name', 'parentCertificate'])->orderBy('expiry_date')->orderBy('certificate_name')->get()
            ->map(fn ($certificate) => [
                'id' => $certificate->id, 'name' => $certificate->parent_certificate_id ? $certificate->certificate_name.' (under '.$certificate->parentCertificate->certificate_name.')' : $certificate->certificate_name, 'number' => $certificate->certificate_number,
                'vessel' => $certificate->vessel->vessel_name, 'date' => $certificate->expiry_date->toDateString(),
                'status' => $certificate->expiry_date->lt($today) ? 'Expired' : ($certificate->expiry_date->equalTo($today) ? 'Expires today' : ($certificate->expiry_date->lte($today->copy()->addDays(30)) ? 'Expiring soon' : 'Upcoming expiry')),
                'url' => route('vessel.certificates.show', ['id' => $certificate->vessel_id, 'search' => $certificate->parentCertificate?->certificate_name ?? $certificate->certificate_name, 'expand' => $certificate->parent_certificate_id ?? $certificate->id]).'#certificate-'.($certificate->parent_certificate_id ?? $certificate->id),
            ])->values()->all();

        return ['month' => $month, 'title' => $first->format('F Y'), 'today' => $today->toDateString(), 'total' => count($events), 'events' => $events];
    }
}
