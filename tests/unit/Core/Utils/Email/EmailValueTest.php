<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Email;

use DateTimeImmutable;
use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Stringable;
use Utils\Email\EmailValue;

/**
 * What a template receives from {@see EmailValue::escapeAll()}: plain PHP values, so `if`, `empty()`,
 * `count()` and `==` answer as they would for any value.
 */
class EmailValueTest extends AbstractTest
{
    #[Test]
    public function valuesThatCannotCarryMarkupKeepTheirType(): void
    {
        $escaped = EmailValue::escapeAll([
            'null' => null,
            'false' => false,
            'true' => true,
            'int' => 0,
            'float' => 1.5,
            'emptyList' => [],
        ]);

        $this->assertSame(
            ['null' => null, 'false' => false, 'true' => true, 'int' => 0, 'float' => 1.5, 'emptyList' => []],
            $escaped
        );
    }

    /**
     * A database row brings numbers as strings; they stay strings that compare equal to the number.
     */
    #[Test]
    public function aNumericStringStaysAStringEqualToItsNumber(): void
    {
        $escaped = EmailValue::escapeAll(['source_page' => '2', 'empty' => '', 'zero' => '0']);

        $this->assertSame('2', $escaped['source_page']);
        $this->assertTrue($escaped['source_page'] == 2);
        $this->assertSame('', $escaped['empty']);
        $this->assertSame('0', $escaped['zero']);
    }

    #[Test]
    public function anArrayKeepsItsKeysAndCanBeCounted(): void
    {
        $escaped = EmailValue::escapeAll(['issues' => [7 => ['category' => 'Style <b>'], 9 => ['category' => 'Terms']]]);

        $this->assertCount(2, $escaped['issues']);
        $this->assertSame([7, 9], array_keys($escaped['issues']));
        $this->assertSame('Style &lt;b&gt;', $escaped['issues'][7]['category']);
    }

    /**
     * Example: an object with `__toString()` is printed as its string, so it is escaped like one.
     */
    #[Test]
    public function aStringableObjectBecomesAnEscapedString(): void
    {
        $value = new class implements Stringable {
            public function __toString(): string
            {
                return 'Ben & Jerry <b>';
            }
        };

        $this->assertSame('Ben &amp; Jerry &lt;b&gt;', EmailValue::escapeAll(['name' => $value])['name']);
    }

    /**
     * A struct cannot be escaped, so it never reaches a template: the error names the key, which is
     * where the fix goes (`->toArray()`, or only the fields the template reads).
     */
    #[Test]
    public function anObjectThatIsNotStringableIsRefusedByItsKey(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("'data.recipient' is a Model\\Users\\UserStruct");

        EmailValue::escapeAll(['data' => ['recipient' => new UserStruct(), 'isPreviousChangeAuthor' => true]]);
    }

    #[Test]
    public function aDateObjectIsRefusedToo(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("'due_date' is a DateTimeImmutable");

        EmailValue::escapeAll(['due_date' => new DateTimeImmutable('2026-11-11 17:04:15')]);
    }
}
