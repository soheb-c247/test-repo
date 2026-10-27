# AccuCare Pathology Lab

A single-laboratory management application for recording laboratory tests and issuing pathology reports.
One administrator signs in, maintains configurable test definitions, registers patients, enters structured results and
releases print-ready PDF reports. Released reports are locked; corrections are issued as tracked revisions.

Built with Laravel 12, Blade, Alpine.js, Tailwind CSS and dompdf on MySQL.

---

## Features

| Area | What it does |
|---|---|
| **Sign-in** | Single administrator, no public registration, rate-limited login, secure session, password change in-app, offline password reset command. |
| **Dashboard** | Database-backed KPIs (today vs yesterday, last 7 days vs previous 7, awaiting release, finalized, flagged), 7-day stacked chart with tooltips and table view, category / top-test / status distributions with period filter, recent reports table with search, filters, sorting and pagination. |
| **Tests** | CRUD for test definitions with a visual parameter builder (sections, 12 input types, units, precision, methods, reference intervals by sex/age, options, calculated formulas, tables / repeating rows) stored as validated JSON. Activate/deactivate, duplicate, archive/restore, version history, “needs review” workflow. |
| **Reports** | Draft → results → review → finalize. Patient lookup or registration, multi-test reports, dynamic result forms generated from each test's snapshot, informational H/L flags, live preview identical to the PDF, PDF download/print, optimistic locking, double-submit protection, controlled corrections with full revision history, repeat tests. |
| **Billing** | One bill per report priced from each test's listed price (editable per bill), amount or percentage discount, full/partial payments (cash, UPI, card, bank, other), balance tracking, cancel with reason, printable/PDF receipt without results, collection and balance-due figures, list with search, filters and sorting. |
| **Patients** | Searchable list, profile with report history, demographic edits (reports keep the details they were issued with). |
| **Reference data** | 41 test definitions derived from the supplied workbook, imported repeatably — see [`docs/WORKBOOK_MAPPING.md`](docs/WORKBOOK_MAPPING.md). |

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `gd`, `dom`, `zip`
- MySQL 8 (or MariaDB 10.6+)
- Composer 2, Node.js 20+ and npm

## Setup

```bash
composer install
npm ci

cp .env.example .env
php artisan key:generate
```

Edit `.env`:

- `DB_*` — database connection (create the database first, e.g. `CREATE DATABASE accucare CHARACTER SET utf8mb4;`).
- `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` — the administrator account created by the seeder.
  The password is hashed when seeded; remove it from `.env` afterwards if you prefer.
- `APP_TIMEZONE` — the laboratory's timezone (default `Asia/Kolkata`); dashboard day boundaries use it.
- `LAB_*` — address, phone, signatories etc. printed on reports. Blank values are simply omitted; nothing is invented.

Then:

```bash
php artisan migrate
php artisan db:seed                              # administrator + workbook test catalog
php artisan db:seed --class=DemoDataSeeder       # optional: 28 synthetic demo reports
npm run build
php artisan serve                                # http://127.0.0.1:8000
```

Sign in with the `ADMIN_EMAIL` / `ADMIN_PASSWORD` values you configured. Email matching is case-insensitive.

For development with hot reload use `composer dev` (runs the server, queue listener, log tail and Vite together).

### Useful commands

| Command | Purpose |
|---|---|
| `php artisan lab:import-reference-tests` | Import missing workbook-derived tests (never overwrites edited ones). `--refresh` updates unmodified workbook definitions as a new version. |
| `php artisan lab:admin-password` | Set a new administrator password from the terminal (there is no e-mail reset). |
| `php artisan lab:purge-demo-data` | Permanently delete synthetic demo patients/reports (`is_demo = 1`) only. |
| `php artisan db:seed --class=AdminUserSeeder` | Re-provision the administrator from `.env`; also removes admin rights from any other account. |

## Tests

Feature tests use a dedicated MySQL database (in-memory SQLite is not required). Create it once:

```sql
CREATE DATABASE accucare_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan test        # uses DB_DATABASE=accucare_testing from phpunit.xml; it is wiped on every run
npm run build
```

## Architecture

