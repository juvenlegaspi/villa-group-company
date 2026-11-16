# Haruhay operational upgrades — November 9, 2026

All five follow-up upgrades from the audit are implemented. The two upgrade migrations have been applied to the local database.

## Payments and deposits

- Receipt details now provide a permission-controlled reversal with a required reason. Original receipt and allocation records remain in history; reversed allocations reopen the affected balances. A reversal corrects the record and does not represent a cash refund.
- Tenant details and collections link to a separate security-deposit ledger. Staff can record actual deposits; authorized reviewers can record deductions, refunds and reversals. Refunds require a moved-out tenant. Transactions cannot make the held deposit negative, including when backdated.
- Online deposit receipts require a private payment proof. Security deposits remain separate from rent collections and have their own report.

## Room transfers and occupancy

- Tenant details provide Room Transfer for eligible tenants and authorized staff. The transfer creates a linked contract amendment and preserves the original agreement and issued invoices.
- The physical room changes today. The existing billing cycle and any issued prepaid coverage remain under the original agreement; the amended rent starts at the next unbilled coverage date. Scheduled future contracts and unresolved drafts must be resolved first.
- Room changes record occupancy snapshots. Reports provide an Occupancy As Of date. Existing rooms received a baseline when the migration ran; dates before recorded history show unavailable data rather than invented occupancy.

## Staff access

System administrators can open Staff Access from the Haruhay application cards or sidebar. Changes require a reason and retain an audit history. Primary company assignments are synchronized with the selected position.

| Preset | Permissions |
| --- | --- |
| Property Manager | Operational management, collections, reversals, deposits, transfers and reports |
| Property Staff | Rooms, tenants, contracts, billing, transfers and reports |
| Cashier | Record collections, receive deposits and view reports |
| Finance Reviewer | Reverse collections, manage deposits and view reports |
| Read Only | View access without operational changes |

Presets were installed without reassigning existing users. Administrators must choose the appropriate preset for each staff member. Executive access remains read only.

## Validation

Final full test run: **250 passed, 2,786 assertions**. Both upgrade migrations are confirmed applied locally. `git diff --check` passed.

Regression coverage includes reversal balances, corrected reference reuse, advance allocations, private deposit proofs, deposit refunds, backdated ledger protection, contract amendments, historical occupancy, permission boundaries and audited staff assignments. Tests use isolated in-memory SQLite; live tenant financial records were not used as test fixtures.

Frontend checks verify ten inline scripts, dates, editable amounts, proof requirements and all four charts. The production asset build and Blade syntax checks also passed. A full manual browser and physical print walkthrough was not performed.
