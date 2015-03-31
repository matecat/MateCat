<?php

/**
 * Description of QATest
 *
 * @author domenico
 */
include_once("AbstractTest.php");
include_once INIT::$UTILS_ROOT . '/CatUtils.php';
include_once INIT::$UTILS_ROOT . '/QA.php';
class Tests_QATest2 extends Tests_AbstractTest {

    public function testSpaces_1(){

        $source_seg = <<<SRC
<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>
SRC;

        $target_seg = <<<TRG
<g id="pt2"> WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Internal Revenue Service di oggi hanno chiesto un commento pubblico sulle questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che verranno applicate a certi datori di lavoro a partire dal 2014. </g>
TRG;

        $source_seg = CatUtils::view2rawxliff( $source_seg );
        $target_seg = CatUtils::view2rawxliff( $target_seg );

        $check = new QA($source_seg, $target_seg);
        $check->performConsistencyCheck();

        $notices = $check->getNotices();
        $warnings = $check->getWarnings();
        $errors   = $check->getErrors();

        $this->assertFalse( $check->thereAreErrors() );
        $this->assertFalse( $check->thereAreWarnings() );
        $this->assertTrue( $check->thereAreNotices() );

        $this->assertEquals( count( $notices ), 2 );
        $this->assertEquals( 1100, $notices[0]->outcome );

        $this->assertEquals( count( $warnings ), 1 );
        $this->assertEquals( 0, $warnings[0]->outcome );

        $this->assertEquals( count( $errors ), 1 );
        $this->assertEquals( 0, $errors[0]->outcome );

        $normalized = $check->getTrgNormalized();

        //" 1 " -> 20 31 20
        $this->assertEquals( '<g id="pt2"> WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Internal Revenue Service di oggi hanno chiesto un commento pubblico sulle questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che verranno applicate a certi datori di lavoro a partire dal 2014. </g>', $normalized );

    }

}
