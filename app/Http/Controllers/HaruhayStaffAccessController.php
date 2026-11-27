<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Position;
use App\Models\User;
use App\Services\HaruhayAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HaruhayStaffAccessController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403);

        $audits = DB::table('haruhay_access_audits as a')->join('users as target', 'target.id', '=', 'a.user_id')->leftJoin('users as actor', 'actor.id', '=', 'a.actor_id')->leftJoin('positions as old_position', 'old_position.id', '=', 'a.old_position_id')->leftJoin('positions as new_position', 'new_position.id', '=', 'a.new_position_id')->where('target.division_id', $this->divisionId())->select('a.*', 'target.name as target_name', 'actor.name as actor_name', 'old_position.name as old_name', 'new_position.name as new_name')->orderByDesc('a.id')->limit(20)->get();

        return view('haruhay.access.index', ['users' => User::where('division_id', $this->divisionId())->with(['position', 'haruhayProperties'])->orderBy('name')->paginate(10), 'positions' => $this->positions()->get(), 'presets' => HaruhayAccessService::PRESETS, 'audits' => $audits]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403);
        abort_unless((int) $user->division_id === $this->divisionId() && ! $user->isSystemAdministrator() && ! $user->isExecutiveViewer(), 403);
        $data = $request->validate(['position_id' => 'required|integer', 'reason' => 'required|string|max:1000']);
        $position = $this->positions()->findOrFail($data['position_id']);
        DB::transaction(function () use ($user, $position, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->division_id === $this->divisionId() && ! $locked->isSystemAdministrator() && ! $locked->isExecutiveViewer(), 403);
            DB::table('haruhay_access_audits')->insert(['user_id' => $locked->id, 'actor_id' => auth()->id(), 'old_position_id' => $locked->position_id, 'new_position_id' => $position->id, 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $locked->update(['position_id' => $position->id, 'department_id' => $position->department_id]);
            $locked->companyAssignments()->updateOrCreate(['division_id' => $locked->division_id], ['department_id' => $position->department_id, 'position_id' => $position->id, 'access_role_id' => $locked->access_role_id, 'is_primary' => true, 'is_active' => (bool) $locked->status, 'effective_from' => today(), 'effective_until' => null]);
        });

        return back()->with('success', 'Haruhay staff access updated and recorded in the audit trail.');
    }

    private function divisionId(): int
    {
        return (int) Division::whereRaw('LOWER(name) = ?', ['haruhay'])->sole()->id;
    }

    private function positions()
    {
        return Position::where('division_id', $this->divisionId())->where('is_active', true)->whereIn('code', array_keys(HaruhayAccessService::PRESETS))->orderBy('name');
    }
}
