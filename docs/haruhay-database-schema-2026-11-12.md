# Haruhay database tables and columns

Read-only schema inventory captured on November 12, 2026. The executive dashboard adds no database tables or columns; it reads these existing records.

## haruhay_access_audits

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `user_id` | `bigint unsigned` | No |
| `actor_id` | `bigint unsigned` | Yes |
| `old_position_id` | `bigint unsigned` | Yes |
| `new_position_id` | `bigint unsigned` | Yes |
| `reason` | `text` | No |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |

## haruhay_billing_payments

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `billing_id` | `bigint unsigned` | No |
| `amount` | `decimal(12,2)` | No |
| `paid_on` | `date` | No |
| `reference` | `varchar(255)` | No |
| `created_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `collection_id` | `bigint unsigned` | Yes |
| `voided_at` | `timestamp` | Yes |
| `voided_by` | `bigint unsigned` | Yes |
| `void_reason` | `text` | Yes |

## haruhay_billings

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `code` | `varchar(255)` | No |
| `tenant_id` | `bigint unsigned` | No |
| `contract_id` | `bigint unsigned` | No |
| `unit_id` | `bigint unsigned` | No |
| `tenant_name` | `varchar(255)` | No |
| `property_name` | `varchar(255)` | No |
| `room_no` | `varchar(255)` | No |
| `period_start` | `date` | No |
| `period_end` | `date` | No |
| `issued_on` | `date` | Yes |
| `due_on` | `date` | No |
| `rent` | `decimal(12,2)` | No |
| `water` | `decimal(12,2)` | Yes |
| `electric` | `decimal(12,2)` | Yes |
| `utilities_other` | `decimal(12,2)` | No |
| `parking` | `decimal(12,2)` | No |
| `addons_other` | `decimal(12,2)` | No |
| `penalty` | `decimal(12,2)` | No |
| `discount` | `decimal(12,2)` | No |
| `total` | `decimal(12,2)` | No |
| `remarks` | `text` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `finalized_at` | `timestamp` | Yes |
| `finalized_by` | `bigint unsigned` | Yes |
| `utilities_ready` | `tinyint(1)` | No |

## haruhay_collections

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `code` | `varchar(255)` | No |
| `submission_token` | `char(36)` | No |
| `tenant_id` | `bigint unsigned` | No |
| `initial_billing_id` | `bigint unsigned` | Yes |
| `tenant_name` | `varchar(255)` | No |
| `property_name` | `varchar(255)` | No |
| `room_no` | `varchar(255)` | No |
| `paid_on` | `date` | No |
| `amount` | `decimal(12,2)` | No |
| `amount_due` | `decimal(12,2)` | Yes |
| `method` | `varchar(30)` | No |
| `external_reference` | `varchar(100)` | Yes |
| `remarks` | `text` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `proof_path` | `varchar(255)` | Yes |
| `proof_name` | `varchar(255)` | Yes |
| `property_id` | `bigint unsigned` | Yes |
| `voided_at` | `timestamp` | Yes |
| `voided_by` | `bigint unsigned` | Yes |
| `void_reason` | `text` | Yes |
| `active_reference` | `varchar(100)` | Yes |

## haruhay_deposit_entries

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `code` | `varchar(255)` | No |
| `submission_token` | `char(36)` | No |
| `tenant_id` | `bigint unsigned` | No |
| `property_id` | `bigint unsigned` | No |
| `contract_id` | `bigint unsigned` | Yes |
| `tenant_name` | `varchar(255)` | No |
| `property_name` | `varchar(255)` | No |
| `room_no` | `varchar(255)` | No |
| `type` | `varchar(20)` | No |
| `amount` | `decimal(12,2)` | No |
| `occurred_on` | `date` | No |
| `method` | `varchar(30)` | Yes |
| `reference` | `varchar(100)` | Yes |
| `reason` | `text` | No |
| `active_reference` | `varchar(100)` | Yes |
| `proof_path` | `varchar(255)` | Yes |
| `proof_name` | `varchar(255)` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `voided_at` | `timestamp` | Yes |
| `voided_by` | `bigint unsigned` | Yes |
| `void_reason` | `text` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |

## haruhay_lease_contracts

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `code` | `varchar(255)` | No |
| `tenant_id` | `bigint unsigned` | No |
| `unit_id` | `bigint unsigned` | No |
| `previous_contract_id` | `bigint unsigned` | Yes |
| `tenant_name` | `varchar(255)` | No |
| `property_name` | `varchar(255)` | No |
| `room_no` | `varchar(255)` | No |
| `floor` | `smallint unsigned` | No |
| `starts_on` | `date` | No |
| `ends_on` | `date` | No |
| `monthly_rent` | `decimal(12,2)` | No |
| `billing_starts_on` | `date` | No |
| `first_due_on` | `date` | No |
| `due_day` | `tinyint unsigned` | No |
| `billing_cycle` | `varchar(30)` | No |
| `first_month_policy` | `varchar(20)` | No |
| `first_month_charge` | `decimal(12,2)` | No |
| `deposit_required` | `decimal(12,2)` | No |
| `advance_required` | `decimal(12,2)` | No |
| `advance_application` | `text` | Yes |
| `remarks` | `text` | Yes |
| `document_path` | `varchar(255)` | Yes |
| `document_name` | `varchar(255)` | Yes |
| `terminated_on` | `date` | Yes |
| `termination_reason` | `text` | Yes |
| `terminated_by` | `bigint unsigned` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `amends_contract_id` | `bigint unsigned` | Yes |
| `billing_coverage_starts_on` | `date` | Yes |
| `amendment_reason` | `text` | Yes |

## haruhay_occupancy_events

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `unit_id` | `bigint unsigned` | No |
| `property_id` | `bigint unsigned` | No |
| `property_name` | `varchar(255)` | No |
| `room_no` | `varchar(255)` | No |
| `status` | `varchar(20)` | No |
| `room_condition` | `varchar(40)` | No |
| `capacity` | `smallint unsigned` | Yes |
| `occupied_tenant_ids` | `json` | No |
| `reserved_tenant_ids` | `json` | No |
| `recorded_at` | `timestamp` | No |
| `source` | `varchar(30)` | No |
| `created_by` | `bigint unsigned` | Yes |

## haruhay_properties

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `division_id` | `bigint unsigned` | No |
| `code` | `varchar(255)` | Yes |
| `name` | `varchar(255)` | No |
| `owner` | `varchar(255)` | No |
| `type` | `varchar(50)` | No |
| `street_barangay` | `varchar(255)` | No |
| `city` | `varchar(255)` | No |
| `province` | `varchar(255)` | No |
| `lot_area` | `decimal(14,2)` | Yes |
| `floor_area` | `decimal(14,2)` | Yes |
| `floors` | `smallint unsigned` | Yes |
| `status` | `varchar(50)` | No |
| `person_in_charge` | `varchar(255)` | Yes |
| `contact_number` | `varchar(50)` | Yes |
| `remarks` | `text` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `updated_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `location` | `text` | Yes |

