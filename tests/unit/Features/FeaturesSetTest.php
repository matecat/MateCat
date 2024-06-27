<?php

use Features\Airbnb;
use TestHelpers\AbstractTest;


/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 01/02/19
 * Time: 16.34
 *
 */
class FeaturesSetTest extends AbstractTest {

    protected static $airbnbDependencies = [
            Features::TRANSLATION_VERSIONS,
//            Features::REVIEW_EXTENDED  // FIX: Undefined index: review_extended
    ];

    protected static $abstractReviewDependencies = [
            Features::TRANSLATION_VERSIONS
    ];

    protected function _testForDependenciesOrder( $dependenciesSet ) {

        foreach ( self::$airbnbDependencies as $dep ) {
            $this->assertTrue( $dependenciesSet[ $dep ] < $dependenciesSet[ Airbnb::FEATURE_CODE ] );
        }

        return true;

    }

    /**
     * This test is dependant from shuffle, to get a better coverage ( heuristic ) let's run it 2000 times.
     *
     * @throws \Exception
     */
    public function testSortFeatures() {

        for ( $i = 0; $i < 2000; $i++ ) {

            $codes = explode( ",", "qa_check_glossary,airbnb,translation_versions,project_completion,review_extended,translated,qa_check_blacklist" );
            shuffle( $codes );
            shuffle( $codes );
            $code_string = implode( ",", $codes );

            $featureSet = new FeatureSet();
            $featureSet->loadFromString( $code_string );

            $set = array_flip( array_values( $featureSet->getCodes() ) );

            $this->assertEquals( 10, count( $featureSet->getCodes() ) );
            $this->assertEquals( 10, count( $set ) );

            $this->assertTrue( $this->_testForDependenciesOrder( $set ) );

        }

    }

}