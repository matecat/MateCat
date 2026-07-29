<?php

namespace Matecat\Core\TmKeyManagement;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;

class TmKeyManagerTest extends AbstractTest
{
    #[Test]
    public function testSanitizePreservesCurlyBraces()
    {
        $obj = new TmKeyStruct();
        $obj->name = 'New resource created for project {{pid}}';

        TmKeyManager::sanitize($obj);

        $this->assertEquals('New resource created for project {{pid}}', $obj->name);
    }

    /**
     * Names are stored raw: escaping for HTML/XML/email is the output layer's
     * job, so sanitize() must keep every printable character byte-identical.
     *
     * @return array<string, array{0: string}>
     */
    public static function rawNameProvider(): array
    {
        return [
            'script tag'   => ['Resource with <script>alert(1)</script> and {{pid}}'],
            'ampersand'    => ['R&D — Client (2024)'],
            'double quote' => ['The "official" glossary'],
            'single quote' => ["L'été"],
            'accents'      => ['Memoria è rotta'],
            'emoji'        => ['Fruit glossary 🍎'],
            'emoji zwj'    => ["Family \u{1F469}\u{200D}\u{1F469}\u{200D}\u{1F466} glossary"],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    #[DataProvider('rawNameProvider')]
    public function testSanitizeStoresNamesByteIdentical(string $name)
    {
        $obj = new TmKeyStruct();
        $obj->name = $name;

        TmKeyManager::sanitize($obj);

        $this->assertSame($name, $obj->name);
    }

    /**
     * Guards the raw-storage invariant: no HTML entity may ever be produced
     * by the input layer.
     *
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testSanitizeNeverProducesHtmlEntities()
    {
        $obj = new TmKeyStruct();
        $obj->name = '<b>R&D</b> "quoted" \'single\'';

        TmKeyManager::sanitize($obj);

        $this->assertDoesNotMatchRegularExpression('/&(lt|gt|amp|quot|#0?39);/', $obj->name);
    }

    #[Test]
    public function testValidateNameRejectsInvalidUtf8()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        TmKeyManager::validateName("Memoria \xC3 rotta");
    }

    #[Test]
    public function testValidateNameRejectsNonStringInput()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        TmKeyManager::validateName(['not', 'a', 'string']);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testValidateNameAllowsNull()
    {
        $this->assertNull(TmKeyManager::validateName(null));
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testValidateNameStripsControlCharacters()
    {
        $this->assertSame('abcd', TmKeyManager::validateName("a\x00b\x1bc\nd"));
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testValidateNameStripsZeroWidthAndBidiCharacters()
    {
        $this->assertSame(
            'evilname',
            TmKeyManager::validateName("evil\u{200B}\u{202E}name")
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testValidateNameNormalizesToNfcAndTrims()
    {
        // "è" as combining sequence (U+0065 U+0300) must become the composed U+00E8
        $this->assertSame(
            "Memoria \u{00E8} rotta",
            TmKeyManager::validateName("  Memoria e\u{0300} rotta  ")
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testValidateNameCapsLengthAt255Characters()
    {
        $result = TmKeyManager::validateName(str_repeat('à', 300));

        $this->assertSame(255, mb_strlen($result));
    }
}
