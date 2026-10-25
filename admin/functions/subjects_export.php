<?php

/**
 * Excel export of the subjects, laid out for Moodle's "Upload courses" tool.
 *
 * Builds a real .xlsx with PHP's built-in ZipArchive (no Composer packages needed).
 * Requires the PHP "zip" extension (enabled by default in XAMPP; if not, remove the
 * semicolon before "extension=zip" in php.ini).
 *
 * Sheet 1 "Moodle Courses": one row per subject, header row = Moodle field names.
 *   shortname = "Code - SHORT NAME"   e.g. 10234 - ENG 7
 *   fullname  = "Code - Subject"      e.g. 10234 - English 7
 *   category  = left BLANK on purpose (you fill in the Moodle Category ID).
 * Sheet 2 "How to use": short upload instructions.
 */

const MOODLE_EXPORT_HEADERS = ["shortname", "fullname", "category"];

/** Make text safe for XML (escape + drop control characters Excel rejects). */
function xlsx_text(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', "", $value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, "UTF-8");
}

/** Subject title without a trailing grade number ("English 9" => "English"). */
function subject_base_title(string $title): string
{
    $title = trim(preg_replace('/\s+/', " ", $title));
    if (preg_match('/^(.*?)[\s-]*(\d+|[IVX]{1,4})$/u', $title, $m) && trim($m[1]) !== "") {
        return trim($m[1]);
    }
    return $title;
}

/**
 * Short subject code used in the shortname.
 *   Filipino => FIL, English => ENG, Science => SCI, Mathematics => MATH,
 *   Araling Panlipunan => AP, Music and Arts => MA, Values Education => VE,
 *   Technology and Livelihood Education => TLE, Physical Education and Health => PEH.
 * Fix any you dislike in the sheet before uploading.
 */
function subject_abbr(string $title): string
{
    $title = subject_base_title($title);
    $known = ["mathematics" => "MATH", "math" => "MATH"];
    if (isset($known[mb_strtolower($title)])) {
        return $known[mb_strtolower($title)];
    }
    $skip = ["and", "of", "the", "in", "to", "for", "at", "on"];
    $words = array_values(array_filter(
        preg_split('/[^\p{L}\p{N}]+/u', $title, -1, PREG_SPLIT_NO_EMPTY),
        fn($w) => !in_array(mb_strtolower($w), $skip, true)
    ));
    if (count($words) >= 2 && count($words) <= 4) {
        $abbr = implode("", array_map(fn($w) => mb_substr($w, 0, 1), $words));
    } else {
        $abbr = mb_substr($words[0] ?? $title, 0, 3);
    }
    return mb_strtoupper($abbr);
}

/** Grade taken from the number at the end of the title ("English 7" => 7), or null. */
function subject_grade(string $title): ?int
{
    return preg_match('/(\d+)\s*$/', $title, $m) ? (int) $m[1] : null;
}

/** Short name like "ENG 7": the abbreviation plus the trailing number, if the title has one. */
function subject_short_name(string $title): string
{
    $title = trim(preg_replace('/\s+/', " ", $title));
    $suffix = "";
    if (preg_match('/^(.*?)[\s-]*(\d+|[IVX]{1,4})$/u', $title, $m) && trim($m[1]) !== "") {
        $suffix = " " . $m[2];
    }
    return subject_abbr($title) . $suffix;
}

/** Column letter for a 0-based index (0 => A). */
function xlsx_col(int $index): string
{
    $letters = "";
    for ($n = $index; $n >= 0; $n = intdiv($n, 26) - 1) {
        $letters = chr(65 + $n % 26) . $letters;
    }
    return $letters;
}

/** A text cell (inline string, so no shared-strings part is needed). */
function xlsx_cell(int $col, int $row, string $text, int $style): string
{
    $ref = xlsx_col($col) . $row;
    if ($text === "") {
        return '<c r="' . $ref . '" s="' . $style . '"/>'; // styled but empty
    }
    return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
        . xlsx_text($text) . '</t></is></c>';
}

/**
 * Build the workbook and return the path of a temporary .xlsx file.
 * $subjects is the list returned by subjects_all().
 */
