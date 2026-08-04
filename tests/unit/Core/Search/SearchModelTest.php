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
    // includeLocked is true by default, so the queries stay as they always were. When it is false the ICE
    // segments drop out of the result set, which is what keeps a replace-all from touching them.

    #[Test]
    public function testSearchInSourceExcludesIcesWhenLockedAreExcluded(): void
    {
        $this->_assertIceIsExcludedWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchInSource('Hello', $includeLocked);
        });
    }

    #[Test]
    public function testSearchInTargetExcludesIcesWhenLockedAreExcluded(): void
    {
        $this->_assertIceIsExcludedWhenLockedAreExcluded(function (bool $includeLocked): array {
            return $this->_searchInTarget('Ciao', $includeLocked);
        });
    }

    #[Test]
    public function testSearchStatusOnlyExcludesIcesWhenLockedAreExcluded(): void
    {
        $this->_assertIceIsExcludedWhenLockedAreExcluded(function (bool $includeLocked): array {
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
     * excluded, after turning one of the segments the first run returned into an ICE. That segment, and
     * only that one, has to disappear from the second result set.
     *
     * @param callable(bool): array{sid_list: list<string>, count: int} $search
     *
     * @throws Exception
     */
    private function _assertIceIsExcludedWhenLockedAreExcluded(callable $search): void
    {
        $withLocked = $search(true);
        $this->assertNotEmpty($withLocked['sid_list'], 'the fixture must return at least one segment');

        $iceSegmentId = (int)$withLocked['sid_list'][0];
        $conn = obtainTestDatabase()->getConnection();

        $read = $conn->prepare("SELECT match_type FROM segment_translations WHERE id_job = :job AND id_segment = :id");
        $read->execute(['job' => $this->jobId, 'id' => $iceSegmentId]);
        $previousMatchType = $read->fetchColumn();

        $write = $conn->prepare("UPDATE segment_translations SET match_type = :match_type WHERE id_job = :job AND id_segment = :id");
        $write->execute(['match_type' => 'ICE', 'job' => $this->jobId, 'id' => $iceSegmentId]);

        try {
            $withoutLocked = $search(false);

            $this->assertNotContains((string)$iceSegmentId, $withoutLocked['sid_list']);
            $this->assertSame(count($withLocked['sid_list']) - 1, count($withoutLocked['sid_list']));
        } finally {
            $write->execute([
                'match_type' => $previousMatchType === false ? null : $previousMatchType,
                'job' => $this->jobId,
                'id' => $iceSegmentId,
            ]);
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
