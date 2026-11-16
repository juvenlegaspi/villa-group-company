# Haruhay Excel report export

The Reports page now downloads a genuine `.xlsx` workbook through **Export Excel**. The selected date range, property, tenant and occupancy date apply to the workbook. Screen pagination does not limit exported rows.

The workbook contains nine sheets: Summary, Period Summary, Billing, Collections, Outstanding, Tenant Ledger, Occupancy, Lease Contracts and Security Deposits. Each sheet includes the reporting period, scope and generation time. Data sheets have frozen headings, column filters, formatted numeric values and record counts. Financial totals appear where meaningful; deposits are not summed across received, deducted and refunded transactions.

The Summary explains report definitions: issued invoices exclude drafts; collections count actual receipts once; applying credit is not another collection; outstanding includes earlier unpaid invoices through the as-of date; occupancy history is unavailable before recorded snapshots; lease status is current; deposits are separate from rent.

Names, references and dates are stored as literal text, so leading zeroes and formula-like input remain safe. Monetary data is stored as numeric cells. The original formula-safe CSV endpoint remains available with `format=csv`.

Validation: report and audit tests passed (14 tests, 170 assertions), including workbook ZIP/XML structure, formatting, numeric cells, literal text, foreign-company exclusion and export beyond ten rows. Frontend script checks and `git diff --check` also passed.
