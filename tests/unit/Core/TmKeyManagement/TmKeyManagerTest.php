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
        
        // < and > are removed or encoded by filter_var depending on implementation, 
        // but preg_replace should strip them if they are not in the allowed list.
        // In the updated code: [^.\-_\p{L}\p{N}\s{}]+
        // <, >, (, ) are NOT in the allowed list.
        
        $this->assertStringContainsString('{{pid}}', $obj->name);
        $this->assertStringNotContainsString('<script>', $obj->name);
    }
}
