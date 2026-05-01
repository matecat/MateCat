<?php

use Model\DataAccess\Database;
use Model\OwnerFeatures\OwnerFeatureDao;
use Model\OwnerFeatures\OwnerFeatureStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


#[Group('PersistenceNeeded')]
class OwnerFeatureDaoTest extends AbstractTest
{
    protected Database $database;
    protected \Predis\Client $flusher;
    protected int $userId = 0;
    protected string $userEmail = 'ownerfeature-dao-test@matecat-phpunit.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        $this->flusher = new Predis\Client(AppConfig::$REDIS_SERVERS);
        $this->flusher->select(AppConfig::$INSTANCE_ID);
        $this->flusher->flushdb();

        $this->database->getConnection()->exec(
            "DELETE FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE email = '{$this->userEmail}'"
        );

        $this->database->getConnection()->exec(
            "INSERT INTO " . AppConfig::$DB_DATABASE . ".`users` " .
            "(uid, email, salt, pass, create_date, first_name, last_name) " .
            "VALUES (NULL, '{$this->userEmail}', 'testsalt', 'testpass', NOW(), 'Owner', 'Tester')"
        );
        $this->userId = (int)$this->database->last_insert();
    }

    protected function tearDown(): void
    {
        if ($this->userId > 0) {
            $stmt = $this->database->getConnection()->prepare(
                "DELETE FROM owner_features WHERE uid = ?"
            );
            $stmt->execute([$this->userId]);
        }

        $this->database->getConnection()->exec(
            "DELETE FROM " . AppConfig::$DB_DATABASE . ".`users` WHERE email = '{$this->userEmail}'"
        );

        $this->flusher->flushdb();
        parent::tearDown();
    }

    private function insertFixtureFeature(string $featureCode, bool $enabled = true): int
    {
        $stmt = $this->database->getConnection()->prepare(
            "INSERT INTO owner_features (uid, feature_code, options, create_date, last_update, enabled, id_team) " .
            "VALUES (?, ?, NULL, NOW(), NOW(), ?, NULL)"
        );
        $stmt->execute([$this->userId, $featureCode, $enabled ? 1 : 0]);

        return (int)$this->database->getConnection()->lastInsertId();
    }

    /**
     * @covers OwnerFeatureDao::create
     */
    #[Test]
    public function test_create_inserts_record_and_returns_struct(): void
    {
        $struct = new OwnerFeatureStruct();
        $struct->uid          = $this->userId;
        $struct->feature_code = 'create_test_feature';
        $struct->options      = null;
        $struct->enabled      = true;
        $struct->id_team      = null;
        $struct->last_update  = null;
        $struct->create_date  = null;

        $dao    = new OwnerFeatureDao($this->database);
        $result = $dao->create($struct);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OwnerFeatureStruct::class, $result);
        $this->assertEquals($this->userId, (int)$result->uid);
        $this->assertEquals('create_test_feature', $result->feature_code);
        $this->assertTrue((bool)$result->enabled);
        $this->assertGreaterThan(0, (int)$result->id);
        $this->assertNotEmpty($result->create_date);
        $this->assertNotEmpty($result->last_update);
    }

    /**
     * @covers OwnerFeatureDao::getByIdCustomer
     */
    #[Test]
    public function test_get_by_id_customer_returns_enabled_features_only(): void
    {
        $this->insertFixtureFeature('enabled_feature', true);
        $this->insertFixtureFeature('disabled_feature', false);

        $results = OwnerFeatureDao::getByIdCustomer($this->userEmail, 0); // ttl=0 bypasses cache

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(OwnerFeatureStruct::class, $results[0]);
        $this->assertEquals('enabled_feature', $results[0]->feature_code);
        $this->assertEquals($this->userId, (int)$results[0]->uid);
    }

    /**
     * @covers OwnerFeatureDao::getByIdCustomer
     */
    #[Test]
    public function test_get_by_id_customer_with_unknown_email_returns_empty_array(): void
    {
        $results = OwnerFeatureDao::getByIdCustomer('no-such-user@matecat-phpunit.test', 0);
        $this->assertSame([], $results);
    }

    /**
     * @covers OwnerFeatureDao::destroyCacheByIdCustomer
     */
    #[Test]
    public function test_destroy_cache_by_id_customer_returns_bool(): void
    {
        $this->insertFixtureFeature('cache_feature_email', true);
        OwnerFeatureDao::getByIdCustomer($this->userEmail, 3600); // prime cache

        $result = OwnerFeatureDao::destroyCacheByIdCustomer($this->userEmail);

        $this->assertIsBool($result);
    }

    /**
     * @covers OwnerFeatureDao::getByUserId
     */
    #[Test]
    public function test_get_by_user_id_returns_all_features_for_user(): void
    {
        $this->insertFixtureFeature('feature_alpha', true);
        $this->insertFixtureFeature('feature_beta', false);

        $results = OwnerFeatureDao::getByUserId($this->userId, 0); // ttl=0 bypasses cache

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        $codes = array_map(static fn(OwnerFeatureStruct $s) => $s->feature_code, $results);
        $this->assertContains('feature_alpha', $codes);
        $this->assertContains('feature_beta', $codes);
    }

    /**
     * @covers OwnerFeatureDao::getByUserId
     */
    #[Test]
    public function test_get_by_user_id_with_null_uid_returns_empty_array(): void
    {
        $results = OwnerFeatureDao::getByUserId(null);
        $this->assertSame([], $results);
    }

    /**
     * @covers OwnerFeatureDao::destroyCacheByUserId
     */
    #[Test]
    public function test_destroy_cache_by_user_id_returns_bool(): void
    {
        $this->insertFixtureFeature('uid_cache_feature', true);
        OwnerFeatureDao::getByUserId($this->userId, 3600); // prime cache

        $result = OwnerFeatureDao::destroyCacheByUserId($this->userId);

        $this->assertIsBool($result);
    }

    /**
     * @covers OwnerFeatureDao::getById
     */
    #[Test]
    public function test_get_by_id_returns_correct_struct(): void
    {
        $id = $this->insertFixtureFeature('get_by_id_feature', true);

        $result = OwnerFeatureDao::getById($id);

        $this->assertNotNull($result);
        $this->assertInstanceOf(OwnerFeatureStruct::class, $result);
        $this->assertEquals($id, (int)$result->id);
        $this->assertEquals('get_by_id_feature', $result->feature_code);
        $this->assertEquals($this->userId, (int)$result->uid);
        $this->assertTrue((bool)$result->enabled);
        $this->assertNull($result->id_team);
    }

    /**
     * @covers OwnerFeatureDao::getById
     */
    #[Test]
    public function test_get_by_id_returns_null_for_nonexistent_record(): void
    {
        $result = OwnerFeatureDao::getById(PHP_INT_MAX);
        $this->assertNull($result);
    }
}
