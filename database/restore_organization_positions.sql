-- Restore the Organization Setup department/position catalog from the
-- September 11, 2026 positions export. Run only after loading that export
-- into villa_positions_stage.positions.

START TRANSACTION;

-- Departments referenced by the exported positions catalog but missing from
-- the recovered database.
INSERT INTO departments (id, name, division_id, created_at, updated_at) VALUES
(8,  'Marine Operations',             2, NOW(), NOW()),
(9,  'Technical Department',          2, NOW(), NOW()),
(10, 'Safety and Compliance',         2, NOW(), NOW()),
(11, 'Procurement',                   2, NOW(), NOW()),
(12, 'Inventory and Warehouse',       2, NOW(), NOW()),
(13, 'Finance and Accounting',        2, NOW(), NOW()),
(14, 'Human Resources',               2, NOW(), NOW()),
(15, 'Information Technology',        2, NOW(), NOW()),
(16, 'Project Operations',            1, NOW(), NOW()),
(17, 'Engineering',                   1, NOW(), NOW()),
(18, 'Equipment and Maintenance',     1, NOW(), NOW()),
(19, 'Safety and Compliance',         1, NOW(), NOW()),
(20, 'Procurement',                   1, NOW(), NOW()),
(21, 'Inventory and Warehouse',       1, NOW(), NOW()),
(22, 'Finance and Accounting',        1, NOW(), NOW()),
(23, 'Human Resources',               1, NOW(), NOW()),
(24, 'Mining Operations',             3, NOW(), NOW()),
(25, 'Equipment Maintenance',         3, NOW(), NOW()),
(26, 'Geology',                       3, NOW(), NOW()),
(27, 'Safety and Environment',        3, NOW(), NOW()),
(28, 'Procurement',                   3, NOW(), NOW()),
(29, 'Inventory and Warehouse',       3, NOW(), NOW()),
(30, 'Finance and Accounting',        3, NOW(), NOW()),
(31, 'Human Resources',               3, NOW(), NOW()),
(32, 'Executive Office',              4, NOW(), NOW()),
(33, 'Corporate Finance',             4, NOW(), NOW()),
(34, 'Human Resources',               4, NOW(), NOW()),
(35, 'Information Technology',        4, NOW(), NOW()),
(36, 'Research and Development',      4, NOW(), NOW()),
(37, 'Internal Audit',                4, NOW(), NOW()),
(38, 'Legal and Administration',      4, NOW(), NOW()),
(39, 'Corporate Procurement',         4, NOW(), NOW()),
(40, 'Operations',                    5, NOW(), NOW()),
(41, 'Procurement',                   5, NOW(), NOW()),
(42, 'Finance and Accounting',        5, NOW(), NOW()),
(43, 'Human Resources',               5, NOW(), NOW()),
(44, 'Information Technology',        5, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    division_id = VALUES(division_id),
    updated_at = VALUES(updated_at);

-- Release the three foreign-key references before replacing the incomplete
-- ten-row catalog. Permissions are rebuilt after the catalog is restored.
DELETE FROM permission_position;
UPDATE company_user_assignments SET position_id = NULL;
UPDATE users SET position_id = NULL;
DELETE FROM positions;

INSERT INTO positions
    (id, division_id, department_id, name, code, legacy_role, description,
     is_active, created_at, updated_at)
SELECT
    id, division_id, department_id, name, code, legacy_role, description,
    is_active, created_at, updated_at
FROM villa_positions_stage.positions
ORDER BY id;

-- Restore the recovered users to positions that belong to their company and
-- department. The executive test account has no matching historical Villa
-- Shipping executive position, so its position remains intentionally blank;
-- its Executive Viewer access role remains intact.
UPDATE users SET department_id = 1,  position_id = 1   WHERE id = 1;
UPDATE users SET department_id = 6,  position_id = 7   WHERE id IN (17,18,19,20,21);
UPDATE users SET division_id = 1, department_id = 46, position_id = 100 WHERE id = 22;
UPDATE users SET department_id = 8,  position_id = 97  WHERE id IN (23,24,26);
UPDATE users SET department_id = 8,  position_id = 98  WHERE id = 25;
UPDATE users SET division_id = 1, department_id = 2,  position_id = 4   WHERE id = 29;
UPDATE users SET division_id = 4, department_id = 32, position_id = 69  WHERE id = 30;
UPDATE users SET division_id = 2, department_id = 6,  position_id = NULL WHERE id = 31;
UPDATE users SET department_id = 9,  position_id = 18  WHERE id = 32;

UPDATE company_user_assignments SET department_id = 1, position_id = 1 WHERE user_id = 1;
UPDATE company_user_assignments SET division_id = 2, department_id = 6, position_id = 7 WHERE user_id IN (17,18,19,20,21);
UPDATE company_user_assignments SET division_id = 1, department_id = 46, position_id = 100 WHERE user_id = 22;
UPDATE company_user_assignments SET division_id = 2, department_id = 8, position_id = 97 WHERE user_id IN (23,24,26);
UPDATE company_user_assignments SET division_id = 2, department_id = 8, position_id = 98 WHERE user_id = 25;
UPDATE company_user_assignments SET division_id = 1, department_id = 2, position_id = 4 WHERE user_id = 29;
UPDATE company_user_assignments SET division_id = 4, department_id = 32, position_id = 69 WHERE user_id = 30;
UPDATE company_user_assignments SET division_id = 2, department_id = 6, position_id = NULL WHERE user_id = 31;
UPDATE company_user_assignments SET division_id = 2, department_id = 9, position_id = 18 WHERE user_id = 32;

ALTER TABLE positions AUTO_INCREMENT = 101;
ALTER TABLE departments AUTO_INCREMENT = 47;

COMMIT;
