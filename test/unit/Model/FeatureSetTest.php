<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/06/2017
 * Time: 16:51
 */
class FeatureSetTest extends PHPUnit_Framework_TestCase {

    function test_getSortedFeatures() {
        $featureSet = new FeatureSet();
        $featureSet->loadFromString("dqf,review_improved,translation_versions,project_completion") ;

        $this->assertEquals(
                "translated,mmt,translation_versions,project_completion,dqf,review_improved",
                implode(',', $featureSet->sortFeatures()->getCodes() ) ) ;
    }


}