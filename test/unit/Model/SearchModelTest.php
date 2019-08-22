<?php

use Search\SearchModel;
use Search\SearchQueryParamsStruct;
use SubFiltering\Filter;

class SearchModelTest extends AbstractTest {



    /**
     * @throws Exception
     */
    public function testSearchSource() {

        // Search for 'Hello'
        $this->_launchSearchAndVerifyResults( 'source', 'Hello', 3, [ 1, 2, 4 ] );

        // Search for '&'
        $this->_launchSearchAndVerifyResults( 'source', '&', 1, [ 1 ] );

        // Search for ';'
        // it MUST skip the html entities
        $this->_launchSearchAndVerifyResults( 'source', ';', 1, [ 3 ] );

        // Search for '$'
        $this->_launchSearchAndVerifyResults( 'source', '$', 0, [] );

        // Search for 'faturës'
        $this->_launchSearchAndVerifyResults( 'source', 'faturës', 1, [ 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'fatur', 1, [ 4 ] );

        // Search for 'qarkullimit”'
        $this->_launchSearchAndVerifyResults( 'source', 'qarkullimit”', 1, [ 4 ] );
        $this->_launchSearchAndVerifyResults( 'source', 'qarkullimit', 1, [ 4 ] );
    }

    /**
     * @throws Exception
     */
    public function testSearchTarget() {

        // Search for 'Hello'
        $this->_launchSearchAndVerifyResults( 'target', 'Ciao', 3, [ 1, 2, 4 ] );

        // Search for '&'
        $this->_launchSearchAndVerifyResults( 'target', '&', 1, [ 1 ] );

        // Search for ';'
        // it MUST skip the html entities
        $this->_launchSearchAndVerifyResults( 'target', ';', 1, [ 3 ] );
    }

    /**
     * @param string $key
     * @param string $word
     * @param int    $expectedCount
     * @param array  $expectedIds
     *
     * @throws Exception
     */
    private function _launchSearchAndVerifyResults( $key, $word, $expectedCount, array $expectedIds = [] ) {

        // build $queryParamsStruct
        $queryParamsStruct                                          = new SearchQueryParamsStruct();
        $queryParamsStruct->job                                     = 587036699; // this is a job ID from my test DB with some "Hello world" string
        $queryParamsStruct->password                                = '62394cc5b716';
        $queryParamsStruct->status                                  = 'all';
        $queryParamsStruct->matchCase                               = false;
        $queryParamsStruct->exactMatch                              = false;
        $queryParamsStruct[ 'key' ]                                 = $key;
        $queryParamsStruct[ ( $key === 'target' ) ? 'trg' : 'src' ] = $word;

        // instantiate the filters
        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        $filters = Filter::getInstance( $featureSet );

        // instantiate the searchModel
        $searchModel = new SearchModel( $queryParamsStruct, $filters );

        // make assertions
        $expected = [
                'sid_list' => $expectedIds,
                'count'    => $expectedCount,
        ];

        $this->assertEquals( $expected, $searchModel->search() );
    }
}