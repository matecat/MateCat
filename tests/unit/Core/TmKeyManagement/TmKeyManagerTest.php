<?php

namespace Matecat\Core\TmKeyManagement;

use Matecat\TestHelpers\AbstractTest;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use PHPUnit\Framework\Attributes\Test;

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
     * The inverse of what this used to assert.
     *
     * The allowlist `[^.\-_\p{L}\p{N}\s{}]+` used to delete anything outside it, so markup in a
     * resource name was flattened here. That was the wrong place for it: deleting characters on the
     * way in also cost `Smith & Sons` its ampersand and every non-Latin name its letters, while
     * doing nothing for the sinks that actually printed the value. The name is stored as typed and
     * each output escapes for its own context.
     */
    #[Test]
    public function testSanitizeKeepsTheNameAsTyped()
    {
        $obj = new TmKeyStruct();
        $obj->name = 'Resource with <script>alert(1)</script> and {{pid}}';

        TmKeyManager::sanitize($obj);

        $this->assertSame('Resource with <script>alert(1)</script> and {{pid}}', $obj->name);
    }

    #[Test]
    public function testSanitizeStripsControlCharactersFromTheName()
    {
        $obj = new TmKeyStruct();
        $obj->name = "Resource\r\nBcc: victim";

        TmKeyManager::sanitize($obj);

        $this->assertSame('Resource Bcc: victim', $obj->name);
    }

    #[Test]
    public function testSanitizeCutsTheNameToTheColumnWidth()
    {
        $obj = new TmKeyStruct();
        $obj->name = str_repeat('a', TmKeyManager::RESOURCE_NAME_MAX_LENGTH + 10);

        TmKeyManager::sanitize($obj);

        // Truncated rather than refused: this runs on the job-keys merge path, over keys the caller
        // already owns, where one over-long legacy name must not fail the whole merge.
        $this->assertSame(str_repeat('a', TmKeyManager::RESOURCE_NAME_MAX_LENGTH), $obj->name);
    }

    #[Test]
    public function testSanitizeKeepsANonLatinName()
    {
        $obj = new TmKeyStruct();
        $obj->name = 'メモリ';

        TmKeyManager::sanitize($obj);

        $this->assertSame('メモリ', $obj->name);
    }
}
