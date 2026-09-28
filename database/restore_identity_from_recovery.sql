-- Restore the June 3, 2026 identity snapshot from villa_user_recovery.
-- Scope: organization references, users, and the minimum access records required by users.
-- Operational tables are intentionally untouched.

START TRANSACTION;

INSERT INTO villa.divisions (id, name, created_at, updated_at)
SELECT id, name, created_at, updated_at
FROM villa_user_recovery.divisions;

INSERT INTO villa.departments (id, name, division_id, created_at, updated_at)
SELECT d.id,
       d.name,
       CASE d.id
           WHEN 4 THEN 4
           WHEN 6 THEN 2
           WHEN 7 THEN 1
           ELSE NULL
       END AS division_id,
       d.created_at,
       d.updated_at
FROM villa_user_recovery.departments d;

INSERT INTO villa.positions
    (division_id, department_id, name, code, legacy_role, description, is_active, created_at, updated_at)
SELECT DISTINCT
       u.division_id,
       u.department_id,
       CASE LOWER(u.role)
           WHEN 'captain' THEN 'Vessel Captain'
           WHEN 'purchaser' THEN 'Purchaser'
           WHEN 'it' THEN 'IT Specialist'
           WHEN 'hr' THEN 'HR Officer'
           WHEN 'r&d' THEN 'R&D Specialist'
           WHEN 'manager' THEN 'Department Manager'
           WHEN 'owner' THEN 'Executive'
           WHEN 'admin' THEN 'System Administrator'
           ELSE 'Staff'
       END AS name,
       CASE LOWER(u.role)
           WHEN 'captain' THEN 'vessel-captain'
           WHEN 'purchaser' THEN 'purchaser'
           WHEN 'it' THEN 'it-specialist'
           WHEN 'hr' THEN 'hr-officer'
           WHEN 'r&d' THEN 'r-d-specialist'
           WHEN 'manager' THEN 'department-manager'
           WHEN 'owner' THEN 'executive'
           WHEN 'admin' THEN 'system-administrator'
           ELSE 'staff'
       END AS code,
       LOWER(u.role) AS legacy_role,
       NULL AS description,
       1 AS is_active,
       NOW() AS created_at,
       NOW() AS updated_at
FROM villa_user_recovery.users u
WHERE u.division_id IS NOT NULL
  AND u.department_id IS NOT NULL;

INSERT INTO villa.users
    (id, name, lastname, username, email, cell_number, avatar_path, password, remember_token,
     created_by, is_admin, role, department_id, position_id, access_role_id,
     must_change_password, status, created_at, updated_at, division_id, new_department_id)
SELECT u.id,
       u.name,
       u.lastname,
       u.username,
       u.email,
       u.cell_number,
       NULL AS avatar_path,
       u.password,
       NULL AS remember_token,
       u.created_by,
       u.is_admin,
       u.role,
       u.department_id,
       p.id AS position_id,
       ar.id AS access_role_id,
       u.must_change_password,
       u.status,
       u.created_at,
       u.updated_at,
       u.division_id,
       NULL AS new_department_id
FROM villa_user_recovery.users u
JOIN villa.positions p
  ON p.division_id = u.division_id
 AND p.department_id = u.department_id
 AND p.legacy_role = LOWER(u.role)
JOIN villa.access_roles ar
  ON ar.slug = CASE
      WHEN LOWER(u.role) = 'owner' THEN 'executive-viewer'
      WHEN LOWER(u.role) = 'admin' OR u.is_admin = 1 THEN 'system-administrator'
      WHEN LOWER(u.role) = 'manager' THEN 'company-approver'
      ELSE 'standard-user'
  END;

INSERT INTO villa.company_user_assignments
    (user_id, division_id, department_id, position_id, access_role_id,
     is_primary, is_active, effective_from, effective_until, created_at, updated_at)
SELECT u.id,
       u.division_id,
       u.department_id,
       u.position_id,
       u.access_role_id,
       1,
       u.status,
       DATE(COALESCE(u.created_at, NOW())),
       NULL,
       NOW(),
       NOW()
FROM villa.users u;

INSERT INTO villa.approval_authorities
    (user_id, division_id, department_id, module, action, approval_level,
     amount_limit, is_active, effective_from, effective_until, created_at, updated_at)
SELECT u.id,
       u.division_id,
       u.department_id,
       'technical_defects',
       'approve',
       1,
       NULL,
       u.status,
       DATE(COALESCE(u.created_at, NOW())),
       NULL,
       NOW(),
       NOW()
FROM villa.users u
WHERE LOWER(u.role) = 'manager';

INSERT IGNORE INTO villa.permission_position (position_id, permission_id)
SELECT DISTINCT u.position_id, per.id
FROM villa.users u
JOIN villa.permissions per
  ON per.slug IN (
      'tech_defects.create',
      'tech_defects.start_repair',
      'tech_defects.request_support'
  )
WHERE LOWER(u.role) = 'captain';

COMMIT;
