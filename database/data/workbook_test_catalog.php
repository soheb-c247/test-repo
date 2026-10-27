<?php

/*
|--------------------------------------------------------------------------
| Reference test catalog derived from "Exal sheet for test (1).xlsx"
|--------------------------------------------------------------------------
|
| One entry per distinct test found in the workbook. Labels, units and
| biological reference intervals follow the workbook; obvious typing errors
| are corrected (e.g. "Eosinphils" -> "Eosinophils", "Lacks" -> "lakhs") and
| every such correction or ambiguity is listed in the entry's review notes
| and in docs/WORKBOOK_MAPPING.md. Where the workbook gives no interval,
| none is configured — values are never invented.
|
| Imported by App\Services\ReferenceDataImportService (php artisan
| lab:import-reference-tests or the TestCatalogSeeder).
|
*/

$numeric = fn (string $key, string $label, ?string $unit, $min, $max, array $extra = []) => array_merge([
    'key' => $key,
    'label' => $label,
    'type' => 'numeric',
    'required' => true,
    'unit' => $unit,
    'precision' => null,
    'reference_ranges' => ($min === null && $max === null) ? [] : [['min' => $min, 'max' => $max, 'sex' => 'any']],
], $extra);

$qualitative = fn (string $key, string $label, array $options, array $extra = []) => array_merge([
    'key' => $key,
    'label' => $label,
    'type' => 'qualitative',
    'required' => true,
    'options' => $options,
], $extra);

$text = fn (string $key, string $label, array $extra = []) => array_merge([
    'key' => $key,
    'label' => $label,
    'type' => 'text',
    'required' => false,
], $extra);

$longText = fn (string $key, string $label, array $extra = []) => array_merge([
    'key' => $key,
    'label' => $label,
    'type' => 'textarea',
    'required' => false,
], $extra);

$section = fn (string $key, string $title, array $parameters) => [
    'key' => $key,
    'title' => $title,
    'parameters' => $parameters,
];

$schema = fn (array $sections) => ['schema_version' => 1, 'sections' => $sections];

$negPos = ['Negative', 'Positive'];
$nonReactive = ['Non-reactive', 'Reactive'];

$cbcNote = 'NOTE: Complete Blood Count provides a snapshot of your blood\'s cellular components, including red blood cells, white blood cells, and platelets. It helps doctors assess overall health, identify potential issues like anemia or infections, and monitor treatment effectiveness.';
$rbsNote = 'Interpretation: Random blood sugar (RBS) test is a simple blood test that measures the level of glucose in the blood at any time of the day, regardless of when the individual last ate.';
$crpNote = 'Interpretation: C-Reactive protein, which is synthesized in the liver, is one of the most sensitive acute phase reactants after tissue damage or inflammation.';
$cardMethod = 'Card test';

