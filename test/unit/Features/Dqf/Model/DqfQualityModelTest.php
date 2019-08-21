<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/06/2017
 * Time: 09:52
 */

use Features\Dqf\Model\DqfQualityModel;

class DqfQualityModelTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        parent::setUp();
        TestHelper::resetDb();
    }

    public function testReviewSettingsAreCorrect() {
        $this->assertTrue( true );

        $pid = TestHelper::$FIXTURES->getFixtures()[ 'projects' ][ 'dqf_project_1' ][ 'id' ];

        $project        = Projects_ProjectDao::findById( $pid );
        $dqfQualtyModel = new DqfQualityModel( $project );

        $reviewSettings = $dqfQualtyModel->getReviewSettings();

        $this->assertEquals( $reviewSettings->severityWeights, json_encode( [
                [ 'severityId' => 1, 'weight' => 0 ],
                [ 'severityId' => 2, 'weight' => 1 ],
                [ 'severityId' => 3, 'weight' => 3 ],
                [ 'severityId' => 4, 'weight' => 10 ],
        ] ) );

        $this->assertEquals( $reviewSettings->errorCategoryIds,
                [ 4, 2, 3, 1, 5 ] // as found in database fixture
        );

        $this->assertEquals( 'error_typology', $reviewSettings->reviewType );
    }


}
