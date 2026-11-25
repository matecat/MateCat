<?php


namespace Matecat\Core\Search;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Search\SearchModel;
use Model\Search\SearchQueryParamsStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class SearchModelTest
 *
 * The test are performed against these records:
 *
 * ############################################
 * # SEGMENTS (used for source tests)         #
 * ############################################
 * - Hello Hello world 4WD &amp; ampoule %{variable}%
 * - Hello world &#13;&#13;
 * - This unit has a &quot;comment&quot; too;
 * - Hello world qarkullimit" &amp; faturës.
 *
 * ############################################
 * # TRANSLATIONS (used for target tests)     #
 * ############################################
 * - Ciao mondo 4WD &amp; ampolla %{variable}%
 * - Ciao mondo &#13;&#13;
 * - Anche questa unità ha un &quot;commento&quot;;
 * - Ciao mondo
 */
#[Group('PersistenceNeeded')]
class SearchModelTest extends AbstractTest
{

    /**
     * @var string
     */
    private $jobId;

    /**
     * @var string
     */
    private $jobPwd;

    public function setUp(): void
    {
        parent::setUp();

        $conn = obtainTestDatabase()->getConnection();

        // job id pre-filled in import sql
        $query = "SELECT id,password FROM unittest_matecat_local.jobs WHERE id = 1886428338 ORDER BY id desc LIMIT 1;";

        $res = $conn->query($query)->fetchAll();

        $this->jobId = $res[0]['id'];
        $this->jobPwd = $res[0]['password'];
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSearchSource()
    {
        $this->_launchSearchAndVerifyResults('source', 'Hello', 4, [1, 2, 4]);
        $this->_launchSearchAndVerifyResults('source', '%', 2, [1]);
        $this->_launchSearchAndVerifyResults('source', '"comment"', 1, [3]);
        $this->_launchSearchAndVerifyResults('source', '&', 2, [1, 4]);
        $this->_launchSearchAndVerifyResults('source', 'amp', 1, [1]);
        $this->_launchSearchAndVerifyResults('source', 'ampoule', 1, [1]);
        $this->_launchSearchAndVerifyResults('source', '#', 0, []);
        $this->_launchSearchAndVerifyResults('source', ';', 1, [3]);
        $this->_launchSearchAndVerifyResults('source', '$', 0, []);
        $this->_launchSearchAndVerifyResults('source', 'faturës', 1, [4]);
        $this->_launchSearchAndVerifyResults('source', 'fatur', 1, [4]);
        $this->_launchSearchAndVerifyResults('source', 'qarkullimit”', 1, [4]);
        $this->_launchSearchAndVerifyResults('source', 'qarkullimit', 1, [4]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSearchTarget()
    {
        $this->_launchSearchAndVerifyResults('target', 'Ciao', 4, [1, 2, 4]);
        $this->_launchSearchAndVerifyResults('target', '%', 2, [1]);
        $this->_launchSearchAndVerifyResults('target', '&', 1, [1]);
        $this->_launchSearchAndVerifyResults('target', ';', 1, [3]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testWholeWordSearch()
    {
        $this->_launchSearchAndVerifyResults('source', 'is', 1, [3]);
        $this->_launchSearchAndVerifyResults('source', 'is', 0, [], true);
        $this->_launchSearchAndVerifyResults('source', 'IS', 0, [], false, true); //  test match case
        $this->_launchSearchAndVerifyResults('source', 'too', 1, [3], true);
    }

    #[Test]
    public function testSearchWithStatusFilter(): void
    {
        $allResults = $this->_searchWithStatus('all');
        $translatedResults = $this->_searchWithStatus('TRANSLATED');

        $this->assertLessThanOrEqual($allResults['count'], $translatedResults['count']);
        foreach ($translatedResults['sid_list'] as $sid) {
            $this->assertContains($sid, $allResults['sid_list']);
        }
    }

    #[Test]
    public function testSearchWithStatusInjectionAttempt(): void
    {
        $result = $this->_searchWithStatus("'; DROP TABLE segments; --");

        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['sid_list']);
    }

    /**
     * @param string $key
     * @param string $word
     * @param int $expectedCount
     * @param array $expectedIds
     * @param bool $wholeWord
     *
     * @throws Exception
     */
    private function _launchSearchAndVerifyResults($key, $word, $expectedCount, array $expectedIds = [], $wholeWord = false, $isMatchCaseRequested = false): void
    {
        // build $queryParamsStruct
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job = $this->jobId;
        $queryParamsStruct->password = $this->jobPwd;
        $queryParamsStruct->status = 'all';
        $queryParamsStruct->isExactMatchRequested = $wholeWord;
        $queryParamsStruct->isMatchCaseRequested = $isMatchCaseRequested;
        $queryParamsStruct['key'] = $key;
        $queryParamsStruct[($key === 'target') ? 'trg' : 'src'] = $word;

        // jobData
        $jobData = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId, $this->jobPwd);

        // instantiate the filters
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");

        /** @var MateCatFilter $filters */
        $filters = MateCatFilter::getInstance($featureSet, $jobData->source, $jobData->target, []);

        // instantiate the searchModel
        $searchModel = new SearchModel($queryParamsStruct, $filters, obtainTestDatabase());

        // make assertions
        $expected = [
            'sid_list' => $expectedIds,
            'count' => $expectedCount,
        ];

        $this->assertEquals($expected, $searchModel->search(true));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSearchCoupled(): void
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job               = $this->jobId;
        $queryParamsStruct->password          = $this->jobPwd;
        $queryParamsStruct->status            = 'all';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested  = false;
        $queryParamsStruct['key'] = 'coupled';
        $queryParamsStruct['src'] = 'Hello';
        $queryParamsStruct['trg'] = 'Ciao';

        $jobData    = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId, $this->jobPwd);
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");
        $filters     = MateCatFilter::getInstance($featureSet, $jobData->source, $jobData->target, []);
        $searchModel = new SearchModel($queryParamsStruct, $filters, obtainTestDatabase());

        $result = $searchModel->search(true);

        $this->assertArrayHasKey('sid_list', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertGreaterThanOrEqual(0, $result['count']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSearchStatusOnly(): void
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job               = $this->jobId;
        $queryParamsStruct->password          = $this->jobPwd;
        $queryParamsStruct->status            = 'TRANSLATED';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested  = false;
        $queryParamsStruct['key'] = 'status_only';

        $jobData    = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId, $this->jobPwd);
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");
        $filters     = MateCatFilter::getInstance($featureSet, $jobData->source, $jobData->target, []);
        $searchModel = new SearchModel($queryParamsStruct, $filters, obtainTestDatabase());

        $result = $searchModel->search(false);

        $this->assertArrayHasKey('sid_list', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertIsArray($result['sid_list']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSearchDefaultKeyReturnsEmpty(): void
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job               = $this->jobId;
        $queryParamsStruct->password          = $this->jobPwd;
        $queryParamsStruct->status            = 'all';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested  = false;
        $queryParamsStruct['key'] = 'unknown_key';

        $jobData    = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId, $this->jobPwd);
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");
        $filters     = MateCatFilter::getInstance($featureSet, $jobData->source, $jobData->target, []);
        $searchModel = new SearchModel($queryParamsStruct, $filters, obtainTestDatabase());

        $result = $searchModel->search(false);

        $this->assertSame([], $result['sid_list']);
        $this->assertSame(0, $result['count']);
    }

    // ─── includeLocked ───
    //
    // includeLocked is true by default, so the queries stay as they always were. When it is false the locked
    // segments drop out of the result set, which is what keeps a replace-all from touching them. Whether a
    // segment is an ICE plays no part: locked is what takes a segment out of scope.

    #[Test]
    public function testSearchInSourceExcludesLockedSegmentsWhenLockedAreExcluded(): void
    {
        $this->_assertLockedIsExcludedWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchInSource('Hello', $includeLocked);
        });
    }

    #[Test]
    public function testSearchInTargetExcludesLockedSegmentsWhenLockedAreExcluded(): void
    {
        $this->_assertLockedIsExcludedWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchInTarget('Ciao', $includeLocked);
        });
    }

