<?php

use Features\Airbnb;
use Features\Ebay;
use Features\Microsoft;
use Features\ReviewImproved;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 01/02/19
 * Time: 16.34
 *
 */
class FeaturesSetTest extends AbstractTest {

    protected static $microsoftDependencies = [
            Features::PROJECT_COMPLETION,
            Features::TRANSLATION_VERSIONS,
            Features::QACHECK_GLOSSARY
    ];

    protected static $ebayDependencies = [
            Features::PROJECT_COMPLETION,
            Features::TRANSLATION_VERSIONS,
            ReviewImproved::FEATURE_CODE
    ];

    protected static $airbnbDependencies = [
            Features::TRANSLATION_VERSIONS,
//            Features::REVIEW_EXTENDED  // FIX: Undefined index: review_extended
    ];

    protected static $abstractReviewDependencies = [
            Features::TRANSLATION_VERSIONS
    ];

    protected function _testForDependenciesOrder( $dependenciesSet ) {

        foreach ( self::$microsoftDependencies as $dep ) {
            $this->assertTrue( $dependenciesSet[ $dep ] < $dependenciesSet[ Microsoft::FEATURE_CODE ] );
        }

        foreach ( self::$ebayDependencies as $dep ) {
            $this->assertTrue( $dependenciesSet[ $dep ] < $dependenciesSet[ Ebay::FEATURE_CODE ] );
        }

        foreach ( self::$airbnbDependencies as $dep ) {
            $this->assertTrue( $dependenciesSet[ $dep ] < $dependenciesSet[ Airbnb::FEATURE_CODE ] );
        }

        foreach ( self::$abstractReviewDependencies as $dep ) {
//            $this->assertTrue( $dependenciesSet[ $dep ] < $dependenciesSet[ Features::REVIEW_EXTENDED ] ); // FIX: Undefined index: review_extended
            $this->assertTrue( $dependenciesSet[ $dep ] < $dependenciesSet[ ReviewImproved::FEATURE_CODE ] );
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

            $codes = explode( ",", "qa_check_glossary,airbnb,ebay,review_improved,microsoft,translation_versions,project_completion,review_extended,translated,qa_check_blacklist" );
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