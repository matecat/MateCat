<?php

use TestHelpers\AbstractTest;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/06/2017
 * Time: 16:51
 */
class FeatureSetTest extends AbstractTest {

    function test_getSortedFeatures() {
        $featureSet = new FeatureSet();
        $featureSet->loadFromString( "translation_versions,project_completion" );

        $this->assertEquals(
                "translated,mmt,translation_versions,review_extended,second_pass_review,aligner,project_completion",
                implode( ',', $featureSet->sortFeatures()->getCodes() ) );
    }


}