    #[Test]
    public function testSearchStatusOnlyExcludesLockedSegmentsWhenLockedAreExcluded(): void
    {
        $this->_assertLockedIsExcludedWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchStatusOnly($includeLocked);
        });
    }

    #[Test]
    public function testSearchInSourceKeepsUnlockedIcesWhenLockedAreExcluded(): void
    {
        $this->_assertUnlockedIceIsKeptWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchInSource('Hello', $includeLocked);
        });
    }

    #[Test]
    public function testSearchInTargetKeepsUnlockedIcesWhenLockedAreExcluded(): void
    {
        $this->_assertUnlockedIceIsKeptWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchInTarget('Ciao', $includeLocked);
        });
    }

    #[Test]
    public function testSearchStatusOnlyKeepsUnlockedIcesWhenLockedAreExcluded(): void
    {
        $this->_assertUnlockedIceIsKeptWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchStatusOnly($includeLocked);
        });
    }

    #[Test]
    public function testSearchInSourceKeepsSegmentsWithoutALockedFlagWhenLockedAreExcluded(): void
    {
        $this->_assertANullLockedIsNotTreatedAsLocked(function (bool $includeLocked): array {
            return $this->_searchInSource('Hello', $includeLocked);
        });
    }

    #[Test]
    public function testSearchInTargetKeepsSegmentsWithoutALockedFlagWhenLockedAreExcluded(): void
    {
        $this->_assertANullLockedIsNotTreatedAsLocked(function (bool $includeLocked): array {
            return $this->_searchInTarget('Ciao', $includeLocked);
        });
    }

    #[Test]
    public function testSearchStatusOnlyKeepsSegmentsWithoutALockedFlagWhenLockedAreExcluded(): void
    {
        $this->_assertANullLockedIsNotTreatedAsLocked(function (bool $includeLocked): array {
            return $this->_searchStatusOnly($includeLocked);
        });
    }

    #[Test]
    public function testGetQueryWrapsDatabaseFailuresIntoAnException(): void
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job = $this->jobId;
        $queryParamsStruct->password = $this->jobPwd;
        $queryParamsStruct->status = 'all';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested = false;

        $searchModel = $this->_buildSearchModel($queryParamsStruct);

        $method = new \ReflectionMethod(SearchModel::class, '_getQuery');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);

        $method->invoke($searchModel, 'SELECT id FROM a_table_that_does_not_exist_in_matecat', []);
    }

    /**
     * Runs the given search twice: once as it normally happens, then again with the locked segments
     * excluded, after locking one of the segments the first run returned. Its match type is left as it
     * was, so the fixture row is not an ICE. That segment, and only that one, has to disappear from the
     * second result set.
     *
     * @param callable(bool): array{sid_list: list<string>, count: int} $search
     *
     * @throws Exception
     */
    private function _assertLockedIsExcludedWhenLockedAreExcluded(callable $search): void
    {
        $withLocked = $search(true);
        $this->assertNotEmpty($withLocked['sid_list'], 'the fixture must return at least one segment');

        $lockedSegmentId = (int)$withLocked['sid_list'][0];

        $this->_withSegmentTranslationColumns($lockedSegmentId, ['locked' => 1], function () use ($search, $withLocked, $lockedSegmentId): void {
            $withoutLocked = $search(false);

            $this->assertNotContains((string)$lockedSegmentId, $withoutLocked['sid_list']);
            $this->assertSame(count($withLocked['sid_list']) - 1, count($withoutLocked['sid_list']));
        });
    }

    /**
     * An ICE that is not locked is in scope, as an XLIFF pre-translation whose rule category is ICE is,
     * and must survive the exclusion.
     *
     * @param callable(bool): array{sid_list: list<string>, count: int} $search
     *
     * @throws Exception
     */
    private function _assertUnlockedIceIsKeptWhenLockedAreExcluded(callable $search): void
    {
        $withLocked = $search(true);
        $this->assertNotEmpty($withLocked['sid_list'], 'the fixture must return at least one segment');

        $iceSegmentId = (int)$withLocked['sid_list'][0];

        $this->_withSegmentTranslationColumns($iceSegmentId, ['match_type' => 'ICE', 'locked' => 0], function () use ($search, $withLocked, $iceSegmentId): void {
            $withoutLocked = $search(false);

            $this->assertContains((string)$iceSegmentId, $withoutLocked['sid_list']);
            $this->assertSame(count($withLocked['sid_list']), count($withoutLocked['sid_list']));
        });
    }

    /**
     * A NULL `locked` is not locked and must survive the exclusion.
     *
     * `segment_translations.locked` is nullable, so `locked = 0` evaluates to NULL, not true, for those
     * rows, and SQL would drop them from the result set.
     *
     * @param callable(bool): array{sid_list: list<string>, count: int} $search
     *
     * @throws Exception
     */
    private function _assertANullLockedIsNotTreatedAsLocked(callable $search): void
    {
        $withLocked = $search(true);
        $this->assertNotEmpty($withLocked['sid_list'], 'the fixture must return at least one segment');

        $nullLockedSegmentId = (int)$withLocked['sid_list'][0];

        $this->_withSegmentTranslationColumns($nullLockedSegmentId, ['locked' => null], function () use ($search, $withLocked, $nullLockedSegmentId): void {
            $withoutLocked = $search(false);

            $this->assertContains((string)$nullLockedSegmentId, $withoutLocked['sid_list']);
            $this->assertSame(count($withLocked['sid_list']), count($withoutLocked['sid_list']));
        });
    }

    /**
     * Sets the given columns on one fixture row, runs the assertions, and puts the previous values back
     * whatever the outcome.
     *
     * @param array<string, int|string|null> $columns
     * @param callable(): void $assertions
     *
     * @throws Exception
     */
    private function _withSegmentTranslationColumns(int $segmentId, array $columns, callable $assertions): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $names = array_keys($columns);

        $read = $conn->prepare("SELECT " . implode(', ', $names) . " FROM segment_translations WHERE id_job = :job AND id_segment = :id");
        $read->execute(['job' => $this->jobId, 'id' => $segmentId]);
        $previous = $read->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($previous, 'the fixture row must exist');

        $set = implode(', ', array_map(fn(string $name): string => "$name = :$name", $names));
        $write = $conn->prepare("UPDATE segment_translations SET $set WHERE id_job = :job AND id_segment = :id");
        $write->execute($columns + ['job' => $this->jobId, 'id' => $segmentId]);

        try {
            $assertions();
        } finally {
            $write->execute($previous + ['job' => $this->jobId, 'id' => $segmentId]);
        }
    }

    /**
     * @return array{sid_list: list<string>, count: int}
     * @throws Exception
     */
    private function _searchInSource(string $term, bool $includeLocked): array
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job = $this->jobId;
        $queryParamsStruct->password = $this->jobPwd;
        $queryParamsStruct->status = 'all';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested = false;
        $queryParamsStruct->includeLocked = $includeLocked;
        $queryParamsStruct['key'] = 'source';
        $queryParamsStruct['src'] = $term;

        return $this->_buildSearchModel($queryParamsStruct)->search(false);
    }

    /**
     * @return array{sid_list: list<string>, count: int}
     * @throws Exception
     */
    private function _searchInTarget(string $term, bool $includeLocked): array
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job = $this->jobId;
        $queryParamsStruct->password = $this->jobPwd;
        $queryParamsStruct->status = 'all';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested = false;
        $queryParamsStruct->includeLocked = $includeLocked;
        $queryParamsStruct['key'] = 'target';
        $queryParamsStruct['trg'] = $term;

        return $this->_buildSearchModel($queryParamsStruct)->search(false);
    }

    /**
     * @return array{sid_list: list<string>, count: int}
     * @throws Exception
     */
    private function _searchStatusOnly(bool $includeLocked): array
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job = $this->jobId;
        $queryParamsStruct->password = $this->jobPwd;
        $queryParamsStruct->status = 'TRANSLATED';
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested = false;
        $queryParamsStruct->includeLocked = $includeLocked;
        $queryParamsStruct['key'] = 'status_only';

        return $this->_buildSearchModel($queryParamsStruct)->search(false);
    }

    /**
     * @throws Exception
     */
    private function _buildSearchModel(SearchQueryParamsStruct $queryParamsStruct): SearchModel
    {
        $jobData = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId, $this->jobPwd);
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");
        $filters = MateCatFilter::getInstance($featureSet, $jobData->source, $jobData->target, []);

        return new SearchModel($queryParamsStruct, $filters, obtainTestDatabase());
    }

    private function _searchWithStatus(string $status): array
    {
        $queryParamsStruct = new SearchQueryParamsStruct();
        $queryParamsStruct->job = $this->jobId;
        $queryParamsStruct->password = $this->jobPwd;
        $queryParamsStruct->status = $status;
        $queryParamsStruct->isExactMatchRequested = false;
        $queryParamsStruct->isMatchCaseRequested = false;
        $queryParamsStruct['key'] = 'target';
        $queryParamsStruct['trg'] = 'Ciao';

        $jobData = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId, $this->jobPwd);
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");
        $filters = MateCatFilter::getInstance($featureSet, $jobData->source, $jobData->target, []);

        $searchModel = new SearchModel($queryParamsStruct, $filters, obtainTestDatabase());
        return $searchModel->search(true);
    }
}
