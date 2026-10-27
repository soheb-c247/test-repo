# Reference workbook → test definition mapping

Source: `Exal sheet for test (1).xlsx` (35 sheets) and the matching PDF print-out.
Catalog file: `database/data/workbook_test_catalog.php` · importer: `App\Services\ReferenceDataImportService`
(`php artisan lab:import-reference-tests`, also run by `php artisan db:seed`).

This document is generated from the catalog, so it always matches what is seeded.

## Principles

- Labels, units and biological reference intervals are taken from the workbook. Obvious typing errors are corrected
  (e.g. *Eosinphils → Eosinophils*, *Lacks → lakhs*, *Amoxcillin → Amoxicillin*) and listed below where they matter.
- No interval is invented. Where the workbook gives none, none is configured and results are **not flagged**.
- Sheets that combine several tests on one print-out (ANC, BIO, W.CARD, ESR…) are split into individual tests;
  the same print-out is produced by adding several tests to one report.
- Patient names, doctors and sample IDs in the workbook/PDF are **not** seeded. Demo data (`DemoDataSeeder`) is synthetic.
- Every definition with an ambiguity is imported with **Needs review** set; the note is visible on the test page.

## Sheets → definitions

| Workbook sheet | Seeded test code(s) |
|---|---|
| `SUGAR` | GLU-FPP |
| `HB,BG` | GLU-RBS |
| `billi` | BIL |
| `Sheet4` | CBC, WIDAL |
| `cbc only` | CBC |
| `salim khan sir` | CBC, CRP, WIDAL, MP-AG |
| `hb & rbs` | GLU-RBS, HB |
| `cal` | CA, UA, RA |
| `anc` | GLU-RBS, UREA, CBC, HBSAG, HIV, VDRL, BGRP |
| `LFT` | LFT, HBSAG |
| `RFT` | RFT |
| `TROP-I` | TROP-I |
| `UPT` | UPT |
| `URINE` | URINE-RM |
| `ESR` | CBC, ESR, CRP, WIDAL |
| `W.CARD` | CBC, CRP, WIDAL, TYPHOID, MP-AG |
| `URINE(SI)` | URINE-RM |
| `BIO` | CA, UA, UREA, CREAT, ELECTRO, CRP, CRP-LTX, RA, RA-LTX, ASO |
| `ANC(ME)` | GLU-RBS, UREA, CBC, HBSAG, HIV, VDRL, BGRP |
| `Sheet1` | HEMOGRAM, CRP, WIDAL, MP-AG |
| `URINE(ME)` | URINE-RM |
| `SEMEN` | SEMEN |
| `STOOL` | STOOL |
| `HB,PLT,BT-CT` | GLU-RBS, UREA, CREAT, HB, PLT, BT-CT |
| `PT-INR` | PT-INR |
| `ELECTRO` | ELECTRO |
| `BT-CT` | GLU-RBS, CBC, BT-CT |
| `MT` | ESR, MANTOUX |
| `Rh Antibody` | RH-AB |
| `HBA1C` | HBA1C |
| `DENGUE` | DENGUE |
| `GTT` | GTT, BT-CT |
| `Culture` | CULTURE |
| `Culture Normal` | CULTURE |
| `PS` | PS |

## Definitions

