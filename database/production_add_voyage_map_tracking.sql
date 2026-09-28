-- Production-safe Voyage Map Tracking schema for phpMyAdmin.
-- Existing vessels, voyages, ports, activities, and users are not changed or deleted.
-- Re-importing this file is safe on MariaDB/MySQL versions that support
-- ADD COLUMN IF NOT EXISTS (Hostinger MariaDB supports this syntax).

START TRANSACTION;

ALTER TABLE ports
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10,7) NULL;

ALTER TABLE voyage_logs_header
    ADD COLUMN IF NOT EXISTS origin_latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS origin_longitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS destination_latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS destination_longitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS current_latitude DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS current_longitude DECIMAL(10,7) NULL;

CREATE TABLE IF NOT EXISTS vessel_position_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    vessel_id BIGINT UNSIGNED NOT NULL,
    voyage_id BIGINT UNSIGNED NOT NULL,
    voyage_activity_id BIGINT UNSIGNED NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_meters DECIMAL(10,2) NULL,
    location_name VARCHAR(255) NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'map_pin',
    recorded_by BIGINT UNSIGNED NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX vessel_position_logs_vessel_recorded_index (vessel_id, recorded_at),
    INDEX vessel_position_logs_voyage_recorded_index (voyage_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

SELECT 'Voyage map tracking schema is ready.' AS result;
