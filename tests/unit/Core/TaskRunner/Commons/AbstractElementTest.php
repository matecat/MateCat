<?php

namespace Matecat\Core\TaskRunner\Commons;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;

/**
 * Minimal concrete subclass with a nullable property for offsetUnset testing.
 */
class ConcreteElement extends AbstractElement
{
    public ?string $label = null;
}

class AbstractElementTest extends AbstractTest
{
    // ─── Constructor ─────────────────────────────────────────────────────────────

    #[Test]
    public function constructor_populates_declared_properties_from_array(): void
    {
        $params = new Params(['foo' => 'bar', 'baz' => 42]);

        $element = new QueueElement([
            'classLoad'  => 'SomeWorker',
            'params'     => $params,
            'reQueueNum' => 3,
        ]);

        $this->assertSame('SomeWorker', $element->classLoad);
        $this->assertSame($params, $element->params);
        $this->assertSame(3, $element->reQueueNum);
    }

    #[Test]
    public function constructor_with_empty_array_leaves_defaults_intact(): void
    {
        $element = new QueueElement([]);

        $this->assertSame(0, $element->reQueueNum);
    }

    #[Test]
    public function constructor_converts_nested_array_to_params_object(): void
    {
        $element = new QueueElement([
            'classLoad' => 'SomeWorker',
            'params'    => ['key' => 'value'],
        ]);

        $this->assertInstanceOf(Params::class, $element->params);
        $this->assertSame('value', $element->params['key']);
    }

    #[Test]
    public function constructor_with_unknown_property_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown property unknownProp');

        new QueueElement(['unknownProp' => 'value']);
    }

    // ─── __set ───────────────────────────────────────────────────────────────────

    #[Test]
    public function set_with_undeclared_property_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown property nonExistent');

        $element = new QueueElement([]);
        $element->nonExistent = 'value';
    }

    // ─── offsetExists ────────────────────────────────────────────────────────────

    #[Test]
    public function offsetExists_returns_true_for_declared_property(): void
    {
        $element = new QueueElement([]);

        $this->assertTrue(isset($element['classLoad']));
    }

    #[Test]
    public function offsetExists_returns_false_for_unknown_property(): void
    {
        $element = new QueueElement([]);

        $this->assertFalse(isset($element['nonExistent']));
    }

    // ─── offsetGet ───────────────────────────────────────────────────────────────

    #[Test]
    public function offsetGet_returns_property_value(): void
    {
        $element = new QueueElement(['classLoad' => 'MyWorker']);

        $this->assertSame('MyWorker', $element['classLoad']);
    }

    #[Test]
    public function offsetGet_returns_null_for_unknown_property(): void
    {
        $element = new QueueElement([]);

        $this->assertNull($element['nonExistent']);
    }

    // ─── offsetSet ───────────────────────────────────────────────────────────────

    #[Test]
    public function offsetSet_updates_existing_property(): void
    {
        $element = new QueueElement(['classLoad' => 'OldWorker']);
        $element['classLoad'] = 'NewWorker';

        $this->assertSame('NewWorker', $element['classLoad']);
    }

    #[Test]
    public function offsetSet_ignores_unknown_property(): void
    {
        $element = new QueueElement([]);
        // offsetSet only sets if offsetExists — unknown key is silently ignored
        $element['nonExistent'] = 'value';

        $this->assertNull($element['nonExistent']);
    }

    // ─── offsetUnset ─────────────────────────────────────────────────────────────

    #[Test]
    public function offsetUnset_sets_nullable_property_to_null(): void
    {
        $element = new ConcreteElement(['label' => 'hello']);
        $this->assertSame('hello', $element['label']);

        unset($element['label']);

        $this->assertNull($element['label']);
    }

    #[Test]
    public function offsetUnset_ignores_unknown_property(): void
    {
        $element = new ConcreteElement([]);
        // should not throw
        unset($element['nonExistent']);

        $this->assertNull($element['nonExistent']);
    }

    // ─── toArray ─────────────────────────────────────────────────────────────────

    #[Test]
    public function toArray_returns_flat_array_of_properties(): void
    {
        $element = new QueueElement([
            'classLoad'  => 'MyWorker',
            'reQueueNum' => 2,
        ]);

        $result = $element->toArray();

        $this->assertIsArray($result);
        $this->assertSame('MyWorker', $result['classLoad']);
        $this->assertSame(2, $result['reQueueNum']);
    }

    #[Test]
    public function toArray_recursively_converts_nested_abstract_element(): void
    {
        $params = new Params(['key' => 'val']);
        $element = new QueueElement([
            'classLoad' => 'MyWorker',
            'params'    => $params,
        ]);

        $result = $element->toArray();

        $this->assertIsArray($result['params']);
        $this->assertSame('val', $result['params']['key']);
    }

    // ─── __toString ──────────────────────────────────────────────────────────────

    #[Test]
    public function toString_returns_valid_json(): void
    {
        $element = new QueueElement([
            'classLoad'  => 'MyWorker',
            'reQueueNum' => 1,
        ]);

        $json = (string)$element;

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('MyWorker', $decoded['classLoad']);
        $this->assertSame(1, $decoded['reQueueNum']);
    }

    #[Test]
    public function toString_returns_json_string(): void
    {
        $element = new QueueElement([]);

        $result = (string)$element;

        $this->assertIsString($result);
        $this->assertJson($result);
    }
}
