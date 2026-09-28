-- Production-safe ETA precision update.
-- Existing voyage records are retained. Old DATE values become 00:00:00,
-- while new voyage ETAs can preserve the exact user-entered time.

ALTER TABLE voyage_logs_header
    MODIFY COLUMN arrival_date DATETIME NULL;
