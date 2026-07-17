<?php

namespace Matecat\Core\Model\Conversion\MimeTypes\Guesser;

use InvalidArgumentException;
use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\MimeTypes\Guesser\FileinfoMimeTypeGuesser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Subclass that forces isGuesserSupported() to return false,
 * so we can test the LogicException branch without altering system state.
 */
class UnsupportedFileinfoMimeTypeGuesser extends FileinfoMimeTypeGuesser
{
    public function isGuesserSupported(): bool
    {
        return false;
    }
}

class FileinfoMimeTypeGuesserTest extends AbstractTest
{
    // ──────────────────────────────────────────────────────────────────
    // isGuesserSupported()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function isGuesserSupported_returns_true_when_finfo_available(): void
    {
        $guesser = new FileinfoMimeTypeGuesser();
        $this->assertTrue($guesser->isGuesserSupported());
    }

    // ──────────────────────────────────────────────────────────────────
    // guessMimeType() — exception paths
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function guessMimeType_throws_for_nonexistent_file(): void
    {
        $guesser = new FileinfoMimeTypeGuesser();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable');
        $guesser->guessMimeType('/tmp/this-file-does-not-exist-' . uniqid() . '.bin');
    }

    #[Test]
    public function guessMimeType_throws_logic_exception_when_not_supported(): void
    {
        $guesser = new UnsupportedFileinfoMimeTypeGuesser();

        $tmpFile = tempnam(sys_get_temp_dir(), 'phpunit_finfo_');
        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('guesser is not supported');
            $guesser->guessMimeType($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // guessMimeType() — happy path with real file
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function guessMimeType_returns_string_for_readable_file(): void
    {
        $guesser = new FileinfoMimeTypeGuesser();
        $tmpFile = tempnam(sys_get_temp_dir(), 'phpunit_finfo_');
        file_put_contents($tmpFile, 'hello world');

        try {
            $result = $guesser->guessMimeType($tmpFile);
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function guessMimeType_with_no_magic_file_uses_default(): void
    {
        $guesser = new FileinfoMimeTypeGuesser(null);
        $tmpFile = tempnam(sys_get_temp_dir(), 'phpunit_finfo_');
        file_put_contents($tmpFile, '<?php echo "test";');

        try {
            $result = $guesser->guessMimeType($tmpFile);
            $this->assertIsString($result);
        } finally {
            @unlink($tmpFile);
        }
    }
}
