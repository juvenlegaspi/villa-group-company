<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public const OPERATIONAL_CATEGORIES = ['staff' => 'General Employee', 'manager' => 'Department Manager', 'captain' => 'Vessel Captain', 'purchaser' => 'Purchasing', 'it' => 'Information Technology', 'hr' => 'Human Resources', 'r&d' => 'Research and Development'];

    public function index()
    {
        $this->authorizeManagement();
        $companies = $this->companies()->load(['departments' => fn ($query) => $query->with(['positions' => fn ($positions) => $positions->orderBy('name')])->orderBy('name')]);
        return view('users.organization', compact('companies'));
    }

    public function storeDepartment(Request $request)
    {
        $this->authorizeManagement();
        $data = $request->validate(['division_id' => 'required|exists:divisions,id', 'name' => 'required|string|max:255']);
        $this->authorizeCompany((int) $data['division_id']);
        if (Department::where('division_id', $data['division_id'])->whereRaw('LOWER(name) = ?', [strtolower(trim($data['name']))])->exists()) throw ValidationException::withMessages(['name' => 'This department already exists in the selected company.']);
        Department::create(['division_id' => $data['division_id'], 'name' => trim($data['name'])]);
        return back()->with('success', 'Department added successfully.');
    }

    public function storePosition(Request $request)
    {
        $this->authorizeManagement();
        $data = $request->validate(['division_id' => 'required|exists:divisions,id', 'department_id' => ['required', Rule::exists('departments', 'id')->where(fn ($query) => $query->where('division_id', $request->integer('division_id')))], 'name' => 'required|string|max:255', 'operational_category' => ['required', Rule::in(array_keys(self::OPERATIONAL_CATEGORIES))], 'description' => 'nullable|string|max:1000']);
        $this->authorizeCompany((int) $data['division_id']);
        $code = Str::slug($data['name']);
        if (Position::where(['division_id' => $data['division_id'], 'department_id' => $data['department_id'], 'code' => $code])->exists()) throw ValidationException::withMessages(['name' => 'This position already exists in the selected department.']);
        $position = Position::create(['division_id' => $data['division_id'], 'department_id' => $data['department_id'], 'name' => trim($data['name']), 'code' => $code, 'legacy_role' => $data['operational_category'], 'description' => $data['description'] ?? null, 'is_active' => true]);
        $departmentName = strtolower((string) Department::whereKey($data['department_id'])->value('name'));
        $divisionName = strtolower((string) Division::whereKey($data['division_id'])->value('name'));
        $permissionSlugs = match (true) {
            in_array($code, ['operations-manager', 'operation-manager', 'marine-operations-manager', 'vessel-manager'], true) => ['vessels.view_all', 'tech_defects.create', 'tech_defects.view_company', 'tech_defects.submit_review', 'tech_defects.review', 'tech_defects.verify'],
            $code === 'technical-manager' => ['vessels.view_all', 'tech_defects.create', 'tech_defects.view_company', 'tech_defects.submit_review', 'tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'],
            $departmentName === 'technical department' => ['vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit'],
            $departmentName === 'marine operations' => ['vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.create', 'tech_defects.submit_review'],
            $data['operational_category'] === 'captain' => ['vessels.view_assigned', 'tech_defects.create', 'tech_defects.submit_review'],
            default => [],
        };
        if ($data['operational_category'] === 'manager' && in_array($departmentName, ['marine operation', 'marine operations', 'technical department'], true)) {
            $permissionSlugs = array_values(array_unique([...$permissionSlugs, 'tech_defects.create', 'tech_defects.submit_review']));
        }
        $modulePermissions = match ($departmentName) {
            'marine operations', 'technical department' => ['shipping.operations.access', 'shipping.vessel_management.access', 'shipping.voyages.access', 'shipping.technical_defects.access', 'shipping.certificates.access', 'shipping.dry_docking.access'],
            'safety and compliance' => ['shipping.operations.access', 'shipping.technical_defects.access', 'shipping.certificates.access'],
            'procurement' => ['shipping.procurement.access'],
            'inventory and warehouse' => ['shipping.inventory.access'],
            default => [],
        };
        $permissionSlugs = array_values(array_unique([...$permissionSlugs, ...$modulePermissions]));
        if ($divisionName === 'yatira') {
            $permissionSlugs = array_values(array_unique([...$permissionSlugs, ...$this->yatiraPermissionsFor($departmentName, $code)]));
        }
        if ($divisionName === 'jmv') {
            $permissionSlugs = array_values(array_unique([...$permissionSlugs, ...$this->jmvPermissionsFor($code)]));
        }
        if ($permissionSlugs) {
            $permissionIds = Permission::whereIn('slug', $permissionSlugs)->pluck('id');
            DB::table('permission_position')->insertOrIgnore($permissionIds->map(fn ($id) => ['position_id' => $position->id, 'permission_id' => $id])->all());
        }
        return back()->with('success', 'Position added successfully.');
    }

    private function authorizeManagement(): void { abort_unless(auth()->user()->canManageUsers(), 403); }
    private function authorizeCompany(int $divisionId): void { abort_unless(auth()->user()->canManageAllCompanies() || (int) auth()->user()->division_id === $divisionId, 403); }
    private function companies() { return auth()->user()->canManageAllCompanies() ? Division::orderBy('name')->get() : Division::whereKey(auth()->user()->division_id)->get(); }
    private function yatiraPermissionsFor(string $departmentName, string $code): array
    {
        $permissions = match ($departmentName) {
            'procurement', 'purchasing' => ['yatira.suppliers.view', 'yatira.reports.view'],
            'finance and accounting' => ['yatira.suppliers.view', 'yatira.assets.view', 'yatira.reports.view'],
            'inventory and warehouse' => ['yatira.assets.view', 'yatira.consumables.view', 'yatira.reports.view'],
            'equipment and maintenance' => ['yatira.assets.view', 'yatira.consumables.view'],
            'project operations', 'engineering' => ['yatira.assets.view', 'yatira.consumables.view'],
            default => [],
        };
        if (in_array($departmentName, ['procurement', 'purchasing'], true) && in_array($code, ['procurement-manager', 'purchaser'], true)) $permissions[] = 'yatira.suppliers.manage';
        if ($departmentName === 'inventory and warehouse' && $code === 'inventory-manager') array_push($permissions, 'yatira.assets.create', 'yatira.assets.update', 'yatira.assets.dispose');
        if ($departmentName === 'equipment and maintenance' && $code === 'maintenance-manager') $permissions[] = 'yatira.assets.update';
        if ($departmentName === 'inventory and warehouse' && in_array($code, ['inventory-manager', 'warehouse-staff'], true)) $permissions[] = 'yatira.consumables.manage';
        return array_values(array_unique($permissions));
    }

    private function jmvPermissionsFor(string $code): array
    {
        return match ($code) {
            'inventory-manager' => ['jmv.inventory.view','jmv.inventory.items.manage','jmv.inventory.movements.manage','jmv.inventory.adjustments.manage','jmv.inventory.reports.view','jmv.inventory.requests.create','jmv.inventory.requests.approve'],
            'warehouse-staff' => ['jmv.inventory.view','jmv.inventory.movements.manage','jmv.inventory.requests.create'],
            'procurement-manager', 'purchaser', 'finance-manager', 'accountant' => ['jmv.inventory.view','jmv.inventory.reports.view'],
            'mine-operations-manager', 'mine-supervisor', 'department-manager' => ['jmv.operations.daily_production.view','jmv.operations.daily_production.manage','jmv.operations.daily_production.reports.view'],
            'chief-geologist' => ['jmv.operations.daily_production.view','jmv.operations.daily_production.reports.view'],
            'geologist' => ['jmv.operations.daily_production.view','jmv.operations.daily_production.manage'],
            default => [],
        };
    }
}