| Code | Test | Category | Specimen | Params | Review |
|---|---|---|---|---:|---|
| `GLU-FPP` | Blood Sugar Fasting & PP | Biochemistry | Serum | 2 | — |
| `GLU-RBS` | Random Blood Sugar | Biochemistry | Serum | 1 | — |
| `GTT` | Glucose Tolerance Test (75 g) | Biochemistry | Serum & Urine | 6 | ⚠ yes |
| `HBA1C` | Glycosylated Haemoglobin (HbA1c) | Biochemistry | Whole blood (EDTA) | 2 | ⚠ yes |
| `BIL` | Serum Bilirubin | Biochemistry | Serum | 3 | ⚠ yes |
| `LFT` | Liver Function Test (LFT) | Biochemistry | Serum | 10 | ⚠ yes |
| `RFT` | Renal Function Test (RFT) | Biochemistry | Serum | 8 | ⚠ yes |
| `CA` | Serum Calcium | Biochemistry | Serum | 1 | — |
| `UA` | Serum Uric Acid | Biochemistry | Serum | 1 | ⚠ yes |
| `UREA` | Blood Urea | Biochemistry | Serum | 1 | ⚠ yes |
| `CREAT` | Serum Creatinine | Biochemistry | Serum | 1 | ⚠ yes |
| `ELECTRO` | Serum Electrolytes | Biochemistry | Serum | 4 | ⚠ yes |
| `HB` | Haemoglobin (Hb) | Haematology | Whole blood (EDTA) | 1 | ⚠ yes |
| `CBC` | Complete Blood Count (CBC) | Haematology | Whole blood (EDTA) | 14 | ⚠ yes |
| `HEMOGRAM` | Haemogram (with RDW / PDW / PCT) | Haematology | Whole blood (EDTA) | 17 | ⚠ yes |
| `PLT` | Platelet Count | Haematology | Whole blood (EDTA) | 1 | — |
| `ESR` | Erythrocyte Sedimentation Rate (ESR) | Haematology | Whole blood (EDTA) | 1 | ⚠ yes |
| `BT-CT` | Bleeding Time & Clotting Time | Haematology | Capillary blood | 2 | ⚠ yes |
| `PS` | Peripheral Smear Examination | Haematology | Whole blood (EDTA) | 5 | ⚠ yes |
| `PT-INR` | Prothrombin Time (PT / INR) | Coagulation | — | 4 | ⚠ yes |
| `CRP` | C-Reactive Protein (Quantitative) | Serology & Immunology | Serum | 1 | ⚠ yes |
| `CRP-LTX` | C-Reactive Protein (Latex) | Serology & Immunology | Serum | 1 | — |
| `RA` | Rheumatoid Factor (RA Quantitative) | Serology & Immunology | Serum | 1 | — |
| `RA-LTX` | Rheumatoid Factor (Latex) | Serology & Immunology | Serum | 1 | — |
| `ASO` | A.S.O. Titre (Quantitative) | Serology & Immunology | Serum | 1 | — |
| `WIDAL` | Widal Test | Serology & Immunology | Serum | 1 | ⚠ yes |
| `TYPHOID` | Typhoid Antigen & Antibody Test | Serology & Immunology | Serum | 2 | — |
| `MP-AG` | Malarial Antigen | Serology & Immunology | Whole blood (EDTA) | 1 | ⚠ yes |
| `DENGUE` | Dengue Antigen & Antibody Test | Serology & Immunology | Serum | 3 | — |
| `HBSAG` | HBsAg | Serology & Immunology | Serum | 1 | ⚠ yes |
| `HIV` | HIV I & II | Serology & Immunology | Serum | 1 | — |
| `VDRL` | V.D.R.L. | Serology & Immunology | Serum | 1 | — |
| `BGRP` | Blood Group & Rh Factor | Serology & Immunology | Whole blood (EDTA) | 2 | — |
| `RH-AB` | Rh Antibody Titre | Serology & Immunology | Serum | 4 | ⚠ yes |
| `MANTOUX` | Mantoux Test | Serology & Immunology | Intradermal (PPD) | 2 | ⚠ yes |
| `TROP-I` | Troponin I (Trop-I) | Cardiac | Serum | 1 | — |
| `URINE-RM` | Urine Routine & Microscopic Examination | Clinical Pathology | Urine | 23 | ⚠ yes |
| `UPT` | Urine Pregnancy Test | Clinical Pathology | Urine | 1 | — |
| `SEMEN` | Semen Examination | Clinical Pathology | Semen | 16 | ⚠ yes |
| `STOOL` | Stool Examination | Clinical Pathology | Stool | 21 | ⚠ yes |
| `CULTURE` | Aerobic Culture & Sensitivity | Microbiology | Urine | 6 | ⚠ yes |

## Items that need manual review