## haruhay_property_attachments

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `property_id` | `bigint unsigned` | No |
| `path` | `varchar(255)` | No |
| `original_name` | `varchar(255)` | No |
| `size` | `bigint unsigned` | No |
| `uploaded_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |

## haruhay_tenant_audits

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `tenant_id` | `bigint unsigned` | No |
| `user_id` | `bigint unsigned` | Yes |
| `action` | `varchar(30)` | No |
| `before` | `json` | Yes |
| `after` | `json` | No |
| `created_at` | `timestamp` | No |

## haruhay_tenants

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `code` | `varchar(255)` | No |
| `unit_id` | `bigint unsigned` | No |
| `name` | `varchar(255)` | No |
| `contact_number` | `varchar(50)` | No |
| `email` | `varchar(255)` | Yes |
| `move_in_date` | `date` | Yes |
| `move_out_date` | `date` | Yes |
| `status` | `varchar(20)` | No |
| `emergency_contact_name` | `varchar(255)` | Yes |
| `emergency_contact_number` | `varchar(50)` | Yes |
| `remarks` | `text` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `updated_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `scheduled_move_in_date` | `date` | Yes |

## haruhay_units

| Column | Database type | Nullable |
| --- | --- | --- |
| `id` | `bigint unsigned` | No |
| `property_id` | `bigint unsigned` | No |
| `unit_no` | `varchar(50)` | No |
| `floor` | `smallint unsigned` | No |
| `unit_type` | `varchar(255)` | No |
| `area` | `decimal(12,2)` | Yes |
| `capacity` | `smallint unsigned` | Yes |
| `rent_basis` | `varchar(30)` | No |
| `monthly_rent` | `decimal(12,2)` | Yes |
| `shared_rate` | `decimal(12,2)` | Yes |
| `rate_notes` | `text` | Yes |
| `status` | `varchar(20)` | No |
| `occupied_by` | `text` | Yes |
| `move_in_date` | `date` | Yes |
| `remarks` | `text` | Yes |
| `created_by` | `bigint unsigned` | Yes |
| `updated_by` | `bigint unsigned` | Yes |
| `created_at` | `timestamp` | Yes |
| `updated_at` | `timestamp` | Yes |
| `room_condition` | `varchar(30)` | No |