function subjects_build_xlsx(array $subjects): string
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException("The PHP zip extension is not enabled (needed to create .xlsx files).");
    }

    /* ----- Sheet 1: Moodle Courses ----- */
    // Style ids are defined in $styles below:
    // 1 header, 2 text, 3 text (zebra), 4 blank category, 5 title, 6 note, 7 label, 8 body
    $rows = '<row r="1" ht="24" customHeight="1">';
    foreach (MOODLE_EXPORT_HEADERS as $c => $head) {
        $rows .= xlsx_cell($c, 1, $head, 1);
    }
    $rows .= "</row>";

    $r = 2;
    foreach ($subjects as $i => $s) {
        $zebra = $i % 2 === 1 ? 3 : 2;
        $rows .= '<row r="' . $r . '">'
            . xlsx_cell(0, $r, $s["code"] . " - " . subject_short_name((string) $s["title"]), $zebra)
            . xlsx_cell(1, $r, $s["code"] . " - " . trim(preg_replace('/\s+/', " ", (string) $s["title"])), $zebra)
            . xlsx_cell(2, $r, "", 4)                     // category: blank, highlighted
            . "</row>";
        $r++;
    }
    $last = max(2, $r - 1);

    $sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0" tabSelected="1">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="C2" sqref="C2"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols>'
        . '<col min="1" max="1" width="24" customWidth="1"/>'
        . '<col min="2" max="2" width="60" customWidth="1"/>'
        . '<col min="3" max="3" width="16" customWidth="1"/>'
        . ''
        . '</cols>'
        . '<sheetData>' . $rows . '</sheetData>'
        . '<autoFilter ref="A1:C' . $last . '"/>'
        . '<dataValidations count="1">'
        . '<dataValidation type="whole" operator="greaterThan" allowBlank="1" showErrorMessage="1" '
        . 'errorTitle="Category ID" error="Enter the Moodle Category ID as a whole number (for example 3)." '
        . 'showInputMessage="1" promptTitle="Category ID" prompt="Type the Moodle Category ID number here." '
        . 'sqref="C2:C' . max($last, 500) . '"><formula1>0</formula1></dataValidation>'
        . '</dataValidations>'
        . '<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
        . '</worksheet>';

    /* ----- Sheet 2: How to use ----- */
    $guide = [
        ["Column", "What it is"],
        ["shortname", "Code - short subject name, e.g. 10234 - ENG 7. Must be unique in Moodle."],
        ["fullname", "Code - subject, e.g. 10234 - English 7."],
        ["category", "Moodle Category ID. Left blank on purpose: fill in the yellow cells."],
    ];
    $rows2 = '<row r="1" ht="28" customHeight="1">' . xlsx_cell(0, 1, "Moodle course upload", 5) . "</row>";
    $rows2 .= '<row r="2">' . xlsx_cell(0, 2, "Exported " . date("F j, Y, g:i A"), 6) . "</row>";
    $r = 4;
    foreach ($guide as $i => $line) {
        $rows2 .= '<row r="' . $r . '">'
            . xlsx_cell(0, $r, $line[0], $i === 0 ? 1 : 7)
            . xlsx_cell(1, $r, $line[1], $i === 0 ? 1 : 8)
            . "</row>";
        $r++;
    }
    $r++;
    $steps = [
        "1. On the Moodle Courses sheet, type the Category ID in every yellow cell.",
        "2. Keep the header row exactly as it is (Moodle reads these names).",
        "3. File > Save As > CSV UTF-8 (Comma delimited). Only the first sheet is saved.",
        "4. In Moodle: Site administration > Courses > Upload courses, then choose the CSV file.",
        "5. Preview the result and confirm the upload.",
    ];
    foreach ($steps as $text) {
        $rows2 .= '<row r="' . $r . '">' . xlsx_cell(0, $r, $text, 0) . "</row>";
        $r++;
    }

    $sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/>'
        . '<cols><col min="1" max="1" width="16" customWidth="1"/><col min="2" max="2" width="78" customWidth="1"/></cols>'
        . '<sheetData>' . $rows2 . '</sheetData>'
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';

    /* ----- Styles ----- */
    $border = '<border><left style="thin"><color rgb="FFD0D7E2"/></left><right style="thin"><color rgb="FFD0D7E2"/></right>'
        . '<top style="thin"><color rgb="FFD0D7E2"/></top><bottom style="thin"><color rgb="FFD0D7E2"/></bottom><diagonal/></border>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="5">'
        . '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/><family val="2"/></font>'                      // 0 normal
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'                  // 1 header
        . '<font><b/><sz val="11"/><color rgb="FF1F3A5F"/><name val="Calibri"/><family val="2"/></font>'                  // 2 label
        . '<font><i/><sz val="10"/><color rgb="FF6B7280"/><name val="Calibri"/><family val="2"/></font>'                  // 3 note
        . '<font><b/><sz val="16"/><color rgb="FF1F3A5F"/><name val="Calibri"/><family val="2"/></font>'                  // 4 title
        . '</fonts>'
        . '<fills count="5">'
        . '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/><bgColor indexed="64"/></patternFill></fill>'  // 2 navy
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill>'  // 3 yellow
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F6FA"/><bgColor indexed="64"/></patternFill></fill>'  // 4 zebra
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $border . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="9">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                                       // 0
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>' // 1 header
        . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'                         // 2 body
        . '<xf numFmtId="49" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'          // 3 zebra
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="top"/></xf>'                        // 4 category
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                                              // 5 title
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                                              // 6 note
        . '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'                                                                                // 7 label
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'                                              // 8 text
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $ns = 'http://schemas.openxmlformats.org/';
    $parts = [
        "[Content_Types].xml" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        "_rels/.rels" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        "xl/workbook.xml" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $ns . 'officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets><sheet name="Moodle Courses" sheetId="1" r:id="rId1"/><sheet name="How to use" sheetId="2" r:id="rId2"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'Moodle Courses\'!$A$1:$C$' . $last . '</definedName></definedNames>'
            . '</workbook>',
        "xl/_rels/workbook.xml.rels" => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="' . $ns . 'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="' . $ns . 'officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        "xl/styles.xml" => $styles,
        "xl/worksheets/sheet1.xml" => $sheet1,
        "xl/worksheets/sheet2.xml" => $sheet2,
    ];

    $tmp = tempnam(sys_get_temp_dir(), "subj");
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Could not create the Excel file.");
    }
    foreach ($parts as $name => $xml) {
        $zip->addFromString($name, $xml);
    }
    $zip->close();
    return $tmp;
}

/** Build the workbook and send it to the browser as a download. Ends the request. */
function subjects_send_xlsx(array $subjects, ?int $grade = null): void
{
    $file = subjects_build_xlsx($subjects);
    $name = "moodle-courses-" . ($grade ? "grade-{$grade}-" : "") . date("Ymd") . ".xlsx";

    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header("Content-Length: " . filesize($file));
    header("Cache-Control: private, max-age=0, must-revalidate");
    readfile($file);
    unlink($file);
    exit;
}
