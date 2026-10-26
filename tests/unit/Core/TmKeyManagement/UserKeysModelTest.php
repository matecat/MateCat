<?php

namespace Matecat\Core\TmKeyManagement;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\CapturesNamedLogs;
use Model\DataAccess\IDatabase;
use Model\TmKeyManagement\UserKeysModel;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use TypeError;

class UserKeysModelTest extends AbstractTest
{
    use CapturesNamedLogs;

    /**
     * When the user's keyring cannot be read, getKeys() logs the error and goes on with an empty
     * keyring: the job keys are still returned, masked, as for a user with no keys.
     *
     * @throws Exception
     * @throws MockObjectException
     * @throws TypeError
     */
    #[Test]
    public function getKeysLogsTheErrorAndReturnsTheJobKeysWhenTheKeyringCannotBeRead(): void
    {
        $log = $this->captureLog('engines');

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willThrowException(new PDOException('Keyring read failed'));

        $model = new UserKeysModel(new UserStruct(['uid' => 9_937_101]), $database);

        $keys = $model->getKeys('[{"key":"0123456789abcdef0123","name":"Job key","r":true,"w":true,"owner":true}]');

        $this->assertTrue($log->hasErrorThatContains('Keyring read failed'));
        $this->assertSame([], $keys['totals']);
        $this->assertCount(1, $keys['job_keys']);
        $this->assertFalse($keys['job_keys'][0]->owner);
        // Only the last characters stay readable.
        $this->assertSame('***************f0123', $keys['job_keys'][0]->key);
    }
}
