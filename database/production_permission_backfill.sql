-- Production-safe organization permission backfill
-- Idempotent: this file only inserts missing permission catalog and pivot rows.
-- It does not delete users, assignments, permissions, or operational records.

START TRANSACTION;

-- Ensure the permission catalog used by current navigation and controllers exists.
INSERT IGNORE INTO permissions (name, slug, module, description, created_at, updated_at) VALUES
('Access Villa Shipping operations', 'shipping.operations.access', 'shipping', 'Access Villa Shipping operations based on department and position.', NOW(), NOW()),
('Access vessel management', 'shipping.vessel_management.access', 'shipping', 'Access vessel management based on department and position.', NOW(), NOW()),
('Access voyage and fuel records', 'shipping.voyages.access', 'shipping', 'Access voyage and fuel records based on department and position.', NOW(), NOW()),
('Access technical defect monitoring', 'shipping.technical_defects.access', 'shipping', 'Access technical defect monitoring based on department and position.', NOW(), NOW()),
('Access vessel certificates', 'shipping.certificates.access', 'shipping', 'Access vessel certificates based on department and position.', NOW(), NOW()),
('Access dry docking monitoring', 'shipping.dry_docking.access', 'shipping', 'Access dry docking monitoring based on department and position.', NOW(), NOW()),
('Access Villa Shipping procurement', 'shipping.procurement.access', 'shipping', 'Access Villa Shipping procurement based on department and position.', NOW(), NOW()),
('Access Villa Shipping inventory', 'shipping.inventory.access', 'shipping', 'Access Villa Shipping inventory based on department and position.', NOW(), NOW()),
('View all vessels', 'vessels.view_all', 'vessels', 'View all vessels in the assigned company.', NOW(), NOW()),
('View assigned vessels', 'vessels.view_assigned', 'vessels', 'View vessels assigned to the employee.', NOW(), NOW()),
('Create technical defect reports', 'tech_defects.create', 'tech_defects', 'Create technical defect reports.', NOW(), NOW()),
('View assigned technical defects', 'tech_defects.view_assigned', 'tech_defects', 'View assigned technical defects.', NOW(), NOW()),
('View company technical defects', 'tech_defects.view_company', 'tech_defects', 'View all technical defects in the assigned company.', NOW(), NOW()),
('Submit technical defects for review', 'tech_defects.submit_review', 'tech_defects', 'Submit new technical defect reports for manager review.', NOW(), NOW()),
('Review technical defects', 'tech_defects.review', 'tech_defects', 'Review and return technical defect reports.', NOW(), NOW()),
('Assess technical defects', 'tech_defects.assess', 'tech_defects', 'Assess technical defect reports.', NOW(), NOW()),
('Assign technical action', 'tech_defects.assign_action', 'tech_defects', 'Assign corrective technical action.', NOW(), NOW()),
('Perform technical action', 'tech_defects.perform_action', 'tech_defects', 'Perform and update corrective action.', NOW(), NOW()),
('Update repair close-out', 'tech_defects.update_closeout', 'tech_defects', 'Update repair close-out information.', NOW(), NOW()),
('Upload repair evidence', 'tech_defects.upload_evidence', 'tech_defects', 'Upload technical defect evidence.', NOW(), NOW()),
('Request third-party support', 'tech_defects.request_support', 'tech_defects', 'Request third-party technical support.', NOW(), NOW()),
('Submit repair for verification', 'tech_defects.submit', 'tech_defects', 'Submit completed repairs for verification.', NOW(), NOW()),
('Verify technical defect resolution', 'tech_defects.verify', 'tech_defects', 'Verify or reopen completed repairs.', NOW(), NOW()),
('Manage vessel certificates', 'certificates.manage', 'certificates', 'Create and renew vessel certificates.', NOW(), NOW()),
('View Yatira suppliers', 'yatira.suppliers.view', 'yatira', 'View Yatira suppliers.', NOW(), NOW()),
('Manage Yatira suppliers', 'yatira.suppliers.manage', 'yatira', 'Create and update Yatira suppliers.', NOW(), NOW()),
('View Yatira fixed assets', 'yatira.assets.view', 'yatira', 'View Yatira fixed assets.', NOW(), NOW()),
('Register Yatira fixed assets', 'yatira.assets.create', 'yatira', 'Register Yatira fixed assets.', NOW(), NOW()),
('Update Yatira fixed assets', 'yatira.assets.update', 'yatira', 'Update Yatira fixed assets.', NOW(), NOW()),
('Dispose Yatira fixed assets', 'yatira.assets.dispose', 'yatira', 'Dispose Yatira fixed assets.', NOW(), NOW()),
('View Yatira consumables', 'yatira.consumables.view', 'yatira', 'View Yatira consumables.', NOW(), NOW()),
('Manage Yatira consumables', 'yatira.consumables.manage', 'yatira', 'Manage Yatira consumables and stock movements.', NOW(), NOW()),
('View Yatira reports', 'yatira.reports.view', 'yatira', 'View and export Yatira reports.', NOW(), NOW()),
('View Yatira sales monitoring', 'yatira.sales.view', 'yatira', 'View Yatira sales monitoring.', NOW(), NOW()),
('Register Yatira sales leads', 'yatira.sales.create', 'yatira', 'Register Yatira sales leads.', NOW(), NOW()),
('Update Yatira sales leads', 'yatira.sales.update', 'yatira', 'Update Yatira leads and sales stages.', NOW(), NOW()),
('Manage Yatira sales leads', 'yatira.sales.manage', 'yatira', 'Manage all Yatira sales leads.', NOW(), NOW()),
('View JMV inventory', 'jmv.inventory.view', 'jmv', 'View JMV inventory.', NOW(), NOW()),
('Manage JMV inventory items', 'jmv.inventory.items.manage', 'jmv', 'Manage JMV inventory items.', NOW(), NOW()),
('Record JMV stock movements', 'jmv.inventory.movements.manage', 'jmv', 'Record JMV stock movements.', NOW(), NOW()),
('Adjust and reverse JMV stock', 'jmv.inventory.adjustments.manage', 'jmv', 'Adjust and reverse JMV stock.', NOW(), NOW()),
('View JMV inventory reports', 'jmv.inventory.reports.view', 'jmv', 'View and export JMV inventory reports.', NOW(), NOW()),
('Create JMV stock requests', 'jmv.inventory.requests.create', 'jmv', 'Create JMV stock requests.', NOW(), NOW()),
('Approve JMV stock requests', 'jmv.inventory.requests.approve', 'jmv', 'Approve JMV stock requests.', NOW(), NOW());

