<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Files;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Files\CsvCell;

/**
 * The guard between a name somebody typed and the spreadsheet a colleague opens.
 *
 * Excel, LibreOffice and Google Sheets evaluate a cell that opens with `=`, `+`, `-` or `@`, and
 * nothing in the CSV format marks a cell as text, so the writer has to. This is a security rule with
 * no visible symptom when it is missing, which is why it gets its own test rather than being covered
 * incidentally by whichever export happens to be exercised.
 */
#[Group('unit')]
#[CoversClass(CsvCell::class)]
class CsvCellTest extends AbstractTest
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function cellsToNeutralise(): array
    {
        return [
            'equals opens a formula' => ["=cmd|' /C calc'!A1", "'=cmd|' /C calc'!A1"],
            'plus opens a formula' => ['+1+1', "'+1+1"],
            'at opens a formula' => ['@SUM(A1)', "'@SUM(A1)"],
            'minus opens a formula' => ['-2+3', "'-2+3"],
            'a leading tab is skipped and what follows is read' => ["\t=1+1", "'\t=1+1"],
            'a leading CR likewise' => ["\r=1+1", "'\r=1+1"],
            'hyperlink' => ['=HYPERLINK("http://evil.example")', '\'=HYPERLINK("http://evil.example")'],
        ];
    }

    #[Test]
    #[DataProvider('cellsToNeutralise')]
    public function it_marks_a_formula_cell_as_text(string $value, string $expected): void
    {
        self::assertSame($expected, CsvCell::inert($value));
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function cellsToLeaveAlone(): array
    {
        return [
            'an ordinary label' => ['Accuracy'],
            'a name with an ampersand' => ['A & B'],
            'a sentence' => ['The translation is wrong here'],
            'a number' => ['42'],
            'a minus inside rather than in front' => ['Accuracy - minor'],
            'empty' => [''],
            'null' => [null],
        ];
    }

    #[Test]
    #[DataProvider('cellsToLeaveAlone')]
    public function it_leaves_a_cell_that_cannot_be_read_as_a_formula(?string $value): void
    {
        // A quote on every cell would be a quote in every formula bar, so the rule has to be narrow.
        self::assertSame($value, CsvCell::inert($value));
    }

    #[Test]
    public function a_label_that_legitimately_begins_with_a_minus_is_escaped_not_stripped(): void
    {
        // A severity called "-2 points" is a label, not an attack, and the reader still has to see it.
        self::assertSame("'-2 points", CsvCell::inert('-2 points'));
    }

    #[Test]
    public function it_neutralises_a_row_and_leaves_its_non_strings_alone(): void
    {
        // Casting a number here would change what the column holds, and a number cannot open with
        // one of those characters anyway.
        self::assertSame(
            ['ID' => 7, 'Category' => "'=1+1", 'Points' => 1.5, 'Message' => 'fine', 'Flag' => true],
            CsvCell::inertRow(['ID' => 7, 'Category' => '=1+1', 'Points' => 1.5, 'Message' => 'fine', 'Flag' => true])
        );
    }
}
