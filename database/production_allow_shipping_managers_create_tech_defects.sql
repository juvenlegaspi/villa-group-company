-- Production-safe grant for Villa Shipping managers.
-- Adds only missing permission catalog and position-permission rows.
-- Existing users, reports, vessel assignments, and workflow records are untouched.

START TRANSACTION;

INSERT IGNORE INTO permissions (name, slug, module, description, created_at, updated_at) VALUES
('Create technical defect reports', 'tech_defects.create', 'tech_defects', 'Create technical defect reports.', NOW(), NOW()),
('Submit technical defects for review', 'tech_defects.submit_review', 'tech_defects', 'Submit new technical defect reports for manager review.', NOW(), NOW());

INSERT IGNORE INTO permission_position (position_id, permission_id)
SELECT p.id, pe.id
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
JOIN permissions pe ON pe.slug IN ('tech_defects.create', 'tech_defects.submit_review')
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND (
      p.code IN (
          'operations-manager',
          'operation-manager',
          'marine-operations-manager',
          'vessel-manager',
          'technical-manager'
      )
      OR (
          LOWER(TRIM(p.legacy_role)) = 'manager'
          AND LOWER(TRIM(d.name)) IN (
              'marine operation',
              'marine operations',
              'technical department'
          )
      )
  );

COMMIT;

SELECT
    dv.name AS company,
    d.name AS department,
    p.name AS position,
    p.code,
    GROUP_CONCAT(pe.slug ORDER BY pe.slug SEPARATOR ', ') AS report_permissions
FROM positions p
JOIN departments d ON d.id = p.department_id
JOIN divisions dv ON dv.id = p.division_id
LEFT JOIN permission_position pp ON pp.position_id = p.id
LEFT JOIN permissions pe ON pe.id = pp.permission_id
    AND pe.slug IN ('tech_defects.create', 'tech_defects.submit_review')
WHERE LOWER(dv.name) LIKE '%villa%shipping%'
  AND (
      p.code IN (
          'operations-manager',
          'operation-manager',
          'marine-operations-manager',
          'vessel-manager',
          'technical-manager'
      )
      OR LOWER(TRIM(p.legacy_role)) = 'manager'
  )
GROUP BY dv.name, d.name, p.id, p.name, p.code
ORDER BY d.name, p.name;
