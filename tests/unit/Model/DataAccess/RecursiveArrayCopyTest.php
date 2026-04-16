<?php

namespace unit\Model\DataAccess;

use Model\DataAccess\RecursiveArrayCopy;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TestObjectWithPublicProps {
    use RecursiveArrayCopy;

    public string $name = 'John';
    public int $age = 30;
}

class TestObjectWithArrayProperties {
    use RecursiveArrayCopy;

    public array $items = ['item1', 'item2'];
}

class TestObjectWithTitle {
    use RecursiveArrayCopy;

    public string $title = 'Nested';
}

class TestObjectWithNestedObject {
    use RecursiveArrayCopy;

    public object $nested;

    public function __construct($nested)
    {
        $this->nested = $nested;
    }
}

class TestObjectWithMaskProperties {
    use RecursiveArrayCopy;

    public int $id = 1;
    public string $name = 'John';
    public string $email = 'john@example.com';
}

class TestObjectWithNullProperty {
    use RecursiveArrayCopy;

    public ?string $name = null;
}

class TestObjectWithMixedProperties {
    use RecursiveArrayCopy;

    private int $id = 1;
    protected string $secret = 'hidden';
    public string $email = 'public@example.com';
}

class TestObjectEmpty {
    use RecursiveArrayCopy;
}

class RecursiveArrayCopyTest extends AbstractTest
{
    #[Test]
    public function testToArrayWithPublicProperties()
    {
        $testObject = new TestObjectWithPublicProps();

        $expected = [
            'name' => 'John',
            'age' => 30,
        ];

        $this->assertEquals($expected, $testObject->toArray());
    }

    #[Test]
    public function testToArrayWithArrayProperties()
    {
        $testObject = new TestObjectWithArrayProperties();

        $expected = [
            'items' => ['item1', 'item2'],
        ];

        $this->assertEquals($expected, $testObject->toArray());
    }

    #[Test]
    public function testToArrayWithNestedObjectProperties()
    {
        $nestedObject = new TestObjectWithTitle();

        $testObject = new TestObjectWithNestedObject($nestedObject);

        $expected = [
            'nested' => [
                'title' => 'Nested',
            ],
        ];

        $this->assertEquals($expected, $testObject->toArray());
    }

    #[Test]
    public function testToArrayWithMask()
    {
        $testObject = new TestObjectWithMaskProperties();

        $expected = [
            'name' => 'John',
        ];

        $this->assertEquals($expected, $testObject->toArray(['name']));
    }

    #[Test]
    public function testToArrayWithNullPublicProperty()
    {
        $testObject = new TestObjectWithNullProperty();

        $expected = [
            'name' => null,
        ];

        $this->assertEquals($expected, $testObject->toArray());
    }

    #[Test]
    public function testToArrayWithNonPublicProperties()
    {
        $testObject = new TestObjectWithMixedProperties();

        $expected = [
            'email' => 'public@example.com',
        ];

        $this->assertEquals($expected, $testObject->toArray());
    }

    #[Test]
    public function testToArrayWithEmptyObject()
    {
        $testObject = new TestObjectEmpty();

        $expected = [];

        $this->assertEquals($expected, $testObject->toArray());
    }
}