return [

    // ------------------------------------------------------------------ Biochemistry
    [
        'code' => 'GLU-FPP',
        'name' => 'Blood Sugar Fasting & PP',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['SUGAR'],
        'schema' => $schema([
            $section('blood_sugar', 'Blood Sugar', [
                $numeric('fbs', 'F.Blood Sugar', 'mgs%', 70.0, 110.0, ['precision' => 1]),
                $numeric('ppbs', 'P.P.Blood Sugar', 'mgs%', 70.0, 140.0, ['precision' => 1]),
            ]),
        ]),
        'reporting_notes' => "Clinical Notes: Elevated glucose levels (hyperglycemia) are most often encountered clinically in the setting of diabetes mellitus, but they may also occur with pancreatic neoplasms, hyperthyroidism, and adrenocortical dysfunction. Decreased glucose levels (hypoglycemia) may result from endogenous or exogenous insulin excess, prolonged starvation, or liver disease.\n\nFasting Glucose | 2 Hours PP Glucose | Diagnosis\n<100 | <140 | Normal\n100 to 125 | 140 to 199 | Pre Diabetes\n>126 | >200 | Diabetes",
        'review' => null,
    ],
    [
        'code' => 'GLU-RBS',
        'name' => 'Random Blood Sugar',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['HB,BG', 'hb & rbs', 'anc', 'ANC(ME)', 'BT-CT', 'HB,PLT,BT-CT'],
        'schema' => $schema([
            $section('blood_sugar', 'Blood Sugar', [
                $numeric('rbs', 'R.Blood Sugar', 'mgs%', 70.0, 140.0, ['precision' => 1]),
            ]),
        ]),
        'reporting_notes' => $rbsNote,
        'review' => null,
    ],
    [
        'code' => 'GTT',
        'name' => 'Glucose Tolerance Test (75 g)',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum & Urine',
        'sheets' => ['GTT'],
        'schema' => $schema([
            $section('fasting', 'Fasting', [
                $numeric('fbs', 'F.Blood Sugar', 'mgs%', 70.0, 110.0, ['precision' => 1]),
                $text('f_urine_sugar', 'F.Urine Sugar', ['help' => 'e.g. Nil']),
            ]),
            $section('one_hour', '1 Hour after 75 g Glucose', [
                $numeric('bs_1h', '1hrs.Blood Sugar', 'mgs%', 70.0, 140.0, ['precision' => 1]),
                $text('urine_sugar_1h', '1hrs.Urine Sugar', ['help' => 'e.g. Nil']),
            ]),
            $section('two_hours', '2 Hours after 75 g Glucose', [
                $numeric('bs_2h', '2hrs.Blood Sugar', 'mgs%', 70.0, 140.0, ['precision' => 1]),
                $text('urine_sugar_2h', '2hrs.Urine Sugar', ['help' => 'e.g. Nil']),
            ]),
        ]),
        'preparation_instructions' => '75 gram glucose given after the fasting sample.',
        'reporting_notes' => null,
        'review' => 'The GTT sheet also lists B.T./C.T.; those are configured as the separate BT-CT test.',
    ],
    [
        'code' => 'HBA1C',
        'name' => 'Glycosylated Haemoglobin (HbA1c)',
        'category' => 'Biochemistry',
        'sample_type' => 'Whole blood (EDTA)',
        'method' => 'Fully automated H.P.L.C. (Tosoh G8)',
        'sheets' => ['HBA1C'],
        'schema' => $schema([
            $section('diabetes_screen', 'Diabetes Screen (Blood)', [
                $numeric('hba1c', 'HbA1c', '%', null, null, ['precision' => 1, 'method' => 'H.P.L.C.', 'reference_text' => 'See reference range below']),
                $numeric('abg', 'Average Blood Glucose (ABG)', 'mg/dl', null, null, [
                    'precision' => 0,
                    'method' => 'Calculated from HbA1c',
                    'formula' => '28.7 * hba1c - 46.7',
                    'reference_text' => 'See reference range below',
                ]),
            ]),
        ]),
        'reporting_notes' => "HbA1c Reference Range | Interpretation\nBelow 6.0% | Normal Value\n6.0% - 7.0% | Good Control\n7.0% - 8.0% | Fair Control\n8.0% - 10.0% | Unsatisfactory Control\nAbove 10.0% | Poor Control\n\nAverage Blood Glucose | Interpretation\n90 - 120 mg/dl | Excellent Control\n121 - 150 mg/dl | Good Control\n151 - 180 mg/dl | Average Control\n181 - 210 mg/dl | Action Suggested\n> 211 mg/dl | Panic Value\n\nNote: Average Blood Glucose value is calculated from the HbA1c value and indicates the average blood sugar level over the past three months. Please correlate with clinical conditions.",
        'review' => 'HbA1c is reported against control categories rather than a single interval, so no automatic flag is configured. ABG uses 28.7 × HbA1c − 46.7, which reproduces the workbook example (9.1% → 214 mg/dl); confirm this is the formula your laboratory uses.',
    ],
    [
        'code' => 'BIL',
        'name' => 'Serum Bilirubin',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['billi'],
        'schema' => $schema([
            $section('bilirubin', 'Serum Bilirubin', [
                $numeric('bil_total', 'Serum Bilirubin (Total)', 'mg/dL', 0.2, 1.00, ['precision' => 2]),
                $numeric('bil_direct', 'Serum Bilirubin (Direct)', 'mg/dL', 0.1, 0.50, ['precision' => 2]),
                $numeric('bil_indirect', 'Serum Bilirubin (Indirect)', 'mg/dL', 0.1, 0.70, [
                    'precision' => 2, 'required' => false, 'formula' => 'bil_total - bil_direct', 'method' => 'Calculated',
                ]),
            ]),
        ]),
        'reporting_notes' => "Total Bilirubin | Interpretation\n< 1.0 mg/dl | Normal\n1.0 - 2.9 mg/dl | Mild elevation, possible liver disease\n3.0 - 5.9 mg/dl | Moderate elevation, likely liver disease\n> 5.9 mg/dl | Severe elevation, possible liver failure",
        'review' => 'Intervals are adult intervals as printed in the workbook, which applied them to a 3-day-old neonate; add neonatal age-specific intervals before relying on flags for newborns. The LFT sheet uses 0.2 - 1.20 mg/dL for total bilirubin.',
    ],
    [
        'code' => 'LFT',
        'name' => 'Liver Function Test (LFT)',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'method' => 'Semi-automated biochemistry analyzer (Star-20)',
        'sheets' => ['LFT'],
        'schema' => $schema([
            $section('lft', 'Liver Function Test', [
                $numeric('bil_total', 'Serum Bilirubin (Total)', 'mg/dL', 0.2, 1.20, ['precision' => 2, 'method' => 'Diazo method']),
                $numeric('bil_direct', 'Serum Bilirubin (Direct)', 'mg/dL', 0.1, 0.50, ['precision' => 2, 'method' => 'Diazo method']),
                $numeric('bil_indirect', 'Serum Bilirubin (Indirect)', 'mg/dL', 0.1, 0.70, [
                    'precision' => 2, 'required' => false, 'method' => 'Calculated', 'formula' => 'bil_total - bil_direct',
                ]),
                $numeric('sgot', 'SGOT (AST)', 'U/L', 0, 39, ['precision' => 1, 'method' => 'IFCC method']),
                $numeric('sgpt', 'SGPT (ALT)', 'U/L', 0, 36, ['precision' => 1, 'method' => 'IFCC method']),
                $numeric('alp', 'Alkaline Phosphatase (ALP)', 'U/L', 25, 147, ['precision' => 1, 'method' => 'Tris carbonate buffer, kinetic']),
                $numeric('total_protein', 'Serum Protein (Total)', 'gm/dL', 6, 8.0, ['precision' => 2, 'method' => 'Biuret']),
                $numeric('albumin', 'Serum Albumin', 'gm/dL', 3.7, 5.3, ['precision' => 2, 'method' => 'Bromocresol green']),
                $numeric('globulin', 'Serum Globulin', 'gm/dL', 2.1, 3.5, ['precision' => 2]),
                $numeric('ag_ratio', 'Albumin/Globulin (A/G) Ratio', 'Ratio', 1.20, 1.80, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => "Introduction: Liver function tests (LFTs) include tests for bilirubin, a breakdown product of hemoglobin, and enzymes such as gamma-glutamyl transferase (GGT), alanine aminotransferase (ALT or SGPT), aspartate aminotransferase (AST or SGOT) and alkaline phosphatase (ALP).\nUse: Liver function tests are used to aid in the differential diagnosis of liver disease and injury, and to help monitor response to treatment.",
        'review' => 'HBsAg, printed on the workbook LFT sheet, is configured as the separate HBSAG test. Globulin and A/G ratio are entered manually as in the workbook (its example values are not arithmetically derived).',
    ],
    [
        'code' => 'RFT',
        'name' => 'Renal Function Test (RFT)',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'method' => 'Semi-automated biochemistry analyzer (Star-20)',
        'sheets' => ['RFT'],
        'schema' => $schema([
            $section('rft', 'Renal Function Test', [
                $numeric('creatinine', 'Serum Creatinine', 'mg/dL', 0.70, 1.40, ['precision' => 2, 'method' => "Jaffe's method"]),
                $numeric('urea', 'Blood Urea', 'mg/dL', 15.0, 40.0, ['precision' => 2, 'method' => 'GLDH urease method']),
                $numeric('uric_acid', 'Uric Acid', 'mg/dL', 2.5, 7.5, ['precision' => 2, 'method' => 'Modified Trinder method']),
                $numeric('calcium', 'Serum Calcium', 'mg/dL', 8.5, 11.0, ['precision' => 2, 'method' => 'Arsenazo III']),
                $numeric('total_protein', 'Serum Protein (Total)', 'gm/dL', 6, 8.0, ['precision' => 2, 'method' => 'Biuret']),
                $numeric('albumin', 'Serum Albumin', 'gm/dL', 3.7, 5.3, ['precision' => 2, 'method' => 'Bromocresol green']),
                $numeric('globulin', 'Serum Globulin', 'gm/dL', 2.1, 3.5, ['precision' => 2]),
                $numeric('ag_ratio', 'Albumin/Globulin (A/G) Ratio', 'Ratio', 1.20, 1.80, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => 'Use: Renal function tests are used to aid in the differential diagnosis of kidney disease and injury, and to help monitor response to treatment.',
        'review' => 'The workbook prints the serum calcium unit as "mg/L" for an interval of 8.5 - 11.0, which matches mg/dL; configured as mg/dL — please confirm.',
    ],
    [
        'code' => 'CA',
        'name' => 'Serum Calcium',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['cal', 'BIO'],
        'schema' => $schema([
            $section('calcium', 'Serum Calcium', [
                $numeric('calcium', 'Calcium', 'mgs%', 8.5, 11.0, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => 'NOTE: A calcium test measures calcium levels vital for bone health, muscles, nerves, and heart function, detecting imbalances (hypercalcemia/hypocalcemia) that can signal bone, kidney, or parathyroid issues, or cancer.',
        'review' => null,
    ],
    [
        'code' => 'UA',
        'name' => 'Serum Uric Acid',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['cal', 'BIO'],
        'schema' => $schema([
            $section('uric_acid', 'Uric Acid', [
                $numeric('uric_acid', 'Uric Acid', 'mg/dl', null, null, [
                    'precision' => 2,
                    'reference_ranges' => [
                        ['min' => 3.6, 'max' => 7.7, 'sex' => 'male'],
                        ['min' => 2.5, 'max' => 6.8, 'sex' => 'female'],
                    ],
                ]),
            ]),
        ]),
        'reporting_notes' => "Interpretation:\n• Normal: 3.6 to 7.7 mg/dL (200 to 430 µmol/L) for men and 2.5 to 6.8 mg/dL (140 to 360 µmol/L) for women. Normal uric acid levels may vary depending on the laboratory that performs the test.\n• High: A uric acid level above the normal range indicates hyperuricemia, which can be caused by genetics, diet, and medical conditions such as gout or kidney disease.\n• Low: A uric acid level below the normal range is uncommon and can be caused by liver disease, certain medications, or a diet low in purines.",
        'review' => 'Sex-specific intervals come from the workbook interpretation text; the result column on the "cal" sheet printed 3.6 - 7.7 for a female patient, and the BIO sheet uses 2.5 - 7.5. Confirm the intervals your laboratory uses.',
    ],
    [
        'code' => 'UREA',
        'name' => 'Blood Urea',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['anc', 'ANC(ME)', 'BIO', 'HB,PLT,BT-CT'],
        'schema' => $schema([
            $section('urea', 'Blood Urea', [
                $numeric('urea', 'Blood Urea', 'mgs%', 10.0, 40.0, ['precision' => 1]),
            ]),
        ]),
        'reporting_notes' => 'NOTE: The blood urea test measures urea levels in the blood to assess kidney function, liver health, and hydration status. Urea is produced in the liver from protein breakdown and filtered by the kidneys; high levels suggest dysfunction or dehydration.',
        'review' => 'The RFT sheet uses 15.0 - 40.0 for blood urea.',
    ],
    [
        'code' => 'CREAT',
        'name' => 'Serum Creatinine',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['BIO', 'HB,PLT,BT-CT'],
        'schema' => $schema([
            $section('creatinine', 'Serum Creatinine', [
                $numeric('creatinine', 'Creatinine', 'mgs%', 0.80, 1.50, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The RFT sheet uses 0.70 - 1.40 mg/dL for serum creatinine.',
    ],
    [
        'code' => 'ELECTRO',
        'name' => 'Serum Electrolytes',
        'category' => 'Biochemistry',
        'sample_type' => 'Serum',
        'sheets' => ['ELECTRO', 'BIO'],
        'schema' => $schema([
            $section('electrolytes', 'Serum Electrolytes', [
                $numeric('sodium', 'Serum Sodium (Na+)', 'mmol/L', 135, 150, ['precision' => 1]),
                $numeric('potassium', 'Serum Potassium (K+)', 'mmol/L', 3.5, 5.0, ['precision' => 2]),
                $numeric('chloride', 'Serum Chloride (Cl-)', 'mEq/L', 96, 108, ['precision' => 1]),
                $numeric('ionised_calcium', 'Ionised Calcium (iCa)', 'mmol/L', 1.10, 1.35, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The BIO sheet lists different intervals (sodium 130 - 154, potassium 3.6 - 5.6 mmol/L).',
    ],

    // ------------------------------------------------------------------ Haematology
    [
        'code' => 'HB',
        'name' => 'Haemoglobin (Hb)',
        'category' => 'Haematology',
        'sample_type' => 'Whole blood (EDTA)',
        'method' => 'Electrical impedance',
        'sheets' => ['hb & rbs', 'HB,PLT,BT-CT'],
        'schema' => $schema([
            $section('hemoglobin', 'Hemoglobin', [
                $numeric('hb', 'Haemoglobin (Hb)', 'gm/dL', 12.0, 15.6, ['precision' => 1]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The workbook applies 12.0 - 15.6 gm/dL to every patient (including adult males); the Sheet1 haemogram uses 14.0 - 18.0. Add sex/age-specific intervals as appropriate.',
    ],
    [
        'code' => 'CBC',
        'name' => 'Complete Blood Count (CBC)',
        'category' => 'Haematology',
        'sample_type' => 'Whole blood (EDTA)',
        'method' => 'Fully automated 3-part hematology analyzer (Erba H360), electrical impedance',
        'sheets' => ['Sheet4', 'salim khan sir', 'cbc only ', 'anc', 'ESR', 'W.CARD', 'ANC(ME)', 'BT-CT'],
        'schema' => $schema([
            $section('hemoglobin', 'Hemoglobin', [
                $numeric('hb', 'Haemoglobin (Hb)', 'gm/dL', 12.0, 15.6, ['precision' => 1]),
            ]),
            $section('rbc_count', "RBC's Count", [
                $numeric('rbc', 'Erythrocyte (RBC) Count', 'mill/cumm', 3.8, 4.8, ['precision' => 2]),
            ]),
            $section('blood_indices', 'Blood Indices', [
                $numeric('pcv', 'Packed Cell Volume (P.C.V.)', '%', 35.0, 47.0, ['precision' => 1]),
                $numeric('mcv', 'Mean Corpuscular Volume (MCV)', 'fl', 76.0, 94.0, ['precision' => 1]),
                $numeric('mch', 'Mean Corpuscular Hemoglobin (MCH)', 'pg', 27.0, 32.0, ['precision' => 1]),
                $numeric('mchc', 'Mean Corpuscular Hb Conc. (MCHC)', 'g/dl', 30.0, 37.0, ['precision' => 1]),
            ]),
            $section('wbc_count', "WBC's Count", [
                $numeric('tlc', 'Total WBC Count', '/cumm', 4000, 10000, ['precision' => 0]),
            ]),
            $section('differential', "Differential WBC's Count", [
                $numeric('neutrophils', 'Neutrophils', '%', 40, 70, ['precision' => 0]),
                $numeric('lymphocytes', 'Lymphocytes', '%', 20, 40, ['precision' => 0]),
                $numeric('monocytes', 'Monocytes', '%', 2, 10, ['precision' => 0]),
                $numeric('eosinophils', 'Eosinophils', '%', 1, 6, ['precision' => 0]),
                $numeric('basophils', 'Basophils', '%', 0, 2, ['precision' => 0]),
            ]),
            $section('platelets', "Platelet's Count", [
                $numeric('platelets', 'Platelets Count', 'lakhs/cumm', 1.5, 5.0, ['precision' => 2]),
                $numeric('mpv', 'MPV', 'fL', 7.5, 11.5, ['precision' => 1, 'required' => false]),
            ]),
        ]),
        'reporting_notes' => $cbcNote,
        'review' => 'Intervals follow the "Sheet4" CBC. Other workbook sheets differ: total WBC upper limit 11,000 and neutrophils 40 - 80% ("salim khan sir", "cbc only"); the "cbc only" sheet lists eosinophils 02 - 10 and monocytes 01 - 06 (apparently swapped). The haemoglobin interval is not sex/age-specific. Unit "Lacks /cumm" corrected to "lakhs/cumm".',
    ],
    [
        'code' => 'HEMOGRAM',
        'name' => 'Haemogram (with RDW / PDW / PCT)',
        'category' => 'Haematology',
        'sample_type' => 'Whole blood (EDTA)',
        'method' => 'Fully automated hematology analyzer (Erba H360)',
        'sheets' => ['Sheet1'],
        'schema' => $schema([
            $section('haemoglobin', 'Haemoglobin', [
                $numeric('hb', 'Haemoglobin', 'gm%', 14.0, 18.0, ['precision' => 1]),
            ]),
            $section('wbc_count', 'WBC Count', [
                $numeric('tlc', 'Total WBC Count', '/cmm', 4000, 11000, ['precision' => 0]),
            ]),
            $section('differential', 'Differential Count', [
                $numeric('neutrophils', 'Neutrophil', '%', 50, 70, ['precision' => 0]),
                $numeric('lymphocytes', 'Lymphocytes', '%', 20, 45, ['precision' => 0]),
                $numeric('eosinophils', 'Eosinophil', '%', 2, 10, ['precision' => 0]),
                $numeric('monocytes', 'Monocytes', '%', 1, 6, ['precision' => 0]),
                $numeric('basophils', 'Basophil', '%', 0, 1, ['precision' => 0]),
            ]),
            $section('rbc_indices', 'RBC Indices', [
                $numeric('hct', 'Haematocrit (HCT)', '%', 40, 54, ['precision' => 1]),
                $numeric('rbc', 'R.B.C. Count', 'mil./cmm', 4.5, 6.0, ['precision' => 2]),
                $numeric('mcv', 'MCV', 'fL', 76, 93, ['precision' => 1]),
                $numeric('mch', 'MCH', 'pg', 27, 32, ['precision' => 1]),
                $numeric('mchc', 'MCHC', 'gm/dl', 30, 34, ['precision' => 1]),
                $numeric('rdw_cv', 'RDW-CV', '%', 11.0, 16.0, ['precision' => 1]),
            ]),
            $section('platelet_indices', 'Platelets Indices', [
                $numeric('platelets', 'Platelet Count', 'lakhs/cmm', 1.5, 5.0, ['precision' => 2]),
                $numeric('mpv', 'MPV', 'fL', 6.5, 12.4, ['precision' => 1]),
                $numeric('pdw', 'PDW', 'fL', 9.0, 17.0, ['precision' => 1]),
                $numeric('pct', 'PCT', '%', 0.100, 0.282, ['precision' => 3]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'Intervals come from a single adult-male example (Sheet1) and look like adult-male intervals; add female/paediatric intervals before use. The workbook prints the platelet unit as "/cmm" for a value of 2.18; configured as lakhs/cmm — please confirm.',
    ],
    [
        'code' => 'PLT',
        'name' => 'Platelet Count',
        'category' => 'Haematology',
        'sample_type' => 'Whole blood (EDTA)',
        'sheets' => ['HB,PLT,BT-CT'],
        'schema' => $schema([
            $section('platelets', 'Platelets', [
                $numeric('platelets', 'Platelets Count', 'lakhs/cumm', 1.5, 5.0, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'ESR',
        'name' => 'Erythrocyte Sedimentation Rate (ESR)',
        'category' => 'Haematology',
        'sample_type' => 'Whole blood (EDTA)',
        'sheets' => ['ESR', 'MT'],
        'schema' => $schema([
            $section('esr', 'E.S.R.', [
                $numeric('esr', 'E.S.R.', 'mm/hr', 0, 20, ['precision' => 0]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The MT sheet prints a second interval (00 - 10 mm/hr) without saying whom it applies to; sex-specific ESR intervals may be required.',
    ],
    [
        'code' => 'BT-CT',
        'name' => 'Bleeding Time & Clotting Time',
        'category' => 'Haematology',
        'sample_type' => 'Capillary blood',
        'sheets' => ['BT-CT', 'HB,PLT,BT-CT', 'GTT'],
        'schema' => $schema([
            $section('bt_ct', 'Bleeding & Clotting Time', [
                $text('bt', 'Bleeding Time (B.T.)', ['required' => true, 'unit' => 'minutes', 'reference_text' => '1 - 5 minutes', 'help' => 'Minutes:seconds, e.g. 3:45']),
                $text('ct', 'Clotting Time (C.T.)', ['required' => true, 'unit' => 'minutes', 'reference_text' => '0 - 9 minutes', 'help' => 'Minutes:seconds, e.g. 5:30']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The workbook records times as minutes:seconds text (e.g. "1:15", "3.45"), so they are entered as text and not flagged automatically. Sample type was not stated in the workbook.',
    ],
    [
        'code' => 'PS',
        'name' => 'Peripheral Smear Examination',
        'category' => 'Haematology',
        'sample_type' => 'Whole blood (EDTA)',
        'sheets' => ['PS'],
        'schema' => $schema([
            $section('peripheral_smear', 'Peripheral Smear Report', [
                $longText('rbc_morphology', 'RBC Morphology', ['required' => true]),
                $longText('wbc', 'WBC'),
                $longText('platelets', 'Platelets'),
                $longText('parasites', 'Haemoparasites'),
                $longText('impression', 'Impression', ['required' => true]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The workbook report is free text; it has been split into RBC / WBC / platelet / parasite / impression fields.',
    ],
    [
        'code' => 'PT-INR',
        'name' => 'Prothrombin Time (PT / INR)',
        'category' => 'Coagulation',
        'sample_type' => null,
        'sheets' => ['PT-INR'],
        'schema' => $schema([
            $section('prothrombin_time', 'Prothrombin Time', [
                $numeric('pt_control', 'Control Prothrombin Time', 'Seconds', null, null, ['precision' => 1]),
                $numeric('pt_patient', "Patient's Prothrombin Time", 'Seconds', null, null, ['precision' => 1]),
                $numeric('isi', 'ISI', null, null, null, ['precision' => 2]),
                $numeric('inr', 'INR', null, null, null, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The workbook gives no reference intervals and no sample type for PT/INR, so none are configured and results are not flagged. INR is entered manually (the workbook example 1.07 differs from (15/14)^1.1 = 1.08).',
    ],

    // ------------------------------------------------------------------ Serology & Immunology
    [
        'code' => 'CRP',
        'name' => 'C-Reactive Protein (Quantitative)',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'sheets' => ['ESR', 'W.CARD', 'salim khan sir', 'Sheet1', 'BIO'],
        'schema' => $schema([
            $section('crp', 'C-Reactive Protein', [
                $numeric('crp', 'C.R.P. (Quantitative)', 'mg/L', 0.0, 6.0, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => $crpNote,
        'review' => 'The "salim khan sir" sheet prints the same interval as 0.0 - 0.6 mg/dl.',
    ],
    [
        'code' => 'CRP-LTX',
        'name' => 'C-Reactive Protein (Latex)',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => 'Latex agglutination test',
        'sheets' => ['BIO'],
        'schema' => $schema([
            $section('crp', 'C-Reactive Protein', [
                $qualitative('crp', 'C.R.P.', $negPos, ['reference_text' => 'Negative']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'RA',
        'name' => 'Rheumatoid Factor (RA Quantitative)',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'sheets' => ['cal', 'BIO'],
        'schema' => $schema([
            $section('ra', 'Rheumatoid Factor', [
                $numeric('ra', 'Rheumatoid Factor (RA)', 'IU/ml', 0, 20, ['precision' => 2]),
            ]),
        ]),
        'reporting_notes' => "Result Interpretation | International units per milliliter (IU/mL)\nNegative | Less than 20\nLow-positive | 20 - 30\nModerate-positive | 30 - 120\nHigh-positive | Greater than 120",
        'review' => null,
    ],
    [
        'code' => 'RA-LTX',
        'name' => 'Rheumatoid Factor (Latex)',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => 'Latex agglutination test',
        'sheets' => ['BIO'],
        'schema' => $schema([
            $section('ra', 'Rheumatoid Factor', [
                $qualitative('ra', 'R.A. Factor', $negPos, ['reference_text' => 'Negative']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'ASO',
        'name' => 'A.S.O. Titre (Quantitative)',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'sheets' => ['BIO'],
        'schema' => $schema([
            $section('aso', 'Anti-Streptolysin O', [
                $numeric('aso', 'A.S.O. Titre', 'IU/ml', 0.0, 200.0, ['precision' => 1]),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'WIDAL',
        'name' => 'Widal Test',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'sheets' => ['Sheet4', 'salim khan sir', 'ESR', 'W.CARD', 'Sheet1'],
        'schema' => $schema([
            $section('widal', 'Widal Test', [
                [
                    'key' => 'widal',
                    'label' => 'Agglutination Seen In Dilution',
                    'type' => 'table',
                    'required' => true,
                    'row_header' => 'Antigen Used',
                    'reference_text' => 'Negative',
                    'columns' => [
                        ['key' => 'dilution', 'label' => 'Dilution', 'type' => 'select', 'options' => ['Negative', '1:20', '1:40', '1:80', '1:160', '1:320', '1:640']],
                        ['key' => 'result', 'label' => 'Result', 'type' => 'select', 'options' => $negPos],
                    ],
                    'rows' => [
                        ['key' => 'o', 'label' => 'S.Typhi "O"'],
                        ['key' => 'h', 'label' => 'S.Typhi "H"'],
                        ['key' => 'ah', 'label' => 'S.Typhi "AH"'],
                        ['key' => 'bh', 'label' => 'S.Typhi "BH"'],
                    ],
                    'allow_add_rows' => false,
                ],
            ]),
        ]),
        'reporting_notes' => "Uses of Widal test:\n• To diagnose infection due to Salmonella species (enteric fever).\n• To monitor the progression of disease.\n• To assess the response to therapy in patients being treated for enteric fever.",
        'review' => 'Dilution choices beyond those seen in the workbook (1:80, 1:160) are standard doubling dilutions; adjust if your kit differs.',
    ],
    [
        'code' => 'TYPHOID',
        'name' => 'Typhoid Antigen & Antibody Test',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => $cardMethod,
        'sheets' => ['W.CARD'],
        'schema' => $schema([
            $section('typhoid', 'Typhoid Antigen & Antibody', [
                $qualitative('igg', 'Typhoid - IgG', $negPos, ['reference_text' => 'Negative']),
                $qualitative('igm', 'Typhoid - IgM', $negPos, ['reference_text' => 'Negative']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'MP-AG',
        'name' => 'Malarial Antigen',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Whole blood (EDTA)',
        'method' => $cardMethod,
        'sheets' => ['salim khan sir', 'W.CARD', 'Sheet1'],
        'schema' => $schema([
            $section('malaria', 'Malarial Antigen', [
                $qualitative('malarial_antigen', 'Malarial Antigen', $negPos, ['reference_text' => 'Negative']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'Only "Negative" appears in the workbook; add species-specific result options if your card reports them.',
    ],
    [
        'code' => 'DENGUE',
        'name' => 'Dengue Antigen & Antibody Test',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => $cardMethod,
        'sheets' => ['DENGUE'],
        'schema' => $schema([
            $section('dengue', 'Dengue Antigen & Antibody', [
                $qualitative('igg', 'Dengue - IgG', $negPos, ['reference_text' => 'Negative']),
                $qualitative('igm', 'Dengue - IgM', $negPos, ['reference_text' => 'Negative']),
                $qualitative('ns1', 'NS1', $negPos, ['reference_text' => 'Negative']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'HBSAG',
        'name' => 'HBsAg',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => 'Rapid card / strip method',
        'sheets' => ['LFT', 'anc', 'ANC(ME)'],
        'schema' => $schema([
            $section('hbsag', 'HBsAg', [
                $qualitative('hbsag', 'HBsAg', $nonReactive, ['reference_text' => 'Non-reactive']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'The workbook prints "By card" on the LFT sheet and "By strip method" on the ANC sheets.',
    ],
    [
        'code' => 'HIV',
        'name' => 'HIV I & II',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => 'Card method',
        'sheets' => ['anc', 'ANC(ME)'],
        'schema' => $schema([
            $section('hiv', 'HIV I & II', [
                $qualitative('hiv', 'HIV I & II', $nonReactive, ['reference_text' => 'Non-reactive']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'VDRL',
        'name' => 'V.D.R.L.',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'method' => 'Strip method',
        'sheets' => ['anc', 'ANC(ME)'],
        'schema' => $schema([
            $section('vdrl', 'V.D.R.L.', [
                $qualitative('vdrl', 'V.D.R.L.', $nonReactive, ['reference_text' => 'Non-reactive']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'BGRP',
        'name' => 'Blood Group & Rh Factor',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Whole blood (EDTA)',
        'sheets' => ['anc', 'ANC(ME)'],
        'schema' => $schema([
            $section('blood_group', 'Blood Group', [
                ['key' => 'blood_group', 'label' => 'Blood Group', 'type' => 'select', 'required' => true, 'options' => ['A', 'B', 'AB', 'O']],
                ['key' => 'rh_factor', 'label' => 'Rh. Factor', 'type' => 'select', 'required' => true, 'options' => ['Positive', 'Negative']],
            ]),
        ]),
        'reporting_notes' => 'NOTE: Blood groups are classifications of blood based on the presence or absence of inherited antigenic substances on red blood cell surfaces, primarily organized by the ABO and Rh systems. The four main types (A, B, AB, O), combined with the Rh factor, determine compatibility for blood transfusions.',
        'review' => null,
    ],
    [
        'code' => 'RH-AB',
        'name' => 'Rh Antibody Titre',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Serum',
        'sheets' => ['Rh Antibody'],
        'schema' => $schema([
            $section('rh_antibody', 'Rh Antibody Titer', [
                $text('rh_antibody_titer', 'Rh Antibody Titer', ['required' => true, 'help' => 'e.g. Negative or a titre such as 1:16']),
                $text('in_bovine_albumin', 'In Bovine Albumin', ['required' => true]),
                $text('in_saline', 'In Saline', ['required' => true]),
                $longText('comments', 'Comments'),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'No reference text is given in the workbook (all example results are "Negative").',
    ],
    [
        'code' => 'MANTOUX',
        'name' => 'Mantoux Test',
        'category' => 'Serology & Immunology',
        'sample_type' => 'Intradermal (PPD)',
        'sheets' => ['MT'],
        'schema' => $schema([
            $section('mantoux', 'Mantoux Test', [
                $text('induration', 'Area of Induration', ['required' => true, 'help' => 'e.g. 1 x 1 mm']),
                $qualitative('result', 'Result', $negPos),
            ]),
        ]),
        'collection_instructions' => 'Injected 0.1 ml of PPD solution intradermally. Result noted after 48 to 72 hrs.',
        'reporting_notes' => '(Injected 0.1 ml of PPD solution; result noted after 48 to 72 hrs.)',
        'review' => 'The workbook gives no induration cut-off; the Positive/Negative result is entered by the reader.',
    ],
    [
        'code' => 'TROP-I',
        'name' => 'Troponin I (Trop-I)',
        'category' => 'Cardiac',
        'sample_type' => 'Serum',
        'method' => $cardMethod,
        'sheets' => ['TROP-I'],
        'schema' => $schema([
            $section('cardiac', 'Cardiac Test', [
                $qualitative('trop_i', 'Trop - I (Serum)', $negPos, ['reference_text' => 'Negative']),
            ]),
        ]),
        'preparation_instructions' => 'No fasting is required.',
        'reporting_notes' => "NOTE: A Trop I (Troponin I) test is a rapid blood test that measures specific proteins released into the bloodstream when the heart muscle is damaged, most commonly during a heart attack.\nPurpose: Diagnoses myocardial infarction (heart attack) and assesses unstable angina or severe heart injuries.\nSignificance: Because Troponin I is highly specific to heart muscle, elevated levels indicate active injury.\nResults: Normal levels are typically <0.04 ng/mL; levels >0.40 ng/mL strongly indicate cardiac injury. Levels rise 3–12 hours after injury, peaking within 24 hours.\nOther causes: Beyond heart attacks, elevated levels can result from myocarditis, pulmonary embolism, or extreme heart strain.",
        'review' => null,
    ],

    // ------------------------------------------------------------------ Clinical pathology
    [
        'code' => 'URINE-RM',
        'name' => 'Urine Routine & Microscopic Examination',
        'category' => 'Clinical Pathology',
        'sample_type' => 'Urine',
        'sheets' => ['URINE', 'URINE(SI)', 'URINE(ME)'],
        'schema' => $schema([
            $section('physical', 'Physical Examination', [
                $text('quantity', 'Quantity', ['help' => 'e.g. 15 ml']),
                $text('colour', 'Colour', ['help' => 'e.g. Pale Yellow']),
                $text('appearance', 'Appearance', ['help' => 'e.g. Clear']),
                ['key' => 'reaction', 'label' => 'Reaction', 'type' => 'select', 'required' => false, 'options' => ['Acidic', 'Alkaline', 'Neutral']],
                ['key' => 'specific_gravity', 'label' => 'Sp. Gravity', 'type' => 'decimal', 'required' => false, 'precision' => 3],
            ]),
            $section('chemical', 'Chemical Examination', [
                $text('albumin', 'Albumin', ['help' => 'e.g. Nil']),
                $text('sugar', 'Sugar', ['help' => 'e.g. Nil']),
                $text('acetone', 'Acetone', ['help' => 'e.g. Nil']),
                $text('blood', 'Blood Test', ['help' => 'e.g. Nil']),
                $text('bile_salt', 'Bile Salt', ['help' => 'e.g. Nil']),
                $text('bile_pigment', 'Bile Pigment', ['help' => 'e.g. Nil']),
                $text('ketone_bodies', 'Ketone Bodies', ['help' => 'e.g. Nil']),
            ]),
            $section('microscopic', 'Microscopic Examination', [
                $text('red_cells', 'Red Cells', ['help' => 'e.g. Nil']),
                $text('pus_cells', 'Pus Cells', ['help' => 'e.g. 2-4/hpf']),
                $text('epithelial_cells', 'Epithelial Cells', ['help' => 'e.g. 1-2/hpf']),
                $text('crystal', 'Crystal', ['help' => 'e.g. Nil']),
                $text('cast', 'Cast', ['help' => 'e.g. Nil']),
                $text('amorphous', 'Amorphous Material', ['help' => 'e.g. Nil']),
                $text('spermatozoa', 'Spermatozoa', ['help' => 'e.g. Nil']),
                $text('yeast', 'Yeast', ['help' => 'e.g. Nil']),
                $text('trichomonas', 'Trichomonas Vaginalis', ['help' => 'e.g. Nil']),
                $text('mucus_threads', 'Mucus Threads', ['help' => 'e.g. Nil']),
                $text('bacteria', 'Bacteria', ['help' => 'e.g. Nil']),
            ]),
        ]),
        'reporting_notes' => 'Note: A urine routine test (urinalysis) is a laboratory examination of urine that assesses its physical, chemical, and microscopic characteristics. It is a common diagnostic tool used to screen for and monitor conditions including urinary tract infections, kidney problems, diabetes, and liver diseases.',
        'review' => 'No reference values are given in the workbook. Fields are left blank (not pre-filled with "Nil") so an unexamined field is never reported as normal.',
    ],
    [
        'code' => 'UPT',
        'name' => 'Urine Pregnancy Test',
        'category' => 'Clinical Pathology',
        'sample_type' => 'Urine',
        'method' => 'Strip method',
        'sheets' => ['UPT'],
        'schema' => $schema([
            $section('pregnancy', 'Urine for Pregnancy', [
                $qualitative('upt', 'Pregnancy Test', $negPos),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => null,
    ],
    [
        'code' => 'SEMEN',
        'name' => 'Semen Examination',
        'category' => 'Clinical Pathology',
        'sample_type' => 'Semen',
        'sheets' => ['SEMEN'],
        'schema' => $schema([
            $section('physical', 'Physical Examination', [
                $text('quantity', 'Quantity', ['required' => true, 'help' => 'e.g. 1 ml']),
                $text('colour', 'Colour', ['help' => 'e.g. Whitish Gray']),
                $text('viscosity', 'Viscosity'),
                ['key' => 'reaction', 'label' => 'Reaction', 'type' => 'select', 'required' => false, 'options' => ['Alkaline', 'Acidic', 'Neutral']],
                $text('liquefaction_time', 'Liquefaction Time', ['help' => 'e.g. 20 min.']),
            ]),
            $section('chemical', 'Chemical Examination', [
                $qualitative('fructose', 'Fructose Test', ['Positive', 'Negative'], ['required' => false]),
            ]),
            $section('microscopic', 'Microscopic Examination', [
                $numeric('sperm_count', 'Sperm Count', 'million/ml', 50, 130, ['precision' => 0]),
                $numeric('motility', 'Motility', '%', null, null, ['precision' => 0, 'required' => false]),
                $numeric('active_motile', 'Active Motile', '%', null, null, ['precision' => 0, 'required' => false]),
                $numeric('sluggish_motile', 'Sluggish Motile', '%', null, null, ['precision' => 0, 'required' => false]),
                $numeric('non_motile', 'Non Motile', '%', null, null, ['precision' => 0, 'required' => false]),
            ]),
            $section('other', 'Other Constituents', [
                $text('red_cells', 'Red Cells'),
                $text('pus_cells', 'Pus Cells'),
                $text('epithelial_cells', 'Epithelial Cells'),
                $text('abstinence', 'Duration of Abstinence', ['help' => 'e.g. 3 Days']),
                $text('collection', 'Sample Collection', ['help' => 'e.g. Lab collection']),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'Only the sperm count has a reference interval in the workbook; motility fractions (stored as 0.75 etc. in Excel) are configured as percentages without intervals.',
    ],
    [
        'code' => 'STOOL',
        'name' => 'Stool Examination',
        'category' => 'Clinical Pathology',
        'sample_type' => 'Stool',
        'sheets' => ['STOOL'],
        'schema' => $schema([
            $section('physical', 'Physical Examination', [
                $text('colour', 'Colour'),
                $text('consistency', 'Consistency'),
                $text('mucus', 'Mucus', ['help' => 'e.g. Absent / Present +']),
                $text('blood', 'Blood', ['help' => 'e.g. Absent']),
                $text('parasites', 'Parasites', ['help' => 'e.g. Absent']),
            ]),
            $section('chemical', 'Chemical Examination', [
                ['key' => 'reaction', 'label' => 'Reaction', 'type' => 'select', 'required' => false, 'options' => ['Acidic', 'Alkaline', 'Neutral']],
                $qualitative('occult_blood', 'Occult Blood', $negPos, ['required' => false]),
                $qualitative('lactose', 'Lactose Test', $negPos, ['required' => false]),
            ]),
            $section('microscopic', 'Microscopic Examination', [
                $text('red_cells', 'Red Cells'),
                $text('pus_cells', 'Pus Cells'),
                $text('epithelial_cells', 'Epithelial Cells'),
                $text('fat_globules', 'Fat Globules'),
                $text('starch', 'Starch (Undigested)'),
                $text('vegetable_fibres', 'Vegetable Fibres'),
                $text('bacterial_contents', 'Bacterial Contents'),
                $text('flagellates', 'Flagellates'),
                $text('giardia', 'Giardia Lamblia'),
                $text('eh_cyst', 'Entamoeba Histolytica Cyst'),
                $text('e_coli', 'Entamoeba Coli'),
                $text('ciliates', 'Ciliates'),
                $text('ova', 'Ova'),
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'No reference values are given in the workbook.',
    ],

    // ------------------------------------------------------------------ Microbiology
    [
        'code' => 'CULTURE',
        'name' => 'Aerobic Culture & Sensitivity',
        'category' => 'Microbiology',
        'sample_type' => 'Urine',
        'sheets' => ['Culture', 'Culture Normal'],
        'schema' => $schema([
            $section('culture', 'Aerobic Culture', [
                $text('specimen', 'Specimen', ['required' => true, 'help' => 'e.g. Urine']),
                $longText('gram_stain', 'Gram Staining', ['help' => 'e.g. 7-8 pus cells/hpf, few bacteria seen']),
                $text('colony_count', 'Colony Count (CFU/ml)', ['help' => 'e.g. >100,000 or <10,000']),
                $text('organism', 'Organism(s) Isolated', ['help' => 'e.g. Klebsiella pneumoniae grown']),
                $longText('result', 'Result', ['help' => 'e.g. No pathogenic growth after 48 hrs isolation period']),
            ]),
            $section('sensitivity', 'Antimicrobial Sensitivity', [
                [
                    'key' => 'antibiogram',
                    'label' => 'Antimicrobial Sensitivity',
                    'type' => 'table',
                    'required' => false,
                    'row_header' => 'Antimicrobial Agent',
                    'columns' => [
                        ['key' => 'result', 'label' => 'Result', 'type' => 'select', 'options' => ['Sensitive', 'Intermediate', 'Resistant']],
                    ],
                    'rows' => array_map(fn ($label) => ['label' => $label], [
                        'Amikacin', 'Amoxicillin', 'Ampicillin', 'Cotrimoxazole', 'Ciprofloxacin', 'Cefazolin',
                        'Ceftazidime', 'Cefepime', 'Cefotaxime', 'Clindamycin', 'Chloramphenicol', 'Erythromycin',
                        'Gentamicin', 'Gentamicin HL', 'Imipenem', 'Levofloxacin', 'Linezolid', 'Teicoplanin',
                        'Tetracycline', 'Streptomycin', 'Piperacillin', 'Penicillin', 'Penicillin - G', 'Polymyxin B',
                        'Roxithromycin', 'Nitrofurantoin', 'Nalidixic acid', 'Norfloxacin', 'Vancomycin',
                    ]),
                    'allow_add_rows' => true,
                ],
            ]),
        ]),
        'reporting_notes' => null,
        'review' => 'Antimicrobial names were spelling-corrected (e.g. "Amoxcillin", "Nitrofurantion", "Polymixin B"). "Intermediate" was added as a standard susceptibility category. The pathologist name printed on the workbook sheet is intentionally not seeded — configure signatories in .env.',
    ],
];
