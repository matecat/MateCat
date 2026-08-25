<?php

declare(strict_types=1);

namespace Utils\Files;

/**
 * Keeps a spreadsheet from reading an exported cell as a formula.
 *
 * Excel, LibreOffice and Google Sheets all evaluate a cell that opens with `=`, `+`, `-` or `@`, so
 * `=cmd|' /C calc'!A1` in a category label is a command that runs when a colleague opens the report
 * someone sent them. Nothing in the CSV format marks a cell as text, so the writer has to.
 *
 * The quality-report download ({@see \Controller\API\V3\DownloadQRController}) is the only export
 * that reaches this today, over every row including the headings, since those are category labels.
 * It is a class rather than a private method there because being a private method is how the guard
 * came to be missing: it lived on the QA report view, and the download beside it never got one.
 * `TMSService::exportJobAsCSV()` and {@see \Utils\Files\CSV::save()} still write user-typed values
 * through `fputcsv` unguarded, and are the callers this is waiting for.
 */
final class CsvCell
{
    /**
     * The leading tab and CR are in the class because a spreadsheet skips them and then reads what
     * follows the same way.
     *
     * A single quote is the prefix all three applications recognise as "this cell is text". It shows
     * in the formula bar and not in the cell, so a value that needed it still reads correctly.
     *
     * Escaped rather than stripped, because a label may legitimately begin with a minus — a severity
     * called "-2 points" is a label, not an attack — and the reader must still see it.
     */
    public static function inert(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // `!== 0` rather than `=== 1`: preg_match returns false when PCRE gives up, and this rule
        // decides whether to neutralise a cell. Escaping a value that did not need it costs a
        // leading quote in the formula bar; not escaping one that did runs a command.
        return preg_match('/^[=+\-@\t\r]/', $value) !== 0 ? "'" . $value : $value;
    }

    /**
     * The same rule for a row, for a writer that assembles its cells elsewhere. Non-strings are
     * returned untouched: a number cannot open with one of those characters, and casting it here
     * would change what the column holds.
     *
     * @param array<array-key, mixed> $row
     *
     * @return array<array-key, mixed>
     */
    public static function inertRow(array $row): array
    {
        return array_map(
            static fn(mixed $value): mixed => is_string($value) ? self::inert($value) : $value,
            $row
        );
    }
}
