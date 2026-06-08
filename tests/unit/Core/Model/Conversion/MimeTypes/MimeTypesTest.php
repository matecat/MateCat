<?php

namespace Matecat\Core\Model\Conversion\MimeTypes;

use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\MimeTypes\MimeTypes;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for {@see MimeTypes}.
 */
class MimeTypesTest extends AbstractTest
{
    private MimeTypes $mimeTypes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mimeTypes = new MimeTypes();
    }

    #[Test]
    public function getExtensions_returns_known_extensions_for_pdf_mime_type(): void
    {
        $extensions = $this->mimeTypes->getExtensions('application/acrobat');
        $this->assertContains('pdf', $extensions);
    }

    #[Test]
    public function getExtensions_is_case_insensitive(): void
    {
        $lower = $this->mimeTypes->getExtensions('application/acrobat');
        $upper = $this->mimeTypes->getExtensions('Application/Acrobat');
        $this->assertEquals($lower, $upper);
    }

    #[Test]
    public function getExtensions_returns_empty_array_for_unknown_mime_type(): void
    {
        $extensions = $this->mimeTypes->getExtensions('application/totally-unknown-type-xyz');
        $this->assertSame([], $extensions);
    }

    #[Test]
    public function getMimeTypes_returns_mime_types_for_pdf_extension(): void
    {
        $mimeTypes = $this->mimeTypes->getMimeTypes('pdf');
        $this->assertNotEmpty($mimeTypes);
        $this->assertContainsOnlyString($mimeTypes);
    }

    #[Test]
    public function getMimeTypes_is_case_insensitive(): void
    {
        $lower = $this->mimeTypes->getMimeTypes('pdf');
        $upper = $this->mimeTypes->getMimeTypes('PDF');
        $this->assertEquals($lower, $upper);
    }

    #[Test]
    public function getMimeTypes_returns_empty_array_for_unknown_extension(): void
    {
        $mimeTypes = $this->mimeTypes->getMimeTypes('totally_unknown_ext_xyz');
        $this->assertSame([], $mimeTypes);
    }

    #[Test]
    public function getDefault_returns_singleton_instance(): void
    {
        $a = MimeTypes::getDefault();
        $b = MimeTypes::getDefault();
        $this->assertSame($a, $b);
    }

    #[Test]
    public function setDefault_replaces_the_singleton(): void
    {
        $custom = new MimeTypes(['text/x-custom' => ['cst']]);
        MimeTypes::setDefault($custom);
        $this->assertSame($custom, MimeTypes::getDefault());

        // Restore default so other tests are not affected
        MimeTypes::setDefault(new MimeTypes());
    }

    #[Test]
    public function constructor_custom_map_is_returned_by_getExtensions(): void
    {
        $custom = new MimeTypes(['text/x-custom' => ['cst', 'custom']]);
        $extensions = $custom->getExtensions('text/x-custom');
        $this->assertSame(['cst', 'custom'], $extensions);
    }

    #[Test]
    public function constructor_custom_map_is_returned_by_getMimeTypes(): void
    {
        $custom = new MimeTypes(['text/x-custom' => ['cst']]);
        $mimeTypes = $custom->getMimeTypes('cst');
        $this->assertContains('text/x-custom', $mimeTypes);
    }

    #[Test]
    public function guessMimeType_throws_logic_exception_for_non_existent_file(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unable to guess the MIME type. File not found.');

        // Only run if at least one guesser is supported
        if (!$this->mimeTypes->isGuesserSupported()) {
            $this->markTestSkipped('No MIME type guessers are supported in this environment.');
        }

        $this->mimeTypes->guessMimeType('/tmp/this_file_does_not_exist_xyz_' . uniqid() . '.txt');
    }
}
