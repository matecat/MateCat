<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Shop;

use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\UnknownPropertyException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Shop\ItemHTSQuoteJob;

/**
 * AbstractItem is abstract; ItemHTSQuoteJob is its only concrete subclass, so it
 * is used here as the vehicle for the inherited ArrayAccess behaviour.
 */
#[Group('unit')]
class AbstractItemTest extends AbstractTest
{
    #[Test]
    public function offsetSet_throws_logic_exception_for_an_empty_string_key(): void
    {
        $item = new ItemHTSQuoteJob();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Can not assign a value to an empty key.');

        $item[''] = 'some value';
    }

    #[Test]
    public function offsetSet_throws_logic_exception_for_a_zero_key(): void
    {
        $item = new ItemHTSQuoteJob();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Can not assign a value to an empty key.');

        $item->offsetSet(0, 'some value');
    }

    #[Test]
    public function offsetSet_throws_unknown_property_for_a_key_outside_the_storage(): void
    {
        $item = new ItemHTSQuoteJob();

        $this->expectException(UnknownPropertyException::class);

        $item['not_a_declared_field'] = 'some value';
    }

    #[Test]
    public function offsetSet_accepts_a_declared_key_and_sanitizes_the_value(): void
    {
        $item = new ItemHTSQuoteJob();

        $item['project_name'] = '<b>My project</b>';

        $this->assertStringNotContainsString('<b>', (string)$item['project_name']);
        $this->assertStringContainsString('My project', (string)$item['project_name']);
    }

    #[Test]
    public function constructor_populates_the_type_class_key(): void
    {
        $item = new ItemHTSQuoteJob();

        $this->assertSame(ItemHTSQuoteJob::class, $item['_id_type_class']);
    }
}