-- Villa Shipping: application/tab access by department.
INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'shipping.operations.access', 'shipping.vessel_management.access',
    'shipping.voyages.access', 'shipping.technical_defects.access',
    'shipping.certificates.access', 'shipping.dry_docking.access'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND LOWER(TRIM(d.name)) IN ('marine operation', 'marine operations', 'technical department');

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'shipping.operations.access', 'shipping.technical_defects.access', 'shipping.certificates.access'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND LOWER(TRIM(d.name)) = 'safety and compliance';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'shipping.procurement.access'
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND LOWER(TRIM(d.name)) IN ('procurement', 'purchasing');

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'shipping.inventory.access'
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND LOWER(TRIM(d.name)) = 'inventory and warehouse';

-- Villa Shipping: manager, captain, and technical workflow actions.
INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'vessels.view_all', 'tech_defects.create', 'tech_defects.view_company',
    'tech_defects.submit_review', 'tech_defects.review', 'tech_defects.verify',
    'certificates.manage'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND p.code IN ('operations-manager', 'operation-manager', 'marine-operations-manager', 'vessel-manager');

-- Managers may also originate a report and submit it into the existing review workflow.
INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN ('tech_defects.create', 'tech_defects.submit_review')
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND (
      p.code IN ('operations-manager', 'operation-manager', 'marine-operations-manager', 'vessel-manager', 'technical-manager')
      OR (
          LOWER(TRIM(p.legacy_role)) = 'manager'
          AND LOWER(TRIM(d.name)) IN ('marine operation', 'marine operations', 'technical department')
      )
  );

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'vessels.view_assigned', 'tech_defects.create', 'tech_defects.view_assigned',
    'tech_defects.submit_review'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND p.code = 'vessel-captain';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'vessels.view_all', 'tech_defects.create', 'tech_defects.view_company',
    'tech_defects.submit_review', 'tech_defects.assess',
    'tech_defects.assign_action', 'tech_defects.perform_action',
    'tech_defects.update_closeout', 'tech_defects.upload_evidence',
    'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND p.code = 'technical-manager';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.assess',
    'tech_defects.assign_action', 'tech_defects.perform_action',
    'tech_defects.update_closeout', 'tech_defects.upload_evidence',
    'tech_defects.request_support', 'tech_defects.submit', 'tech_defects.verify'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND p.code = 'chief-engineer';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'vessels.view_assigned', 'tech_defects.view_assigned', 'tech_defects.perform_action',
    'tech_defects.update_closeout', 'tech_defects.upload_evidence',
    'tech_defects.request_support', 'tech_defects.submit'
)
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND p.code IN ('second-engineer', 'maintenance-technician');

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN ('certificates.manage', 'vessels.view_assigned')
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND p.code = 'liaison-officer';

