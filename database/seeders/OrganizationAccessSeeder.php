<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrganizationAccessSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Villa shipping Lines' => [
                'Marine Operations' => [['Operations Manager', 'manager'], ['Liaison Officer', 'staff'], ['Vessel Manager', 'manager'], ['Vessel Captain', 'captain'], ['Chief Officer', 'staff'], ['Deck Officer', 'staff']],
                'Technical Department' => [['Technical Manager', 'manager'], ['Chief Engineer', 'staff'], ['Second Engineer', 'staff'], ['Maintenance Technician', 'staff']],
                'Safety and Compliance' => [['Safety Manager', 'manager'], ['Safety Officer', 'staff']],
                'Procurement' => [['Procurement Manager', 'manager'], ['Purchaser', 'purchaser']],
                'Inventory and Warehouse' => [['Inventory Manager', 'manager'], ['Warehouse Staff', 'staff']],
                'Finance and Accounting' => [['Finance Manager', 'manager'], ['Accountant', 'staff']],
                'Human Resources' => [['HR Manager', 'manager'], ['HR Officer', 'hr']],
                'Information Technology' => [['IT Manager', 'manager'], ['System Administrator', 'it']],
            ],
            'Yatira' => [
                'Sales and Business Development' => [['Sales Manager', 'manager'], ['Sales Agent', 'staff']],
                'Project Operations' => [['Project Manager', 'manager'], ['Project Coordinator', 'staff'], ['Site Supervisor', 'staff']],
                'Engineering' => [['Engineering Manager', 'manager'], ['Civil Engineer', 'staff'], ['Project Engineer', 'staff']],
                'Equipment and Maintenance' => [['Maintenance Manager', 'manager'], ['Equipment Technician', 'staff']],
                'Safety and Compliance' => [['Safety Manager', 'manager'], ['Safety Officer', 'staff']],
                'Procurement' => [['Procurement Manager', 'manager'], ['Purchaser', 'purchaser']],
                'Inventory and Warehouse' => [['Inventory Manager', 'manager'], ['Warehouse Staff', 'staff']],
                'Finance and Accounting' => [['Finance Manager', 'manager'], ['Accountant', 'staff']],
                'Human Resources' => [['HR Manager', 'manager'], ['HR Officer', 'hr']],
            ],
            'JMV' => [
                'Mining Operations' => [['Mine Operations Manager', 'manager'], ['Mine Supervisor', 'staff'], ['Mining Staff', 'staff']],
                'Equipment Maintenance' => [['Maintenance Manager', 'manager'], ['Heavy Equipment Technician', 'staff']],
                'Geology' => [['Chief Geologist', 'manager'], ['Geologist', 'staff']],
                'Safety and Environment' => [['HSE Manager', 'manager'], ['Safety Officer', 'staff']],
                'Procurement' => [['Procurement Manager', 'manager'], ['Purchaser', 'purchaser']],
                'Inventory and Warehouse' => [['Inventory Manager', 'manager'], ['Warehouse Staff', 'staff']],
                'Finance and Accounting' => [['Finance Manager', 'manager'], ['Accountant', 'staff']],
                'Human Resources' => [['HR Manager', 'manager'], ['HR Officer', 'hr']],
            ],
            'Villa Group' => [
                'Executive Office' => [['President', 'owner'], ['Executive Assistant', 'staff']],
                'Corporate Finance' => [['Corporate Finance Manager', 'manager'], ['Corporate Accountant', 'staff']],
                'Human Resources' => [['HR Manager', 'manager'], ['HR Officer', 'hr']],
                'Information Technology' => [['IT Manager', 'manager'], ['System Administrator', 'it'], ['IT Support Specialist', 'it']],
                'Research and Development' => [['R&D Manager', 'manager'], ['R&D Specialist', 'r&d']],
                'Internal Audit' => [['Internal Audit Manager', 'manager'], ['Internal Auditor', 'staff']],
                'Legal and Administration' => [['Administration Manager', 'manager'], ['Administrative Officer', 'staff']],
                'Corporate Procurement' => [['Procurement Manager', 'manager'], ['Purchaser', 'purchaser']],
            ],
            'HYVE' => [
                'Operations' => [['Operations Manager', 'manager'], ['Operations Staff', 'staff']],
                'Procurement' => [['Procurement Manager', 'manager'], ['Purchaser', 'purchaser']],
                'Finance and Accounting' => [['Finance Manager', 'manager'], ['Accountant', 'staff']],
                'Human Resources' => [['HR Manager', 'manager'], ['HR Officer', 'hr']],
                'Information Technology' => [['IT Manager', 'manager'], ['IT Support Specialist', 'it']],
            ],
        ];

        $positionPermissions = [
            'vessel-manager' => ['vessels.view_all', 'tech_defects.create', 'tech_defects.view_company', 'tech_defects.submit_review', 'tech_defects.review', 'tech_defects.verify'],
            'vessel-captain' => ['vessels.view_assigned', 'tech_defects.create', 'tech_defects.view_assigned', 'tech_defects.submit_review'],
            'technical-manager' => ['vessels.view_all', 'tech_defects.create', 'tech_defects.view_company', 'tech_defects.submit_review', 'tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'],
            'chief-engineer' => ['vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.assess', 'tech_defects.assign_action', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'],
            'second-engineer' => ['vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.assess', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit'],
            'maintenance-technician' => ['vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.perform_action', 'tech_defects.update_closeout', 'tech_defects.upload_evidence', 'tech_defects.request_support', 'tech_defects.submit'],
            'operations-manager' => ['vessels.view_all', 'tech_defects.create', 'tech_defects.view_company', 'tech_defects.submit_review', 'tech_defects.review', 'tech_defects.verify', 'certificates.manage'],
            'liaison-officer' => ['vessels.view_assigned', 'certificates.manage'],
        ];
        foreach ($catalog as $company => $departments) {
            $division = DB::table('divisions')->whereRaw('LOWER(name) = ?', [strtolower($company)])->first();
            if (! $division) continue;
            foreach ($departments as $departmentName => $positions) {
                $departmentId = DB::table('departments')->where('division_id', $division->id)->whereRaw('LOWER(name) = ?', [strtolower($departmentName)])->value('id');
                if (! $departmentId) $departmentId = DB::table('departments')->insertGetId(['division_id' => $division->id, 'name' => $departmentName, 'created_at' => now(), 'updated_at' => now()]);
                foreach ($positions as [$positionName, $legacyRole]) {
                    $code = Str::slug($positionName);
                    $positionId = DB::table('positions')->where(['division_id' => $division->id, 'department_id' => $departmentId, 'code' => $code])->value('id');
                    if (! $positionId) $positionId = DB::table('positions')->insertGetId(['division_id' => $division->id, 'department_id' => $departmentId, 'name' => $positionName, 'code' => $code, 'legacy_role' => $legacyRole, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $modulePermissions = match (strtolower($departmentName)) {
                        'marine operations', 'technical department' => ['shipping.operations.access', 'shipping.vessel_management.access', 'shipping.voyages.access', 'shipping.technical_defects.access', 'shipping.certificates.access', 'shipping.dry_docking.access'],
                        'safety and compliance' => ['shipping.operations.access', 'shipping.technical_defects.access', 'shipping.certificates.access'],
                        'procurement' => ['shipping.procurement.access'],
                        'inventory and warehouse' => ['shipping.inventory.access'],
                        default => [],
                    };
                    $yatiraPermissions = strtolower($company) === 'yatira'
                        ? $this->yatiraPermissionsFor($departmentName, $code)
                        : [];
                    $jmvPermissions = strtolower($company) === 'jmv' ? $this->jmvPermissionsFor($code) : [];
                    $slugs = array_values(array_unique([...($positionPermissions[$code] ?? []), ...$modulePermissions, ...$yatiraPermissions, ...$jmvPermissions]));
                    foreach (DB::table('permissions')->whereIn('slug', $slugs)->pluck('id') as $permissionId) {
                        DB::table('permission_position')->insertOrIgnore(['position_id' => $positionId, 'permission_id' => $permissionId]);
                    }
                }
            }
        }
        $this->ensureJmvInventoryDefaults();
    }

    private function yatiraPermissionsFor(string $departmentName, string $positionCode): array
    {
        if (strtolower($departmentName) === 'sales and business development') {
            return $positionCode === 'sales-manager'
                ? ['yatira.sales.view', 'yatira.sales.create', 'yatira.sales.update', 'yatira.sales.manage']
                : ['yatira.sales.view', 'yatira.sales.create', 'yatira.sales.update'];
        }
        $permissions = match (strtolower($departmentName)) {
            'procurement', 'purchasing' => ['yatira.suppliers.view'],
            'finance and accounting' => ['yatira.suppliers.view', 'yatira.assets.view', 'yatira.reports.view'],
            'project operations', 'engineering', 'equipment and maintenance' => ['yatira.assets.view', 'yatira.consumables.view'],
            'inventory and warehouse' => ['yatira.assets.view', 'yatira.consumables.view', 'yatira.reports.view'],
            default => [],
        };

        if (in_array($positionCode, ['procurement-manager', 'purchaser'], true)) {
            $permissions[] = 'yatira.suppliers.manage';
            $permissions[] = 'yatira.reports.view';
        }
        if ($positionCode === 'inventory-manager') {
            array_push($permissions, 'yatira.assets.create', 'yatira.assets.update', 'yatira.assets.dispose', 'yatira.consumables.manage');
        }
        if ($positionCode === 'maintenance-manager') {
            $permissions[] = 'yatira.assets.update';
        }
        if ($positionCode === 'warehouse-staff') {
            $permissions[] = 'yatira.consumables.manage';
        }

        return array_values(array_unique($permissions));
    }

    private function jmvPermissionsFor(string $positionCode): array
    {
        return match ($positionCode) {
            'inventory-manager' => ['jmv.inventory.view','jmv.inventory.items.manage','jmv.inventory.movements.manage','jmv.inventory.adjustments.manage','jmv.inventory.reports.view','jmv.inventory.requests.create','jmv.inventory.requests.approve'],
            'warehouse-staff' => ['jmv.inventory.view','jmv.inventory.movements.manage','jmv.inventory.requests.create'],
            'procurement-manager', 'purchaser', 'finance-manager', 'accountant' => ['jmv.inventory.view','jmv.inventory.reports.view'],
            'mine-operations-manager', 'mine-supervisor', 'department-manager' => ['jmv.operations.daily_production.view','jmv.operations.daily_production.manage','jmv.operations.daily_production.reports.view'],
            'chief-geologist' => ['jmv.operations.daily_production.view','jmv.operations.daily_production.reports.view'],
            'geologist' => ['jmv.operations.daily_production.view','jmv.operations.daily_production.manage'],
            default => [],
        };
    }

    private function ensureJmvInventoryDefaults(): void
    {
        if (! Schema::hasTable('jmv_inventory_categories') || ! Schema::hasTable('jmv_inventory_locations')) return;
        $divisionId = DB::table('divisions')->whereRaw('LOWER(name) = ?', ['jmv'])->value('id');
        if (! $divisionId) return;
        foreach (['General','Fuel and Lubricants','Spare Parts','Tools and Equipment','Safety Supplies','Office Supplies'] as $name) {
            DB::table('jmv_inventory_categories')->updateOrInsert(['division_id'=>$divisionId,'name'=>$name], ['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        DB::table('jmv_inventory_locations')->updateOrInsert(['division_id'=>$divisionId,'code'=>'MAIN'], ['name'=>'Main Warehouse','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }
}
