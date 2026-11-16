# Haruhay audit — 9 November 2026

Scope: company and executive access, the Villa Group property connection, Units, Tenants, Lease Contracts, Billing, Collections, payment proofs, Reports and exports.

## Fixes made

| Finding | Change |
| --- | --- |
| Month-end dashboard totals could omit records on the last calendar day when dates are represented as timestamps. | Billing and Collections now use inclusive date comparisons. Added a last-day-of-month regression test. |
| Room rates and property measurements accepted more than two decimal places, allowing database-dependent rounding. | Added server validation for two-decimal precision. Invalid values are rejected before saving. |
| A tenant who confirmed an early move-in could still get the old reservation date as the suggested lease start. | The contract form now prefers actual move-in, with scheduled move-in as the fallback. |
| Editing Billing Starts did not synchronize an untouched First Due Date or the required anniversary due day. | The form synchronizes defaults, preserves an explicitly edited first due date, and makes the anniversary due day follow Billing Starts. Calendar-month due days remain editable. |
| The property attachment limit was checked before the transaction, leaving a concurrent-upload race. | The property is locked and its attachment count is checked inside the save transaction. Rejected uploads do not partially change the property. |
| Property-filtered collection reports followed the tenant's current room, potentially reclassifying old receipts after a transfer. | Receipts now store their original property ID. Existing receipts were backfilled. Reports use that snapshot. Historical rooms cannot be reassigned to another property once they have contract or billing history. |
| Generated room numbers sorted alphabetically, putting DR-100 before DR-98. | Rooms now sort by their numeric ID within property and floor. |

No existing receipt amount, bill amount, payment allocation or user permission was changed by these fixes. The receipt-property migration adds historical identification only.

## Verification

- Initial full application test run: **232 passed**.
- Final full application test run: **240 passed, 2,648 assertions**, including **85 Haruhay / Villa Group tests**.
- **41 Blade templates** compiled and passed PHP syntax checking, including Haruhay, Villa Group and the shared application layout.
- **8 inline JavaScript scripts** passed syntax checking. DOM-based checks exercise leap-year lease dates, due-date synchronization, editable rent/deposit amounts, payment versus advance modes, online proof requirements and all four report charts with empty and populated data.
- Production asset build completed successfully.
- `git diff --check` completed without whitespace errors.
- New migration: `2026_11_09_000002_snapshot_collection_property` applied successfully to the local database.

Automated tests cover capacity/reservations, early move-in, private file access, executive read-only access, foreign-company access denial, immutable issued bills, duplicate invoices/receipts, partial and full payments, advance credit, automatic credit application, sequential monthly billing, payment proofs, report date filters, aging, property filters, CSV formula protection and pagination.

## Existing-data checks

A read-only integrity check covered **14 rooms, 6 tenants, 6 contracts, 8 bills and 13 receipts** at the audit snapshot.

No instances were found of:

- Rooms exceeding capacity or disagreeing with their active/reserved tenants.
- Bills overpaid or totals disagreeing with their charge breakdown.
- Receipts allocated beyond the received amount.
- Missing files referenced by an uploaded payment proof or signed contract.
- Missing receipt property snapshots after the migration.

The count-only check result is stored locally in `storage/app/haruhay-audit/data-check.json`; it contains no tenant names or payment references.

## Limits and optional next work

Update: the five feature upgrades listed below have now been implemented. See [Haruhay operational upgrades](haruhay-upgrades-2026-11-09.md) for their behavior, access presets and historical-data limits. The following notes describe the original audit snapshot.

- Tests use isolated SQLite. The existing MySQL data was checked read-only; this was not a concurrent-load test against the live database.
- Frontend behavior was checked through rendered views and a mock DOM. A complete manual browser walkthrough, mobile layout review and physical print review remain useful before rollout.
- Occupancy reports intentionally show current room status. Historical occupancy requires periodic snapshots.
- A formal payment reversal/refund workflow and security-deposit return ledger are separate features. Receipt deletion or silent edits should not replace those workflows.
- A tenant room transfer preserves the original contract and invoice history. A dedicated contract-amendment flow would make new-room rent agreements clearer.
- Define and assign Haruhay staff position permissions before non-admin rollout; this audit did not grant additional access.

## Repeat the checks

```powershell
& 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe' artisan test --compact
node tests/haruhay-ui-audit.mjs
npm.cmd run build
git diff --check
```

The test bootstrap refuses to run if configuration is cached or the test database is not the isolated in-memory SQLite database.
