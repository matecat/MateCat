<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Email;

use ArrayObject;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use TypeError;
use Utils\Email\EmailValue;

/**
 * The email templates test wrapped values with `if`, `empty()`, `count()` and `==`. A wrapper object
 * is always truthy and is not an array, so each of those must still see the value it wraps.
 */
class EmailValueTest extends AbstractTest
{
    #[Test]
    public function aWrappedListCanBeCounted(): void
    {
        $wrapped = EmailValue::wrapAll([
            'issues' => [['category' => 'Style'], ['category' => 'Terminology']],
            'none' => [],
            'segmentInfo' => ['issues' => [1, 2, 3]],
        ]);

        $this->assertCount(2, $wrapped['issues']);
        $this->assertCount(0, $wrapped['none']);
        $this->assertCount(3, $wrapped['segmentInfo']['issues']);
    }

    #[Test]
    public function aWrappedCountableObjectIsCountedByItself(): void
    {
        $wrapped = EmailValue::wrapAll(['rows' => new ArrayObject(['a', 'b', 'c'])]);

        $this->assertCount(3, $wrapped['rows']);
    }

    #[Test]
    public function countingAWrappedStringFailsLikeCountingAString(): void
    {
        $wrapped = EmailValue::wrapAll(['name' => 'Giulia']);

        $this->expectException(TypeError::class);

        count($wrapped['name']);
    }

    #[Test]
    public function emptyValuesStayEmpty(): void
    {
        $wrapped = EmailValue::wrapAll([
            'senderEmail' => '',
            'zero' => '0',
            'issues' => [],
            'user' => ['email' => '', 'comments' => []],
        ]);

        $this->assertSame('', $wrapped['senderEmail']);
        $this->assertSame('0', $wrapped['zero']);
        $this->assertSame([], $wrapped['issues']);
        $this->assertSame('', $wrapped['user']['email']);
        $this->assertSame([], $wrapped['user']['comments']);
    }

    #[Test]
    public function numericStringsArePassedAsTheyAre(): void
    {
        $wrapped = EmailValue::wrapAll([
            'id_job' => '12885070',
            'penalty' => '2.50',
            'comment' => ['source_page' => '2'],
        ]);

        $this->assertSame('12885070', $wrapped['id_job']);
        $this->assertSame('2.50', $wrapped['penalty']);
        $this->assertSame('2', $wrapped['comment']['source_page']);
    }

    #[Test]
    public function falseAndNullStayFalsy(): void
    {
        $wrapped = EmailValue::wrapAll([
            'showTitle' => false,
            'changeAuthor' => null,
            'data' => ['isPreviousChangeAuthor' => false, 'author' => null],
        ]);

        $this->assertFalse($wrapped['showTitle']);
        $this->assertNull($wrapped['changeAuthor']);
        $this->assertFalse($wrapped['data']['isPreviousChangeAuthor']);
        $this->assertNull($wrapped['data']['author']);
        $this->assertNull($wrapped['data']['missing_key']);
    }

    #[Test]
    public function numbersAndTrueArePassedAsTheyAre(): void
    {
        $wrapped = EmailValue::wrapAll([
            'total' => 12,
            'penalty' => 2.5,
            'showTitle' => true,
            'comment' => ['source_page' => 1],
            'ids' => [4, 5],
        ]);

        $this->assertSame(12, $wrapped['total']);
        $this->assertSame(2.5, $wrapped['penalty']);
        $this->assertTrue($wrapped['showTitle']);
        $this->assertSame(1, $wrapped['comment']['source_page']);
        $this->assertSame([4, 5], iterator_to_array($wrapped['ids']));
    }

    #[Test]
    public function stringsAndArraysAreStillWrapped(): void
    {
        $wrapped = EmailValue::wrapAll([
            'name' => '<b>Giulia</b>',
            'user' => ['first_name' => '<b>Giulia</b>'],
        ]);

        $this->assertInstanceOf(EmailValue::class, $wrapped['name']);
        $this->assertInstanceOf(EmailValue::class, $wrapped['user']);
        $this->assertSame('&lt;b&gt;Giulia&lt;/b&gt;', (string)$wrapped['name']);
        $this->assertSame('&lt;b&gt;Giulia&lt;/b&gt;', (string)$wrapped['user']['first_name']);
    }
}
