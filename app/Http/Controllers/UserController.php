<?php

namespace App\Http\Controllers;

use App\Models\AccessRole;
use App\Models\ApprovalAuthority;
use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->canManageUsers(), 403);

        $search = $request->search;

        $users = User::query()
            ->with(['department', 'division', 'position', 'accessRole', 'approvalAuthorities'])
            ->when(! auth()->user()->canManageAllCompanies(), fn ($query) => $query->where('division_id', auth()->user()->division_id))
            ->when($search, function ($query, $searchTerm) {
                $query->where(function ($userQuery) use ($searchTerm) {
                    $userQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('lastname', 'like', "%{$searchTerm}%")
                        ->orWhere('username', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhereHas('position', fn ($positions) => $positions->where('name', 'like', "%{$searchTerm}%"))
                        ->orWhereHas('department', fn ($departments) => $departments->where('name', 'like', "%{$searchTerm}%"))
                        ->orWhereHas('division', fn ($companies) => $companies->where('name', 'like', "%{$searchTerm}%"));
                });
            })
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create()
    {
        abort_unless(auth()->user()->canManageUsers(), 403);

        return view('users.create', $this->formData());
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->canManageUsers(), 403);

        $this->hydrateLegacyOrganizationFields($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'cell_number' => 'required|string|max:255',
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->whereNull('division_id')
                        ->orWhere('division_id', $request->integer('division_id'))
                ),
            ],
            'division_id' => 'required|exists:divisions,id',
            'position_id' => ['required', Rule::exists('positions', 'id')->where(fn ($query) => $query->where('division_id', $request->integer('division_id'))->where('department_id', $request->integer('department_id'))->where('is_active', true))],
            'access_role_id' => ['required', Rule::exists('access_roles', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'approval_modules' => 'nullable|array',
            'approval_modules.*' => ['string', Rule::in(array_keys(ApprovalAuthority::MODULES))],
            'vessel_assignment_present' => 'nullable|boolean',
            'vessel_ids' => 'nullable|array',
            'vessel_ids.*' => 'integer|distinct|exists:vessels,id',
        ]);

        $this->authorizeCompanyAndAccessRole($data);
        $access = $this->resolveAccess($data);

        $temporaryPassword = 'villa@2026';

        $user = User::create([
            ...$data,
            'password' => Hash::make($temporaryPassword),
            'created_by' => auth()->id(),
            'role' => $access['legacy_role'],
            'is_admin' => $access['is_admin'],
            'must_change_password' => 1,
        ]);
        $this->syncOrganizationAccess($user, $data);
        $this->syncVesselAssignments($user, $data, true);

        return redirect('/users')
            ->with('success', 'User created successfully.')
            ->with('temporary_password', $temporaryPassword);
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        abort_unless(auth()->user()->canManageUsers(), 403);

        $user->load(['approvalAuthorities', 'position', 'accessRole', 'vesselAssignments']);

        return view('users.edit', ['user' => $user, ...$this->formData()]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $actor = auth()->user();
        abort_unless($actor->canManageUsers(), 403);

        $this->hydrateLegacyOrganizationFields($request, $user);
        $rules = [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'cell_number' => 'required|string|max:255',
        ];

        $rules += [
            'status' => 'required|boolean',
            'division_id' => 'required|exists:divisions,id',
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->whereNull('division_id')
                        ->orWhere('division_id', $request->integer('division_id'))
                ),
            ],
            'position_id' => ['required', Rule::exists('positions', 'id')->where(fn ($query) => $query->where('division_id', $request->integer('division_id'))->where('department_id', $request->integer('department_id'))->where('is_active', true))],
            'access_role_id' => ['required', Rule::exists('access_roles', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'approval_modules' => 'nullable|array',
            'approval_modules.*' => ['string', Rule::in(array_keys(ApprovalAuthority::MODULES))],
            'vessel_assignment_present' => 'nullable|boolean',
            'vessel_ids' => 'nullable|array',
            'vessel_ids.*' => 'integer|distinct|exists:vessels,id',
        ];

        $data = $request->validate($rules);
        $this->authorizeCompanyAndAccessRole($data);
        $access = $this->resolveAccess($data);

        $user->fill([
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'username' => $data['username'],
            'email' => $data['email'],
            'cell_number' => $data['cell_number'],
        ]);

        $newIsAdmin = $access['is_admin'];

        if ($actor->is($user) && (! (bool) $data['status'] || ($access['legacy_role'] !== 'admin' && ! $newIsAdmin))) {
            throw ValidationException::withMessages([
                'status' => 'You cannot deactivate or remove your own administrator access.',
            ]);
        }

        if ($user->canManageUsers() && (! (bool) $data['status'] || ($access['legacy_role'] === 'owner' || ($access['legacy_role'] !== 'admin' && ! $newIsAdmin)))) {
            $this->ensureAnotherActiveManagerExists($user);
        }

        $user->fill([
            'status' => $data['status'],
            'role' => $access['legacy_role'],
            'department_id' => $data['department_id'],
            'division_id' => $data['division_id'],
            'position_id' => $data['position_id'],
            'access_role_id' => $data['access_role_id'],
            'is_admin' => $newIsAdmin,
        ]);

        $user->save();
        $this->syncOrganizationAccess($user, $data);
        $this->syncVesselAssignments($user, $data, $request->boolean('vessel_assignment_present'));

        if (! $user->status) {
            $this->invalidateSessions($user);
        }

        return redirect()->route('users.edit', $user->id)
            ->with('success', 'User updated successfully.');
    }

    public function destroy($id)
    {
        abort_unless(auth()->user()->canManageUsers(), 403);

        $user = User::findOrFail($id);

        if (auth()->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot deactivate your own account.',
            ]);
        }

        if ($user->canManageUsers() && $user->status) {
            $this->ensureAnotherActiveManagerExists($user);
        }

        $user->forceFill([
            'status' => false,
            'remember_token' => null,
        ])->save();
        $user->companyAssignments()->update(['is_active' => false]);
        $user->approvalAuthorities()->update(['is_active' => false]);
        $user->vesselAssignments()->update(['is_active' => false, 'is_primary' => false, 'effective_until' => today()]);
        $this->invalidateSessions($user);

        return redirect('/users')->with('success', 'User deactivated safely. Historical records were preserved.');
    }

    public function resetPassword($id)
    {
        abort_unless(auth()->user()->canManageUsers(), 403);

        $user = User::findOrFail($id);
        $temporaryPassword = Str::password(16);
        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => 1,
        ]);

        return back()
            ->with('success', 'Password reset successfully.')
            ->with('temporary_password', $temporaryPassword);
    }

    public function changePassword(Request $request, $id)
    {
        abort_unless(auth()->user()->canManageUsers(), 403);

        $data = $request->validate([
            'password' => 'required|string|confirmed|min:12',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => 0,
        ]);

        return back()->with('success', 'Password updated successfully.');
    }

    private function ensureAnotherActiveManagerExists(User $excludedUser): void
    {
        $anotherManagerExists = User::query()
            ->whereKeyNot($excludedUser->getKey())
            ->where('status', true)
            ->where('role', '!=', 'owner')
            ->where(function ($query): void {
                $query->where('role', 'admin')->orWhere('is_admin', true);
            })
            ->exists();

        if (! $anotherManagerExists) {
            throw ValidationException::withMessages([
                'user' => 'At least one active system administrator must remain.',
            ]);
        }
    }

    private function invalidateSessions(User $user): void
    {
        $user->forceFill(['remember_token' => null])->saveQuietly();

        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    private function formData(): array
    {
        $actor = auth()->user();
        $divisions = $actor->canManageAllCompanies() ? Division::orderBy('name')->get() : Division::whereKey($actor->division_id)->get();
        $divisionIds = $divisions->pluck('id');
        $departments = Department::whereIn('division_id', $divisionIds)->orderBy('name')->get();
        $positions = Position::whereIn('division_id', $divisionIds)->where('is_active', true)->orderBy('name')->get();
        $roles = AccessRole::where('is_active', true)->when(! $actor->canManageAllCompanies(), fn ($query) => $query->where('rank', '<', $actor->accessRole?->rank ?? 80)->where('slug', '!=', 'executive-viewer'))->orderByDesc('rank')->get();
        $vessels = Vessel::orderBy('vessel_name')->get();
        $approvalModulesByDivision = $divisions->mapWithKeys(
            fn (Division $division): array => [(string) $division->id => ApprovalAuthority::modulesForCompany($division->name)]
        )->all();
        $approvalModules = collect($approvalModulesByDivision)->flatMap(fn (array $modules): array => $modules)->all();

        return compact('departments', 'divisions', 'positions', 'roles', 'vessels', 'approvalModules', 'approvalModulesByDivision');
    }

    private function authorizeCompanyAndAccessRole(array $data): void
    {
        $actor = auth()->user();
        abort_unless($actor->canManageAllCompanies() || (int) $actor->division_id === (int) $data['division_id'], 403, 'You can manage users only within your assigned company.');
        $selected = AccessRole::findOrFail($data['access_role_id']);
        if (! $actor->canManageAllCompanies()) {
            abort_unless($selected->rank < ($actor->accessRole?->rank ?? 80) && $selected->slug !== 'executive-viewer', 403, 'You cannot grant this access profile.');
        }
        $companyName = Division::whereKey($data['division_id'])->value('name');
        $allowedModules = array_keys(ApprovalAuthority::modulesForCompany($companyName));
        $selectedModules = array_values(array_unique($data['approval_modules'] ?? []));

        if (array_diff($selectedModules, $allowedModules)) {
            throw ValidationException::withMessages(['approval_modules' => 'One or more approval workflows are not available for the selected company.']);
        }
        if ($selected->slug === 'company-approver' && $allowedModules && ! $selectedModules) {
            throw ValidationException::withMessages(['approval_modules' => 'Select at least one approval module for a Company Approver.']);
        }
        if ($selected->slug !== 'company-approver' && $selectedModules) {
            throw ValidationException::withMessages(['approval_modules' => 'Approval authority requires the Company Approver access profile.']);
        }
    }

    private function resolveAccess(array $data): array
    {
        $role = AccessRole::findOrFail($data['access_role_id']);
        $position = Position::findOrFail($data['position_id']);

        return match ($role->slug) {
            'system-administrator' => ['legacy_role' => 'admin', 'is_admin' => true],
            'company-administrator', 'company-approver' => ['legacy_role' => 'manager', 'is_admin' => false],
            'executive-viewer' => ['legacy_role' => 'owner', 'is_admin' => false],
            default => ['legacy_role' => in_array($position->legacy_role, ['captain', 'purchaser', 'it', 'hr', 'r&d'], true) ? $position->legacy_role : 'staff', 'is_admin' => false],
        };
    }

    private function syncOrganizationAccess(User $user, array $data): void
    {
        $user->companyAssignments()->updateOrCreate(['division_id' => $data['division_id']], ['department_id' => $data['department_id'], 'position_id' => $data['position_id'], 'access_role_id' => $data['access_role_id'], 'is_primary' => true, 'is_active' => (bool) ($data['status'] ?? true), 'effective_from' => today()]);
        $user->companyAssignments()->where('division_id', '!=', $data['division_id'])->update(['is_primary' => false, 'is_active' => false, 'effective_until' => today()]);
        $modules = $data['approval_modules'] ?? [];
        $user->approvalAuthorities()->where('division_id', $data['division_id'])->whereNotIn('module', $modules ?: ['__none__'])->update(['is_active' => false]);
        foreach ($modules as $module) {
            $user->approvalAuthorities()->updateOrCreate(['division_id' => $data['division_id'], 'module' => $module, 'action' => 'approve'], ['department_id' => $data['department_id'], 'approval_level' => 1, 'is_active' => true, 'effective_from' => today(), 'effective_until' => null]);
        }
    }

    private function syncVesselAssignments(User $user, array $data, bool $sync): void
    {
        if (! $sync) {
            return;
        }

        $selectedIds = collect($data['vessel_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $existing = $user->vesselAssignments()->get()->keyBy('vessel_id');

        foreach ($selectedIds as $index => $vesselId) {
            $user->vesselAssignments()->updateOrCreate(['vessel_id' => $vesselId], [
                'assigned_by' => auth()->id(),
                'is_primary' => $index === 0,
                'is_active' => true,
                'effective_from' => today(),
                'effective_until' => null,
            ]);
        }

        $removedIds = $existing->keys()->diff($selectedIds);
        if ($removedIds->isNotEmpty()) {
            $user->vesselAssignments()->whereIn('vessel_id', $removedIds)->update([
                'is_primary' => false,
                'is_active' => false,
                'effective_until' => today(),
            ]);
            Vessel::whereIn('id', $removedIds)->where('captain_id', $user->id)->update(['captain_id' => null]);
        }

        // Keep the legacy single captain field synchronized with the primary
        // assignment while older reports/integrations still depend on it.
        $positionCode = Position::whereKey($data['position_id'])->value('code');
        if ($positionCode === 'vessel-captain') {
            foreach ($selectedIds as $vesselId) {
                Vessel::whereKey($vesselId)->where(fn ($query) => $query->whereNull('captain_id')->orWhere('captain_id', $user->id))->update(['captain_id' => $user->id]);
            }
        }
    }

    private function hydrateLegacyOrganizationFields(Request $request, ?User $user = null): void
    {
        if ($request->filled('position_id') && $request->filled('access_role_id')) {
            return;
        }
        $legacyRole = (string) ($request->input('role') ?: $user?->role ?: 'staff');
        $divisionId = $request->integer('division_id') ?: $user?->division_id;
        $departmentId = $request->integer('department_id') ?: $user?->department_id;
        if (! $divisionId || ! $departmentId) {
            return;
        }
        $position = Position::firstOrCreate(['division_id' => $divisionId, 'department_id' => $departmentId, 'code' => Str::slug($legacyRole)], ['name' => Str::headline($legacyRole), 'legacy_role' => $legacyRole, 'is_active' => true]);
        $roleSlug = match (true) {
            $legacyRole === 'owner' => 'executive-viewer', $legacyRole === 'admin' || $request->boolean('is_admin') => 'system-administrator', $legacyRole === 'manager' => 'company-approver', default => 'standard-user'
        };
        $request->merge(['position_id' => $request->input('position_id', $position->id), 'access_role_id' => $request->input('access_role_id', AccessRole::where('slug', $roleSlug)->value('id'))]);
    }
}
