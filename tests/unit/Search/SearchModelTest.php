<?php

use Matecat\SubFiltering\MateCatFilter;
use Search\SearchModel;
use Search\SearchQueryParamsStruct;
use TestHelpers\AbstractTest;

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
 * - Hello world qarkullimit” &amp; faturës.
 *
 * ############################################
 * # TRANSLATIONS (used for target tests)     #
 * ############################################
 * - Ciao mondo 4WD &amp; ampolla %{variable}%
 * - Ciao mondo &#13;&#13;
 * - Anche questa unità ha un &quot;commento&quot;;
 * - Ciao mondo
 */
class SearchModelTest extends AbstractTest {

    /**
     * @var string
     */
    private $jobId;

    /**
     * @var string
     */
    private $jobPwd;

    public function setUp(): void {
        parent::setUp();

        $conn = Database::obtain()->getConnection();

        // job id pre-filled in import sql
        $query = "SELECT id,password FROM unittest_matecat_local.jobs WHERE id = 1886428338 ORDER BY id desc LIMIT 1;";

        $res = $conn->query( $query )->fetchAll();

        $this->jobId  = $res[ 0 ][ 'id' ];
        $this->jobPwd = $res[ 0 ][ 'password' ];
    }

    /**
     * @throws Exception
     */
    public function testSearchSource() {
        $this->_launchSearchAndVerifyResults( 'source', 'Hello', 4, [ 1, 2, 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', '%', 2, [ 1 ] );
        $this->_launchSearchAndVerifyResults( 'source', '"comment"', 1, [ 3 ] );
        $this->_launchSearchAndVerifyResults( 'source', '&', 2, [ 1, 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'amp', 1, [ 1 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'ampoule', 1, [ 1 ] );
        $this->_launchSearchAndVerifyResults( 'source', '#', 0, [] );
        $this->_launchSearchAndVerifyResults( 'source', ';', 1, [ 3 ] );
        $this->_launchSearchAndVerifyResults( 'source', '$', 0, [] );
        $this->_launchSearchAndVerifyResults( 'source', 'faturës', 1, [ 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'fatur', 1, [ 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'qarkullimit”', 1, [ 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'qarkullimit', 1, [ 4 ] );
    }

    /**
     * @throws Exception
     */
    public function testSearchTarget() {
        $this->_launchSearchAndVerifyResults( 'target', 'Ciao', 4, [ 1, 2, 4 ] );
        $this->_launchSearchAndVerifyResults( 'target', '%', 2, [ 1 ] );
        $this->_launchSearchAndVerifyResults( 'target', '&', 1, [ 1 ] );
        $this->_launchSearchAndVerifyResults( 'target', ';', 1, [ 3 ] );
    }

    /**
     * @throws Exception
     */
    public function testWholeWordSearch() {
        $this->_launchSearchAndVerifyResults( 'source', 'is', 1, [ 3 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'is', 0, [], true );
        $this->_launchSearchAndVerifyResults( 'source', 'IS', 0, [], false, true ); //  test match case
        $this->_launchSearchAndVerifyResults( 'source', 'too', 1, [ 3 ], true );
    }

    /**
     * @param string $key
     * @param string $word
     * @param int    $expectedCount
     * @param array  $expectedIds
     * @param bool   $wholeWord
     *
     * @throws Exception
     */
    private function _launchSearchAndVerifyResults( $key, $word, $expectedCount, array $expectedIds = [], $wholeWord = false, $isMatchCaseRequested = false ) {


        // build $queryParamsStruct
        $queryParamsStruct                                          = new SearchQueryParamsStruct();
        $queryParamsStruct->job                                     = $this->jobId;
        $queryParamsStruct->password                                = $this->jobPwd;
        $queryParamsStruct->status                                  = 'all';
        $queryParamsStruct->matchCase                               = false;
        $queryParamsStruct->isExactMatchRequested                   = $wholeWord;
        $queryParamsStruct->isMatchCaseRequested                    = $isMatchCaseRequested;
        $queryParamsStruct[ 'key' ]                                 = $key;
        $queryParamsStruct[ ( $key === 'target' ) ? 'trg' : 'src' ] = $word;

        // jobData
        $jobData = Jobs_JobDao::getByIdAndPassword( $this->jobId, $this->jobPwd );

        // instantiate the filters
        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );

        /** @var MateCatFilter $filters */
        $filters = MateCatFilter::getInstance( $featureSet, $jobData->source, $jobData->target, [] );

        // instantiate the searchModel
        $searchModel = new SearchModel( $queryParamsStruct, $filters );

        // make assertions
        $expected = [
                'sid_list' => $expectedIds,
                'count'    => $expectedCount,
        ];

        $this->assertEquals( $expected, $searchModel->search( true ) );
    }
}
