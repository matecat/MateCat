<?php

namespace unit\Utils\Validator;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use stdClass;
use Utils\Validator\Contracts\ValidatorObject;

class ValidatorObjectTest extends AbstractTest
{
    #[Test]
    public function fromObjectCopiesProperties(): void
    {
        $obj = new stdClass();
        $obj->name = 'test';
        $obj->value = 42;

        $vo = ValidatorObject::fromObject($obj);

        $this->assertSame('test', $vo->name);
        $this->assertSame(42, $vo->value);
    }

    #[Test]
    public function fromArrayCopiesEntries(): void
    {
        $vo = ValidatorObject::fromArray(['key' => 'val', 'num' => 7]);

        $this->assertSame('val', $vo->key);
        $this->assertSame(7, $vo->num);
    }

    #[Test]
    public function magicSetAndGet(): void
    {
        $vo = new ValidatorObject();
        $vo->foo = 'bar';

        $this->assertSame('bar', $vo->foo);
    }

    #[Test]
    public function magicGetReturnsNullForMissing(): void
    {
        $vo = new ValidatorObject();

        $this->assertNull($vo->nonexistent);
    }

    #[Test]
    public function issetReturnsTrueForExisting(): void
    {
        $vo = new ValidatorObject();
        $vo->key = 'value';

        $this->assertTrue(isset($vo->key));
        $this->assertFalse(isset($vo->missing));
    }

    #[Test]
    public function arrayAccessWorks(): void
    {
        $vo = new ValidatorObject();
        $vo['item'] = 'data';

        $this->assertSame('data', $vo['item']);
    }
}