-- Yatira: permissions by department, with restricted management actions by position.
INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'yatira.suppliers.view'
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND LOWER(TRIM(d.name)) IN ('procurement', 'purchasing', 'finance and accounting');

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN ('yatira.suppliers.manage', 'yatira.reports.view')
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND p.code IN ('procurement-manager', 'purchaser');

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'yatira.assets.view'
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND LOWER(TRIM(d.name)) IN (
      'project operations', 'engineering', 'equipment and maintenance',
      'inventory and warehouse', 'finance and accounting'
  );

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'yatira.consumables.view'
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND LOWER(TRIM(d.name)) IN (
      'project operations', 'engineering', 'equipment and maintenance', 'inventory and warehouse'
  );

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'yatira.reports.view'
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND LOWER(TRIM(d.name)) IN ('procurement', 'purchasing', 'finance and accounting', 'inventory and warehouse');

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON
    (p.code = 'inventory-manager' AND pe.slug IN (
        'yatira.assets.create', 'yatira.assets.update', 'yatira.assets.dispose',
        'yatira.consumables.manage'
    ))
    OR (p.code = 'maintenance-manager' AND pe.slug = 'yatira.assets.update')
    OR (p.code = 'warehouse-staff' AND pe.slug = 'yatira.consumables.manage')
WHERE LOWER(dv.name) LIKE '%yatira%';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN ('yatira.sales.view', 'yatira.sales.create', 'yatira.sales.update')
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND LOWER(TRIM(d.name)) = 'sales and business development';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug = 'yatira.sales.manage'
WHERE LOWER(dv.name) LIKE '%yatira%'
  AND p.code = 'sales-manager';

-- JMV: inventory permissions by position.
INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'jmv.inventory.view', 'jmv.inventory.items.manage',
    'jmv.inventory.movements.manage', 'jmv.inventory.adjustments.manage',
    'jmv.inventory.reports.view', 'jmv.inventory.requests.create',
    'jmv.inventory.requests.approve'
)
WHERE LOWER(dv.name) LIKE '%jmv%'
  AND p.code = 'inventory-manager';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN (
    'jmv.inventory.view', 'jmv.inventory.movements.manage', 'jmv.inventory.requests.create'
)
WHERE LOWER(dv.name) LIKE '%jmv%'
  AND p.code = 'warehouse-staff';

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id FROM positions p
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN ('jmv.inventory.view', 'jmv.inventory.reports.view')
WHERE LOWER(dv.name) LIKE '%jmv%'
  AND p.code IN ('procurement-manager', 'purchaser', 'finance-manager', 'accountant');

-- Access-profile safety net. Company-level route scoping still limits users to their company.
INSERT IGNORE INTO access_role_permission (access_role_id, permission_id)
SELECT ar.id, pe.id
FROM access_roles ar
CROSS JOIN permissions pe
WHERE ar.slug = 'system-administrator';

INSERT IGNORE INTO access_role_permission (access_role_id, permission_id)
SELECT ar.id, pe.id
FROM access_roles ar
CROSS JOIN permissions pe
WHERE ar.slug = 'company-administrator'
  AND (pe.slug = 'users.manage.company' OR pe.module IN ('shipping', 'yatira', 'jmv'));

COMMIT;

-- Verification report: every active position with its resulting permission count.
SELECT
    dv.name AS company,
    d.name AS department,
    p.name AS position,
    p.code,
    COUNT(pp.permission_id) AS permission_count
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
LEFT JOIN permission_position pp ON pp.position_id = p.id
WHERE p.is_active = 1
GROUP BY dv.name, d.name, p.id, p.name, p.code
ORDER BY dv.name, d.name, p.name;
