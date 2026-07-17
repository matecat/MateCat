<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\MetadataStruct;
use PHPUnit\Framework\Attributes\Test;

class MetadataStructTest extends AbstractTest
{
    #[Test]
    public function getValueReturnsIntForIntegerString(): void
    {
        $struct = new MetadataStruct();
        $struct->value = '42';

        $this->assertSame(42, $struct->getValue());
    }

    #[Test]
    public function getValueReturnsFloatForDecimalString(): void
    {
        $struct = new MetadataStruct();
        $struct->value = '3.14';

        $this->assertSame(3.14, $struct->getValue());
    }

    #[Test]
    public function getValueReturnsStringForNonNumeric(): void
    {
        $struct = new MetadataStruct();
        $struct->value = 'hello';

        $this->assertSame('hello', $struct->getValue());
    }

    #[Test]
    public function getValueReturnsObjectForArray(): void
    {
        $struct = new MetadataStruct();
        $struct->value = ['key' => 'val'];

        $result = $struct->getValue();
        $this->assertIsObject($result);
        $this->assertSame('val', $result->key);
    }

    #[Test]
    public function getValueReturnsObjectForSerialisedData(): void
    {
        $struct = new MetadataStruct();
        $struct->value = serialize(['a' => 1]);

        $result = $struct->getValue();
        $this->assertIsObject($result);
        $this->assertSame(1, $result->a);
    }

    #[Test]
    public function getValueReturnsStringForNonSerialisedLookalike(): void
    {
        $struct = new MetadataStruct();
        $struct->value = 'x';

        $this->assertSame('x', $struct->getValue());
    }

    #[Test]
    public function jsonSerializeReturnsExpectedShape(): void
    {
        $struct = new MetadataStruct();
        $struct->id = '1';
        $struct->uid = '10';
        $struct->key = 'theme';
        $struct->value = 'dark';

        $result = $struct->jsonSerialize();

        $this->assertSame(1, $result['id']);
        $this->assertSame(10, $result['uid']);
        $this->assertSame('theme', $result['key']);
        $this->assertSame('dark', $result['value']);
    }

    #[Test]
    public function looksSerialised_shortString(): void
    {
        $struct = new MetadataStruct();
        $struct->value = 'N;';

        $result = $struct->getValue();
        $this->assertIsObject($result);
    }
}