```
app/
  Http/Controllers/      thin HTTP layer (Dashboard, Tests, Reports, Patients, Profile, Auth)
  Http/Requests/         request validation (TestDefinitionRequest, ReportRequest, PatientRequest, LoginRequest)
  Http/Middleware/       EnsureLabAdministrator (single-admin gate), SecurityHeaders
  Services/
    DashboardService            aggregate KPI / chart queries
    ReportQuery                 allow-listed search, filters, sorting, pagination for report lists
    TestDefinitionService       create / update (versioning) / duplicate / archive / restore
    TestSchemaValidator         validates + normalizes the JSON parameter schema
    ReportService               draft → final → correction lifecycle, optimistic locking, idempotent create
    ReportResultValidationService  type-aware result validation, calculated parameters
    ResultEvaluator             reference-interval selection (sex/age), H/L flags, formatting
    ReportSnapshotService       immutable copies of definitions (per report item) and released reports
    ReportRevisionService       stores / rehydrates released versions
    ReportPdfService            one Blade template for preview and PDF (dompdf)
    PatientService, AuditLogger, ReferenceDataImportService
  Support/               Age, ParameterTypes, FormulaEvaluator (safe RPN evaluator), NotesFormatter
database/data/workbook_test_catalog.php   reference catalog derived from the workbook
resources/views/reports/document.blade.php   the report layout (preview + PDF)
resources/js/schema-builder.js            Alpine component behind the parameter builder
```

### Data model

| Table | Purpose |
|---|---|
| `users` | `is_lab_admin` marks the single administrator; legacy accounts cannot sign in. |
| `lab_patients` | Patient register (`patient_code`, age value + unit, sex, contact). |
| `test_definitions` | Definition metadata + JSON `schema` + `version`, soft-deleted when archived. |
| `test_definition_versions` | Full snapshot of every definition version. |
| `reports` | Report header, patient demographics as issued, status, revision, `lock_version`, `form_token`. |
| `report_items` | One per test on a report: **complete schema snapshot**, results JSON, flags. |
| `report_revisions` | Complete copy of each released version superseded by a correction. |
| `lab_bills` | Bill per report: totals, discount, amount paid, balance, status, `lock_version`, `form_token`. |
| `lab_bill_items` | Billed tests and amounts. |
| `lab_bill_payments` | Payments received against a bill. |
| `audit_events` | Who did what to which record (identifiers only, no clinical data). |

### Parameter schema (JSON)

```json
{
  "schema_version": 1,
  "sections": [
    { "key": "differential", "title": "Differential count", "parameters": [
      { "key": "neutrophils", "label": "Neutrophils", "type": "numeric", "unit": "%", "precision": 0,
        "required": true, "method": "Electrical impedance",
        "reference_ranges": [ { "min": 40, "max": 70, "sex": "any", "age_min": null, "age_max": null } ] }
    ] }
  ]
}
```

Types: `numeric`, `decimal`, `integer`, `text`, `textarea`, `qualitative`, `select`, `multiselect`, `boolean`, `date`,
`time`, `table`. Numeric parameters may declare a `formula` (e.g. `bil_total - bil_direct`) evaluated server-side by a
whitelist parser (no `eval`). Keys are stable identifiers; labels can change freely.

### Historical integrity

- Editing reportable content of a test creates a new version; existing report items keep their own snapshot, so units,
  labels and intervals on issued reports never change. New reports use the latest active version.
- Finalized reports cannot be edited or deleted. *Correct report* (with a mandatory reason) stores the released version
  in `report_revisions`, reopens the report as revision *n + 1*, and the PDF of the new release is marked as revised.
  Superseded versions remain viewable and downloadable.
- Flags (H/L) are computed only for numeric results with an applicable interval for the patient's sex/age; otherwise
  the value is shown without a flag. They are informational, not diagnoses.

### Security

Hashed passwords, CSRF, login throttling (5 attempts / 5 min per email + IP and 10 req/min per route), admin-only
middleware on every private route, allow-listed sort columns, escaped output, encrypted sessions, `no-store` caching for
authenticated pages, `X-Frame-Options`/`nosniff` headers, dompdf with remote fetching and PHP disabled, and no patient
data in logs or audit metadata. No data is sent to third-party services.

This software has not been validated, certified or accredited for clinical use; the laboratory remains responsible for
verifying test definitions and reference intervals (see the “Needs review” flags).

## Upgrading from the previous code base

This repository previously contained a multi-tenant hospital suite (PulseForge). Its modules were removed; its database
tables and migrations are left untouched so no data is lost. A backup of the removed code is stored at
`storage/app/private/legacy-backup/`.

## Credits & licence

Derived from [PulseForge](https://github.com/nasimubd/PulseForge) by MD NASIM, released under the MIT License (see `LICENSE`).
