<?php

namespace unit\Model\Projects;

use Exception;
use Model\DataAccess\Database;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ProjectTemplateDaoTest extends AbstractTest
{
    private const int TEST_UID = 1886428310;
    private const int PERSONAL_TEAM_ID = 991001;
    private const int SHARED_TEAM_ID = 991002;

    protected function setUp(): void
    {
        parent::setUp();

        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS project_templates (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                uid BIGINT(20) NOT NULL,
                id_team INT(11) NOT NULL,
                pretranslate_100 TINYINT(1) NOT NULL DEFAULT 0,
                pretranslate_101 TINYINT(1) NOT NULL DEFAULT 1,
                get_public_matches TINYINT(1) NOT NULL DEFAULT 0,
                segmentation_rule VARCHAR(255) DEFAULT NULL,
                tm TEXT,
                mt TEXT,
                payable_rate_template_id INT(11) DEFAULT 0,
                qa_model_template_id INT(11) DEFAULT 0,
                filters_template_id INT(11) DEFAULT 0,
                xliff_config_template_id INT(11) DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                modified_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                subject VARCHAR(255) DEFAULT NULL,
                source_language VARCHAR(45) DEFAULT NULL,
                target_language VARCHAR(2048) DEFAULT NULL,
                subfiltering_handlers TEXT,
                character_counter_count_tags TINYINT(1) NOT NULL DEFAULT 0,
                character_counter_mode VARCHAR(255) DEFAULT NULL,
                mt_quality_value_in_editor INT(11) DEFAULT NULL,
                icu_enabled TINYINT(1) NOT NULL DEFAULT 0,
                tm_prioritization TINYINT(1) NOT NULL DEFAULT 0,
                dialect_strict TINYINT(1) NOT NULL DEFAULT 0,
                public_tm_penalty INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uid_name_idx (uid, name),
                KEY uid_idx (uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $conn->beginTransaction();

        $conn->exec(
            "INSERT IGNORE INTO users (uid, email, salt, pass, create_date, first_name, last_name)
             VALUES (" . self::TEST_UID . ", 'domenico@translated.net', 'x', 'x', '2024-01-01 00:00:00', 'Domenico', 'Lupinetti')"
        );

        $conn->exec(
            "INSERT IGNORE INTO teams (id, name, created_by, type)
             VALUES (" . self::PERSONAL_TEAM_ID . ", 'Personal Team', " . self::TEST_UID . ", 'personal')"
        );

        $conn->exec(
            "INSERT IGNORE INTO teams (id, name, created_by, type)
             VALUES (" . self::SHARED_TEAM_ID . ", 'Shared Team', " . self::TEST_UID . ", 'general')"
        );

        $conn->exec(
            "INSERT IGNORE INTO teams_users (id_team, uid, is_admin)
             VALUES (" . self::PERSONAL_TEAM_ID . ", " . self::TEST_UID . ", 1)"
        );

        $conn->exec(
            "INSERT IGNORE INTO teams_users (id_team, uid, is_admin)
             VALUES (" . self::SHARED_TEAM_ID . ", " . self::TEST_UID . ", 1)"
        );
    }

    protected function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function getDefaultTemplateReturnsExpectedDefaults(): void
    {
        $default = ProjectTemplateDao::getDefaultTemplate(self::TEST_UID);

        $this->assertSame(0, $default->id);
        $this->assertSame('Matecat original settings', $default->name);
        $this->assertTrue($default->is_default);
        $this->assertSame(self::PERSONAL_TEAM_ID, $default->id_team);
        $this->assertSame(self::TEST_UID, $default->uid);
        $this->assertFalse($default->pretranslate_100);
        $this->assertTrue($default->pretranslate_101);
        $this->assertTrue($default->get_public_matches);
        $this->assertSame('en-US', $default->source_language);
        $this->assertSame('general', $default->subject);
        $this->assertSame(['fr-FR'], $default->getTargetLanguage());
        $this->assertSame(1, $default->getMt()->id);
    }

    #[Test]
    public function saveGetUpdateGetRemoveLifecycleWorks(): void
    {
        $struct = $this->makeStruct('lifecycle-template');

        $saved = ProjectTemplateDao::save($struct);
        $this->assertGreaterThan(0, $saved->id);

        $byId = ProjectTemplateDao::getById($saved->id);
        $this->assertNotNull($byId);
        $this->assertStringStartsWith('lifecycle-template-', $byId->name);

        $byIdAndUser = ProjectTemplateDao::getByIdAndUser($saved->id, self::TEST_UID);
        $this->assertNotNull($byIdAndUser);
        $this->assertSame($saved->id, $byIdAndUser->id);
        $this->assertNull(ProjectTemplateDao::getByIdAndUser($saved->id, self::TEST_UID + 1));

        $saved->name = 'lifecycle-template-updated-' . uniqid('', true);
        $saved->pretranslate_100 = true;
        $saved->source_language = 'it-IT';
        $saved->target_language = serialize(['de-DE']);
        $saved->is_default = true;
        ProjectTemplateDao::update($saved);

        $updated = ProjectTemplateDao::getById($saved->id);
        $this->assertNotNull($updated);
        $this->assertStringStartsWith('lifecycle-template-updated-', $updated->name);
        $this->assertTrue($updated->pretranslate_100);
        $this->assertSame('it-IT', $updated->source_language);
        $this->assertSame(['de-DE'], $updated->getTargetLanguage());

        $removed = ProjectTemplateDao::remove($saved->id, self::TEST_UID);
        $this->assertSame(1, $removed);
        $this->assertNull(ProjectTemplateDao::getById($saved->id));
    }

    #[Test]
    public function getTheDefaultProjectAndMarkAsNotDefaultWork(): void
    {
        $first = $this->makeStruct('default-1');
        $first->is_default = true;
        $first = ProjectTemplateDao::save($first);

        $second = $this->makeStruct('default-2');
        $second->is_default = false;
        $second = ProjectTemplateDao::save($second);

        $default = ProjectTemplateDao::getTheDefaultProject(self::TEST_UID);
        $this->assertNotNull($default);
        $this->assertSame($first->id, $default->id);

        ProjectTemplateDao::markAsNotDefault(self::TEST_UID, $second->id);

        $reloadedFirst = ProjectTemplateDao::getById($first->id);
        $this->assertNotNull($reloadedFirst);
        $this->assertFalse($reloadedFirst->is_default);
    }

    #[Test]
    public function getAllPaginatedReturnsExpectedShape(): void
    {
        ProjectTemplateDao::save($this->makeStruct('page-1'));
        ProjectTemplateDao::save($this->makeStruct('page-2'));

        $result = ProjectTemplateDao::getAllPaginated(self::TEST_UID, '/api/templates?page=', 1, 1, 1);

        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('last_page', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(1, $result['items']);
        $this->assertInstanceOf(ProjectTemplateStruct::class, $result['items'][0]);
    }

    #[Test]
    public function removeSubTemplateByIdAndUserClearsReferencedSubtemplateField(): void
    {
        $first = $this->makeStruct('sub-template-1');
        $first->payable_rate_template_id = 42;
        $first = ProjectTemplateDao::save($first);

        $second = $this->makeStruct('sub-template-2');
        $second->payable_rate_template_id = 42;
        $second = ProjectTemplateDao::save($second);

        $affected = ProjectTemplateDao::removeSubTemplateByIdAndUser(42, self::TEST_UID, 'payable_rate_template_id');
        $this->assertSame(2, $affected);

        $firstReloaded = ProjectTemplateDao::getById($first->id);
        $secondReloaded = ProjectTemplateDao::getById($second->id);

        $this->assertNotNull($firstReloaded);
        $this->assertNotNull($secondReloaded);
        $this->assertSame(0, $firstReloaded->payable_rate_template_id);
        $this->assertSame(0, $secondReloaded->payable_rate_template_id);
    }

    #[Test]
    public function createFromJsonAndEditFromJsonPersistValues(): void
    {
        $user = $this->makeUser();
        $createPayload = $this->makePayload('create-json-template');

        $created = ProjectTemplateDao::createFromJSON($createPayload, $user);
        $this->assertGreaterThan(0, $created->id);

        $editPayload = $this->makePayload('edited-json-template');
        $editPayload->pretranslate_100 = true;
        $editPayload->target_language = ['fr-FR', 'it-IT'];

        ProjectTemplateDao::editFromJSON($created, $editPayload, $created->id, $user);

        $reloaded = ProjectTemplateDao::getById($created->id);
        $this->assertNotNull($reloaded);
        $this->assertStringStartsWith('edited-json-template-', $reloaded->name);
        $this->assertTrue($reloaded->pretranslate_100);
        $this->assertSame(['fr-FR', 'it-IT'], $reloaded->getTargetLanguage());
    }

    #[Test]
    public function createFromJsonThrowsWhenUserDoesNotBelongToTeam(): void
    {
        $user = $this->makeUser();
        $payload = $this->makePayload('invalid-team-template');
        $payload->id_team = 999999;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not belong to this group');

        ProjectTemplateDao::createFromJSON($payload, $user);
    }

    #[Test]
    public function createFromJsonThrowsForInvalidSourceLanguage(): void
    {
        $user = $this->makeUser();
        $payload = $this->makePayload('invalid-source-language-template');
        $payload->source_language = 'zz-ZZ';

        $this->expectException(Exception::class);

        ProjectTemplateDao::createFromJSON($payload, $user);
    }

    #[Test]
    public function createFromJsonThrowsForInvalidTargetLanguage(): void
    {
        $user = $this->makeUser();
        $payload = $this->makePayload('invalid-target-language-template');
        $payload->target_language = ['zz-ZZ'];

        $this->expectException(Exception::class);

        ProjectTemplateDao::createFromJSON($payload, $user);
    }

    #[Test]
    public function destroyDefaultTemplateCacheIsCallable(): void
    {
        $conn = Database::obtain()->getConnection();

        ProjectTemplateDao::destroyDefaultTemplateCache($conn, self::TEST_UID);

        $this->assertTrue(true);
    }

    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = self::TEST_UID;
        $user->email = 'domenico@translated.net';
        $user->first_name = 'Domenico';
        $user->last_name = 'Lupinetti';

        return $user;
    }

    private function makePayload(string $name): object
    {
        return (object)[
            'name' => $name . '-' . uniqid('', true),
            'id_team' => self::SHARED_TEAM_ID,
            'segmentation_rule' => (object)['name' => 'General', 'id' => 'standard'],
            'pretranslate_100' => false,
            'pretranslate_101' => true,
            'tm_prioritization' => false,
            'dialect_strict' => false,
            'public_tm_penalty' => 0,
            'get_public_matches' => true,
            'mt' => (object)[],
            'tm' => [],
            'payable_rate_template_id' => 0,
            'qa_model_template_id' => 0,
            'filters_template_id' => 0,
            'xliff_config_template_id' => 0,
            'character_counter_count_tags' => false,
            'character_counter_mode' => 'google_ads',
            'subject' => 'general',
            'subfiltering_handlers' => [],
            'source_language' => 'en-US',
            'target_language' => ['fr-FR'],
            'mt_quality_value_in_editor' => 85,
            'icu_enabled' => false,
        ];
    }

    private function makeStruct(string $name): ProjectTemplateStruct
    {
        $struct = new ProjectTemplateStruct();
        $struct->name = $name . '-' . uniqid('', true);
        $struct->is_default = false;
        $struct->uid = self::TEST_UID;
        $struct->id_team = self::SHARED_TEAM_ID;
        $struct->subfiltering_handlers = json_encode([]);
        $struct->segmentation_rule = json_encode(['name' => 'General', 'id' => 'standard']);
        $struct->tm = json_encode([]);
        $struct->mt = json_encode((object)[]);
        $struct->pretranslate_100 = false;
        $struct->pretranslate_101 = true;
        $struct->tm_prioritization = false;
        $struct->dialect_strict = false;
        $struct->get_public_matches = true;
        $struct->public_tm_penalty = 0;
        $struct->payable_rate_template_id = 0;
        $struct->qa_model_template_id = 0;
        $struct->filters_template_id = 0;
        $struct->xliff_config_template_id = 0;
        $struct->subject = 'general';
        $struct->source_language = 'en-US';
        $struct->target_language = serialize(['fr-FR']);
        $struct->character_counter_count_tags = false;
        $struct->character_counter_mode = 'google_ads';
        $struct->mt_quality_value_in_editor = 85;
        $struct->icu_enabled = false;

        return $struct;
    }
}
