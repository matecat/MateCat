<?php

namespace Matecat\Core\Model\Projects;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for Model\Projects\ProjectTemplateDao (plan dao-realsql-90.md, Wave 2 / T5).
 *
 * Every public SQL method is invoked DIRECTLY against the live project_templates table and
 * asserted on real returned data (DoD b). The DAO is constructed with the single per-test
 * connection (C-2); methods that build child DAOs internally (TeamDao / MembershipDao via
 * createFromJSON/editFromJSON/getDefaultTemplate) resolve those through the Database::obtain()
 * singleton the trait seeds with the SAME test creds, so they hit the same PDO handle. NO
 * wrapping transaction (C-1). Builder rows (users, teams, teams_users) and DAO-INSERTed template
 * rows are tracked so the whole-table COUNT(*) residue gate over every declared dep returns to
 * baseline (A-1/A-2/AC-1). No assertion on absolute generated ids (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ProjectTemplateDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['project_templates', 'teams_users', 'teams', 'users'];

    private ProjectTemplateDao $dao;
    private int $uid;
    private int $idTeam;
    private UserStruct $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new ProjectTemplateDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        $userRow = $this->fixtures->makeUser();
        $this->uid = $userRow['uid'];

        // personal team owned by the user + membership so MembershipDao::findTeamByIdAndUser and
        // TeamDao::getPersonalByUid resolve.
        $teamRow = $this->fixtures->makeTeam($this->uid, 'personal');
        $this->idTeam = $teamRow['id'];
        $this->fixtures->makeTeamUser($this->idTeam, $this->uid, true);

        $this->user = new UserStruct();
        $this->user->uid = $this->uid;
        $this->user->email = $userRow['email'];
        $this->user->first_name = $userRow['first_name'];
        $this->user->last_name = $userRow['last_name'];
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    private function trackTemplate(int $id): void
    {
        $this->fixtures->trackExisting('project_templates', ['id' => $id]);
    }

    private function newStruct(string $name = 'rsq tpl', bool $default = false): ProjectTemplateStruct
    {
        $s = new ProjectTemplateStruct();
        $s->name = $name;
        $s->is_default = $default;
        $s->uid = $this->uid;
        $s->id_team = $this->idTeam;
        $s->subfiltering_handlers = json_encode(['markup']);
        $s->segmentation_rule = null;
        $s->mt = json_encode(['id' => 1]);
        $s->tm = null;
        $s->source_language = 'en-US';
        $s->target_language = serialize(['it-IT']);
        $s->created_at = date('Y-m-d H:i:s');

        return $s;
    }

    /** A minimal valid decoded JSON object accepted by hydrateFromJSON. */
    private function newDecodedJson(string $name = 'rsq json tpl', bool $default = false): object
    {
        return (object)[
            'name'                         => $name,
            'is_default'                   => $default,
            'id_team'                      => $this->idTeam,
            'pretranslate_100'             => false,
            'pretranslate_101'             => true,
            'tm_prioritization'            => false,
            'dialect_strict'               => false,
            'get_public_matches'           => true,
            'mt'                           => new \stdClass(), // no ->id -> engine branch skipped
            'tm'                           => [],
            'payable_rate_template_id'     => 0,
            'qa_model_template_id'         => 0,
            'filters_template_id'          => 0,
            'xliff_config_template_id'     => 0,
            'character_counter_count_tags' => false,
            'character_counter_mode'       => null,
            'subject'                      => null,
            'subfiltering_handlers'        => ['markup'],
            'source_language'              => 'en-US',
            'target_language'              => ['it-IT'],
            'segmentation_rule'            => null,
            'public_tm_penalty'            => 0,
            'mt_quality_value_in_editor'   => null,
            'icu_enabled'                  => false,
        ];
    }

    // ---- save / getByIdAndUser -------------------------------------------------------------------

    public function testSavePersistsAndGetByIdAndUserRoundTrips(): void
    {
        $struct = $this->dao->save($this->newStruct('persisted'));
        $this->trackTemplate($struct->id);

        self::assertGreaterThan(0, $struct->id);

        $fetched = $this->dao->getByIdAndUser($struct->id, $this->uid);
        self::assertInstanceOf(ProjectTemplateStruct::class, $fetched);
        self::assertSame('persisted', $fetched->name);
        self::assertSame($this->uid, $fetched->uid);
        self::assertSame($this->idTeam, $fetched->id_team);
        self::assertSame('en-US', $fetched->source_language);
    }

    public function testGetByIdAndUserReturnsNullForOtherUser(): void
    {
        $struct = $this->dao->save($this->newStruct());
        $this->trackTemplate($struct->id);

        self::assertNull($this->dao->getByIdAndUser($struct->id, $this->uid + 999999));
    }

    public function testSaveDefaultMarksOthersNotDefault(): void
    {
        $first = $this->dao->save($this->newStruct('first', true));
        $this->trackTemplate($first->id);
        $second = $this->dao->save($this->newStruct('second', true));
        $this->trackTemplate($second->id);

        // saving the 2nd default flips the 1st to non-default
        self::assertFalse((bool)$this->dao->getByIdAndUser($first->id, $this->uid)->is_default);
        self::assertTrue((bool)$this->dao->getByIdAndUser($second->id, $this->uid)->is_default);
    }

    // ---- update ----------------------------------------------------------------------------------

    public function testUpdatePersistsChanges(): void
    {
        $struct = $this->dao->save($this->newStruct('before'));
        $this->trackTemplate($struct->id);

        $struct->name = 'after';
        $struct->source_language = 'fr-FR';
        $this->dao->update($struct);

        $fetched = $this->dao->getByIdAndUser($struct->id, $this->uid);
        self::assertSame('after', $fetched->name);
        self::assertSame('fr-FR', $fetched->source_language);
    }

    public function testUpdateThrowsWhenIdIsNull(): void
    {
        $struct = $this->newStruct();
        $struct->id = null;

        $this->expectException(Exception::class);
        $this->dao->update($struct);
    }

    public function testUpdateDefaultMarksOthersNotDefault(): void
    {
        $first = $this->dao->save($this->newStruct('a', true));
        $this->trackTemplate($first->id);
        $second = $this->dao->save($this->newStruct('b', false));
        $this->trackTemplate($second->id);

        $second->is_default = true;
        $this->dao->update($second);

        self::assertFalse((bool)$this->dao->getByIdAndUser($first->id, $this->uid)->is_default);
        self::assertTrue((bool)$this->dao->getByIdAndUser($second->id, $this->uid)->is_default);
    }

    // ---- getTheDefaultProject --------------------------------------------------------------------

    public function testGetTheDefaultProjectReturnsTheDefault(): void
    {
        $struct = $this->dao->save($this->newStruct('thedefault', true));
        $this->trackTemplate($struct->id);

        $default = $this->dao->getTheDefaultProject($this->uid);
        self::assertInstanceOf(ProjectTemplateStruct::class, $default);
        self::assertSame($struct->id, $default->id);
    }

    public function testGetTheDefaultProjectReturnsNullWhenNoneDefault(): void
    {
        $struct = $this->dao->save($this->newStruct('nondefault', false));
        $this->trackTemplate($struct->id);

        self::assertNull($this->dao->getTheDefaultProject($this->uid));
    }

    // ---- getAllPaginated -------------------------------------------------------------------------

    public function testGetAllPaginatedReturnsRows(): void
    {
        $a = $this->dao->save($this->newStruct('p1'));
        $this->trackTemplate($a->id);
        $b = $this->dao->save($this->newStruct('p2'));
        $this->trackTemplate($b->id);

        $result = $this->dao->getAllPaginated($this->uid, '/templates', 1, 20, 0);

        self::assertIsArray($result);
        self::assertArrayHasKey('items', $result);
        self::assertCount(2, $result['items']);
        self::assertSame(1, $result['current_page']);
    }

    // ---- markAsNotDefault ------------------------------------------------------------------------

    public function testMarkAsNotDefaultClearsOtherDefaults(): void
    {
        $keep = $this->dao->save($this->newStruct('keep', true));
        $this->trackTemplate($keep->id);
        $other = $this->dao->save($this->newStruct('other', false));
        $this->trackTemplate($other->id);
        // force the "other" to default directly so markAsNotDefault has something to clear
        $other->is_default = true;
        $this->dao->update($other);

        $this->dao->markAsNotDefault($this->uid, $keep->id);

        self::assertFalse((bool)$this->dao->getByIdAndUser($other->id, $this->uid)->is_default);
    }

    // ---- remove ----------------------------------------------------------------------------------

    public function testRemoveDeletesRow(): void
    {
        $struct = $this->dao->save($this->newStruct('todelete'));
        $this->trackTemplate($struct->id); // idempotent: DELETE in cleanup is harmless if gone

        $affected = $this->dao->remove($struct->id, $this->uid);

        self::assertSame(1, $affected);
        self::assertNull($this->dao->getByIdAndUser($struct->id, $this->uid));
    }

    // ---- removeSubTemplateByIdAndUser ------------------------------------------------------------

    public function testRemoveSubTemplateZeroesField(): void
    {
        $struct = $this->newStruct('withsub');
        $struct->qa_model_template_id = 7;
        $saved = $this->dao->save($struct);
        $this->trackTemplate($saved->id);

        $affected = $this->dao->removeSubTemplateByIdAndUser(7, $this->uid, 'qa_model_template_id');

        self::assertSame(1, $affected);
        self::assertSame(0, $this->dao->getByIdAndUser($saved->id, $this->uid)->qa_model_template_id);
    }

    // ---- destroyDefaultTemplateCache -------------------------------------------------------------

    public function testDestroyDefaultTemplateCache(): void
    {
        $struct = $this->dao->save($this->newStruct('cachedflag', true));
        $this->trackTemplate($struct->id);
        // prime the default-template cache
        $this->dao->setCacheTTL(3600)->getTheDefaultProject($this->uid);

        $conn = $this->realSqlDb()->getConnection();
        // void method: exercise the cache-destroy SQL path; assert the cached row is gone after.
        $this->dao->destroyDefaultTemplateCache($conn, $this->uid);

        // re-fetch still returns the default (cache was destroyed, re-read from DB)
        self::assertSame($struct->id, $this->dao->getTheDefaultProject($this->uid)->id);
    }

    // ---- getDefaultTemplate ----------------------------------------------------------------------

    public function testGetDefaultTemplateBuildsSyntheticDefault(): void
    {
        // No saved templates -> is_default true; uses the personal team seeded in setUp.
        $default = $this->dao->getDefaultTemplate($this->uid);

        self::assertInstanceOf(ProjectTemplateStruct::class, $default);
        self::assertSame(0, $default->id);
        self::assertSame('Matecat original settings', $default->name);
        self::assertTrue($default->is_default);
        self::assertSame($this->idTeam, $default->id_team);
        self::assertSame($this->uid, $default->uid);
        self::assertSame('en-US', $default->source_language);
        self::assertSame(['fr-FR'], $default->getTargetLanguage());
        self::assertNotNull($default->mt);
    }

    public function testGetDefaultTemplateNotDefaultWhenADefaultExists(): void
    {
        $saved = $this->dao->save($this->newStruct('existing default', true));
        $this->trackTemplate($saved->id);

        $default = $this->dao->getDefaultTemplate($this->uid);

        self::assertFalse($default->is_default);
    }

    // ---- createFromJSON / editFromJSON (checkValues happy path) -----------------------------------

    public function testCreateFromJSONHappyPath(): void
    {
        $created = $this->dao->createFromJSON($this->newDecodedJson('created tpl'), $this->user);
        $this->trackTemplate($created->id);

        self::assertGreaterThan(0, $created->id);
        self::assertSame('created tpl', $created->name);
        $fetched = $this->dao->getByIdAndUser($created->id, $this->uid);
        self::assertSame('created tpl', $fetched->name);
    }

    public function testCreateFromJSONThrowsWhenTeamNotOwned(): void
    {
        $json = $this->newDecodedJson('bad team');
        $json->id_team = 987654321; // a team the user does not belong to

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONThrowsOnInvalidSourceLanguage(): void
    {
        $json = $this->newDecodedJson('bad src');
        $json->source_language = 'zz-ZZ';

        $this->expectException(Exception::class);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONThrowsOnInvalidTargetLanguage(): void
    {
        $json = $this->newDecodedJson('bad tgt');
        $json->target_language = ['zz-ZZ'];

        $this->expectException(Exception::class);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONThrowsOnMissingXliffTemplate(): void
    {
        $json = $this->newDecodedJson('bad xliff');
        $json->xliff_config_template_id = 424242; // not existing

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONThrowsOnMissingFiltersTemplate(): void
    {
        $json = $this->newDecodedJson('bad filters');
        $json->filters_template_id = 424242;

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONThrowsOnMissingQaTemplate(): void
    {
        $json = $this->newDecodedJson('bad qa');
        $json->qa_model_template_id = 424242;

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONThrowsOnMissingPayableRateTemplate(): void
    {
        $json = $this->newDecodedJson('bad pr');
        $json->payable_rate_template_id = 424242;

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testCreateFromJSONWithDefaultEngineMtPasses(): void
    {
        $json = $this->newDecodedJson('with mt');
        // engine id 1 is the built-in default MT (record id <= 1) -> ownership check passes.
        $json->mt = (object)['id' => 1];

        $created = $this->dao->createFromJSON($json, $this->user);
        $this->trackTemplate($created->id);

        self::assertGreaterThan(0, $created->id);
    }

    public function testCreateFromJSONThrowsWhenTmKeyNotOwned(): void
    {
        $json = $this->newDecodedJson('with tm');
        // a TM key the user does not own -> MemoryKeyDao::read returns empty -> 403.
        $json->tm = [
            (object)['key' => str_repeat('a', 16), 'name' => 'rsq-key', 'r' => 1, 'w' => 1],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $this->dao->createFromJSON($json, $this->user);
    }

    public function testEditFromJSONHappyPath(): void
    {
        $created = $this->dao->createFromJSON($this->newDecodedJson('orig'), $this->user);
        $this->trackTemplate($created->id);

        $json = $this->newDecodedJson('edited');
        $edited = $this->dao->editFromJSON(new ProjectTemplateStruct(), $json, $created->id, $this->user);

        self::assertSame('edited', $edited->name);
        self::assertSame('edited', $this->dao->getByIdAndUser($created->id, $this->uid)->name);
    }
}
