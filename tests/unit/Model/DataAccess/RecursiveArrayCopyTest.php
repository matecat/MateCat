<?php

namespace unit\Model\DataAccess;

use Model\DataAccess\RecursiveArrayCopy;
use PHPUnit\Framework\TestCase;

class RecursiveArrayCopyTest extends TestCase {
    use RecursiveArrayCopy;

    public function testToArrayWithPublicProperties() {
        $testObject = new class {
            use RecursiveArrayCopy;

            public string $name = 'John';
            public int    $age  = 30;
        };

        $expected = [
                'name' => 'John',
                'age'  => 30,
        ];

        $this->assertEquals( $expected, $testObject->toArray() );
    }

    public function testToArrayWithArrayProperties() {
        $testObject = new class {
            use RecursiveArrayCopy;

            public array $items = [ 'item1', 'item2' ];
        };

        $expected = [
                'items' => [ 'item1', 'item2' ],
        ];

        $this->assertEquals( $expected, $testObject->toArray() );
    }

    public function testToArrayWithNestedObjectProperties() {
        $nestedObject = new class {
            use RecursiveArrayCopy;

            public string $title = 'Nested';
        };

        $testObject = new class ( $nestedObject ) {
            use RecursiveArrayCopy;

            public object $nested;

            public function __construct( $nested ) {
                $this->nested = $nested;
            }
        };

        $expected = [
                'nested' => [
                        'title' => 'Nested',
                ],
        ];

        $this->assertEquals( $expected, $testObject->toArray() );
    }

    public function testToArrayWithMask() {
        $testObject = new class {
            use RecursiveArrayCopy;

            public int    $id    = 1;
            public string $name  = 'John';
            public string $email = 'john@example.com';
        };

        $expected = [
                'name' => 'John',
        ];

        $this->assertEquals( $expected, $testObject->toArray( [ 'name' ] ) );
    }

    public function testToArrayWithNullPublicProperty() {
        $testObject = new class {
            use RecursiveArrayCopy;

            public ?string $name = null;
        };

        $expected = [
                'name' => null,
        ];

        $this->assertEquals( $expected, $testObject->toArray() );
    }

    public function testToArrayWithNonPublicProperties() {
        $testObject = new class {
            use RecursiveArrayCopy;

            private int      $id     = 1;
            protected string $secret = 'hidden';
            public string    $email  = 'public@example.com';
        };

        $expected = [
                'email' => 'public@example.com',
        ];

        $this->assertEquals( $expected, $testObject->toArray() );
    }

    public function testToArrayWithEmptyObject() {
        $testObject = new class {
            use RecursiveArrayCopy;
        };

        $expected = [];

        $this->assertEquals( $expected, $testObject->toArray() );
    }
}