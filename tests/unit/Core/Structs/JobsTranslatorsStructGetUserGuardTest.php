<?php

namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * RED→GREEN guard test for JobsTranslatorsStruct::getUser singleton removal (T1b).
 *
 * Written BEFORE the implementation change (TDD strict RED step).
 * After T1b: getUser(UserDao $dao) — injected DAO used, singleton never touched.
 */
class JobsTranslatorsStructGetUserGuardTest extends AbstractTest
{
    private JobsTranslatorsStruct $struct;

    public function setUp(): void
    {
        parent::setUp();

        $this->struct = new JobsTranslatorsStruct();
        $this->struct->id_job = 100;
        $this->struct->job_password = 'pwd123';
        $this->struct->email = 'translator@example.com';
        $this->struct->id_translator_profile = 42;
        $this->struct->added_by = 1;
        $this->struct->delivery_date = '2026-06-15 10:00:00';
        $this->struct->job_owner_timezone = 0;
        $this->struct->source = 'en-US';
        $this->struct->target = 'it-IT';
    }

    /**
     * getUser must use the injected UserDao, never the singleton.
     *
     * Before T1b: getUser() calls `new UserDao()` which hits Database::obtain() → poison fails.
     * After T1b: getUser(UserDao $dao) uses $dao directly → singleton never touched → GREEN.
     */
    #[Test]
    public function getUser_uses_injected_dao_not_singleton(): void
    {
        $expectedUser = new UserStruct();
        $expectedUser->uid = 42;
        $expectedUser->email = 'translator@example.com';

        $mockDao = $this->createMock(UserDao::class);
        $mockDao->method('setCacheTTL')->willReturnSelf();
        $mockDao->expects($this->once())
            ->method('getByEmail')
            ->with('translator@example.com')
            ->willReturn($expectedUser);

        // Poison singleton — must never be touched after T1b
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        // After T1b: getUser(UserDao $dao) — mandatory param
        $result = $this->struct->getUser($mockDao);

        $this->assertSame($expectedUser, $result);
    }

    /**
     * getUser returns null when id_translator_profile is empty,
     * and the injected DAO is never called.
     */
    #[Test]
    public function getUser_returns_null_when_no_profile(): void
    {
        $this->struct->id_translator_profile = null;

        $mockDao = $this->createMock(UserDao::class);
        $mockDao->expects($this->never())->method('getByEmail');

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $result = $this->struct->getUser($mockDao);

        $this->assertNull($result);
    }
}
