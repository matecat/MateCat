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

    #[Test]
    public function testSanitizeRemovesOtherSpecialChars()
    {
        $obj = new TmKeyStruct();
        $obj->name = 'Resource with <script>alert(1)</script> and {{pid}}';

        TmKeyManager::sanitize($obj);

        // htmlspecialchars encodes <, >, &, ", ' instead of stripping them,
        // so the {{pid}} placeholder is left untouched (braces aren't escaped)
        // while the script tag becomes harmless encoded text.

        $this->assertSame('Resource with &lt;script&gt;alert(1)&lt;/script&gt; and {{pid}}', $obj->name);
    }
}