- **GTT — Glucose Tolerance Test (75 g)**: The GTT sheet also lists B.T./C.T.; those are configured as the separate BT-CT test.
- **HBA1C — Glycosylated Haemoglobin (HbA1c)**: HbA1c is reported against control categories rather than a single interval, so no automatic flag is configured. ABG uses 28.7 × HbA1c − 46.7, which reproduces the workbook example (9.1% → 214 mg/dl); confirm this is the formula your laboratory uses.
- **BIL — Serum Bilirubin**: Intervals are adult intervals as printed in the workbook, which applied them to a 3-day-old neonate; add neonatal age-specific intervals before relying on flags for newborns. The LFT sheet uses 0.2 - 1.20 mg/dL for total bilirubin.
- **LFT — Liver Function Test (LFT)**: HBsAg, printed on the workbook LFT sheet, is configured as the separate HBSAG test. Globulin and A/G ratio are entered manually as in the workbook (its example values are not arithmetically derived).
- **RFT — Renal Function Test (RFT)**: The workbook prints the serum calcium unit as "mg/L" for an interval of 8.5 - 11.0, which matches mg/dL; configured as mg/dL — please confirm.
- **UA — Serum Uric Acid**: Sex-specific intervals come from the workbook interpretation text; the result column on the "cal" sheet printed 3.6 - 7.7 for a female patient, and the BIO sheet uses 2.5 - 7.5. Confirm the intervals your laboratory uses.
- **UREA — Blood Urea**: The RFT sheet uses 15.0 - 40.0 for blood urea.
- **CREAT — Serum Creatinine**: The RFT sheet uses 0.70 - 1.40 mg/dL for serum creatinine.
- **ELECTRO — Serum Electrolytes**: The BIO sheet lists different intervals (sodium 130 - 154, potassium 3.6 - 5.6 mmol/L).
- **HB — Haemoglobin (Hb)**: The workbook applies 12.0 - 15.6 gm/dL to every patient (including adult males); the Sheet1 haemogram uses 14.0 - 18.0. Add sex/age-specific intervals as appropriate.
- **CBC — Complete Blood Count (CBC)**: Intervals follow the "Sheet4" CBC. Other workbook sheets differ: total WBC upper limit 11,000 and neutrophils 40 - 80% ("salim khan sir", "cbc only"); the "cbc only" sheet lists eosinophils 02 - 10 and monocytes 01 - 06 (apparently swapped). The haemoglobin interval is not sex/age-specific. Unit "Lacks /cumm" corrected to "lakhs/cumm".
- **HEMOGRAM — Haemogram (with RDW / PDW / PCT)**: Intervals come from a single adult-male example (Sheet1) and look like adult-male intervals; add female/paediatric intervals before use. The workbook prints the platelet unit as "/cmm" for a value of 2.18; configured as lakhs/cmm — please confirm.
- **ESR — Erythrocyte Sedimentation Rate (ESR)**: The MT sheet prints a second interval (00 - 10 mm/hr) without saying whom it applies to; sex-specific ESR intervals may be required.
- **BT-CT — Bleeding Time & Clotting Time**: The workbook records times as minutes:seconds text (e.g. "1:15", "3.45"), so they are entered as text and not flagged automatically. Sample type was not stated in the workbook.
- **PS — Peripheral Smear Examination**: The workbook report is free text; it has been split into RBC / WBC / platelet / parasite / impression fields.
- **PT-INR — Prothrombin Time (PT / INR)**: The workbook gives no reference intervals and no sample type for PT/INR, so none are configured and results are not flagged. INR is entered manually (the workbook example 1.07 differs from (15/14)^1.1 = 1.08).
- **CRP — C-Reactive Protein (Quantitative)**: The "salim khan sir" sheet prints the same interval as 0.0 - 0.6 mg/dl.
- **WIDAL — Widal Test**: Dilution choices beyond those seen in the workbook (1:80, 1:160) are standard doubling dilutions; adjust if your kit differs.
- **MP-AG — Malarial Antigen**: Only "Negative" appears in the workbook; add species-specific result options if your card reports them.
- **HBSAG — HBsAg**: The workbook prints "By card" on the LFT sheet and "By strip method" on the ANC sheets.
- **RH-AB — Rh Antibody Titre**: No reference text is given in the workbook (all example results are "Negative").
- **MANTOUX — Mantoux Test**: The workbook gives no induration cut-off; the Positive/Negative result is entered by the reader.
- **URINE-RM — Urine Routine & Microscopic Examination**: No reference values are given in the workbook. Fields are left blank (not pre-filled with "Nil") so an unexamined field is never reported as normal.
- **SEMEN — Semen Examination**: Only the sperm count has a reference interval in the workbook; motility fractions (stored as 0.75 etc. in Excel) are configured as percentages without intervals.
- **STOOL — Stool Examination**: No reference values are given in the workbook.
- **CULTURE — Aerobic Culture & Sensitivity**: Antimicrobial names were spelling-corrected (e.g. "Amoxcillin", "Nitrofurantion", "Polymixin B"). "Intermediate" was added as a standard susceptibility category. The pathologist name printed on the workbook sheet is intentionally not seeded — configure signatories in .env.

Mark a definition as reviewed from its page in **Tests** once the laboratory has confirmed its intervals.
