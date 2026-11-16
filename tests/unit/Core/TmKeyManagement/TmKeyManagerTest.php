<?php

namespace Matecat\Core\TmKeyManagement;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\CapturesNamedLogs;
use Model\DataAccess\IDatabase;
use PDOException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use Utils\TmKeyManagement\Filter;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use PHPUnit\Framework\Attributes\Test;

class TmKeyManagerTest extends AbstractTest
{
    use CapturesNamedLogs;

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

    #[Test]
    public function testGetOwnerJobTmKeysReturnsOnlyOwnerKeysWithTheGrant()
    {
        $tmKeys = json_encode([
            ['tm' => true, 'glos' => true, 'owner' => true, 'uid_transl' => null, 'uid_rev' => null, 'key' => 'owner_rw', 'r' => true, 'w' => true, 'r_transl' => null, 'w_transl' => null, 'r_rev' => null, 'w_rev' => null],
            ['tm' => true, 'glos' => true, 'owner' => true, 'uid_transl' => null, 'uid_rev' => null, 'key' => 'owner_r', 'r' => true, 'w' => false, 'r_transl' => null, 'w_transl' => null, 'r_rev' => null, 'w_rev' => null],
            ['tm' => true, 'glos' => true, 'owner' => false, 'uid_transl' => 42, 'uid_rev' => null, 'key' => 'translator', 'r' => false, 'w' => false, 'r_transl' => true, 'w_transl' => true, 'r_rev' => null, 'w_rev' => null],
            ['tm' => false, 'glos' => true, 'owner' => true, 'uid_transl' => null, 'uid_rev' => null, 'key' => 'owner_glossary', 'r' => true, 'w' => true, 'r_transl' => null, 'w_transl' => null, 'r_rev' => null, 'w_rev' => null],
        ]);

        $this->assertSame(['owner_rw', 'owner_r'], TmKeyManager::getOwnerJobTmKeys($tmKeys, 'r'));
        $this->assertSame(['owner_rw'], TmKeyManager::getOwnerJobTmKeys($tmKeys, 'w'));
        $this->assertSame([], TmKeyManager::getOwnerJobTmKeys('[]', 'r'));
    }

    /**
     * A string that is not valid JSON is logged and refused with code -2.
     *
     * @throws Exception
     */
    #[Test]
    public function getOwnerKeysLogsAndThrowsOnInvalidJson(): void
    {
        $log = $this->captureLog('engines');

        // The code throws a plain Exception, which is also the parent of PHPUnit's own failures,
        // so a fail() inside the try would be caught here. Keep the exception and assert after.
        $thrown = null;
        try {
            TmKeyManager::getOwnerKeys(['{not json']);
        } catch (Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'An invalid JSON string must be refused.');
        $this->assertSame(-2, $thrown->getCode());
        $this->assertTrue($log->hasErrorThatContains('Invalid JSON.'));
    }

    /**
     * A new client key is still merged into the job when it cannot be saved to the user's
     * keyring: the error is logged and the merge goes on.
     *
     * @throws Exception
     * @throws MockObjectException
     */
    #[Test]
    public function mergeJsonKeysLogsTheErrorAndKeepsTheNewKeyWhenTheKeyringCannotBeRead(): void
    {
        $log = $this->captureLog('engines');

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willThrowException(new PDOException('Keyring read failed'));

        $merged = TmKeyManager::mergeJsonKeys(
            '[{"key":"0123456789abcdef0123","name":"New key","r":true,"w":true,"tm":true,"glos":true}]',
            '[]',
            $database,
            Filter::OWNER,
            9_937_001
        );

        $this->assertTrue($log->hasErrorThatContains('Keyring read failed'));
        $this->assertCount(1, $merged);
        $this->assertSame('0123456789abcdef0123', $merged[0]->key);
        $this->assertTrue($merged[0]->owner);
    }
}
