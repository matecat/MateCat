<?php

declare(strict_types=1);

namespace Matecat\Core\Model\Conversion\MimeTypes\Guesser;

use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\MimeTypes\Guesser\SimpleMarkupMimeTypeGuesser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use RuntimeException;

#[Group('unit')]
class SimpleMarkupMimeTypeGuesserTest extends AbstractTest
{
    /**
     * Writes $content to a temp file and hands the path to the callback, then removes it.
     *
     * @param callable(string):void $assert
     */
    private function withTempFile(string $content, callable $assert): void
    {
        $path = tempnam(sys_get_temp_dir(), 'phpunit_simple_markup_');
        file_put_contents($path, $content);

        try {
            $assert($path);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function isGuesserSupported_always_returns_true(): void
    {
        $this->assertTrue((new SimpleMarkupMimeTypeGuesser())->isGuesserSupported());
    }

    /**
     * fopen() on a missing path raises an E_WARNING before the throw; suppressing
     * PHPUnit's error handler keeps that noise out of the report.
     */
    #[Test]
    #[WithoutErrorHandler]
    public function guessMimeType_throws_when_the_path_cannot_be_opened(): void
    {
        $guesser = new SimpleMarkupMimeTypeGuesser();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not open XML input');

        @$guesser->guessMimeType('/tmp/this-file-does-not-exist-' . uniqid() . '.xml');
    }

    #[Test]
    public function guessMimeType_detects_html(): void
    {
        $this->withTempFile('<!DOCTYPE html><html><body>hi</body></html>', function (string $path): void {
            $this->assertSame('text/html', (new SimpleMarkupMimeTypeGuesser())->guessMimeType($path));
        });
    }

    #[Test]
    public function guessMimeType_detects_xliff(): void
    {
        $content = '<?xml version="1.0"?><xliff version="1.2"><file></file></xliff>';

        $this->withTempFile($content, function (string $path): void {
            $this->assertSame('application/x-xliff', (new SimpleMarkupMimeTypeGuesser())->guessMimeType($path));
        });
    }

    #[Test]
    public function guessMimeType_detects_plain_xml(): void
    {
        $this->withTempFile('<?xml version="1.0"?><root></root>', function (string $path): void {
            $this->assertSame('text/xml', (new SimpleMarkupMimeTypeGuesser())->guessMimeType($path));
        });
    }

    #[Test]
    public function guessMimeType_detects_tmx(): void
    {
        $content = '<?xml version="1.0"?><tmx version="1.4"><body></body></tmx>';

        $this->withTempFile($content, function (string $path): void {
            $this->assertSame('text/xml', (new SimpleMarkupMimeTypeGuesser())->guessMimeType($path));
        });
    }

    #[Test]
    public function guessMimeType_returns_null_for_unrecognised_markup(): void
    {
        $this->withTempFile('just some plain text', function (string $path): void {
            $this->assertNull((new SimpleMarkupMimeTypeGuesser())->guessMimeType($path));
        });
    }
}
