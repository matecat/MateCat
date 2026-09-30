<?php

namespace Matecat\Core\Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\V2\UserController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\DataAccess\IDatabase;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class TestableV2UserController extends UserController
{
    public function __construct()
    {
    }

    public function initWith(Request $request, Response $response, UserStruct $user, IDatabase $database): void
    {
        $ref = new ReflectionClass(KleinController::class);
        $ref->getProperty('request')->setValue($this, $request);
        $ref->getProperty('response')->setValue($this, $response);
        $ref->getProperty('user')->setValue($this, $user);
        $ref->getProperty('userIsLogged')->setValue($this, true);
        $ref->getProperty('database')->setValue($this, $database);
    }
}

/**
 * A user row is cached under two addresses, uid and email. The rename endpoint has to drop both:
 * an email entry left standing answers every login lookup with the previous name for the rest of
 * its TTL. Pinned here rather than on the DAO because it is the endpoint, not the door, that decides
 * whether the door is reached at all.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class UserControllerCacheEvictionTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(['users']);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    #[Test]
    public function editEvictsTheEmailKeyAndNotOnlyTheUidKey(): void
    {
        $made = $this->fixtures->makeUser();

        $reader = new UserDao($this->realSqlDb());
        $user = $reader->getByUid($made['uid']);
        $this->assertInstanceOf(UserStruct::class, $user);

        $reader->setCacheTTL(60);
        $reader->getByUid($made['uid']);
        $reader->getByEmail($made['email']);

        $controller = new TestableV2UserController();
        $controller->initWith(
            new Request([], [], [], [], [], json_encode(['first_name' => 'Renamed', 'last_name' => 'Person'])),
            new Response(),
            $user,
            $this->realSqlDb()
        );
        $controller->edit();

        $this->assertSame(
            'Renamed',
            $reader->getByUid($made['uid'])?->first_name,
            'the uid entry addresses the renamed row'
        );
        $this->assertSame(
            'Renamed',
            $reader->getByEmail($made['email'])?->first_name,
            'the email entry addresses the same row and has to go with it'
        );

        $reader->setCacheTTL(0);
    }
}
