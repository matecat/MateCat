<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 01/02/19
 * Time: 16.34
 *
 */

use Matecat\SubFiltering\Filters\LtGtDoubleDecode;
use Matecat\SubFiltering\MateCatFilter;
use TestHelpers\AbstractTest;


class ParseSplittedSegmentTest extends AbstractTest {

    /**
     * @var MateCatFilter
     */
    protected $filter;

    /**
     * @throws \Exception
     */
    public function setUp() {
        parent::setUp();
    }

    /**
     * Test for JobStatusController/BulkSegmentStatusChangeWorker
     */
    public function testInputSplitted() {

        $request = [ "123-1", "123-2", "234", "ciao", "567", "536", "244" ];

        foreach( $request as $pos => $integer ) {
            $result = (int)$integer;
            if ( empty( $result ) ) {
                unset( $request[ $pos ] );
                continue;
            }
            $request[ $pos ] = $result;
        }
        $segments_id   = array_unique( $request );

        $this->assertEquals( [ 123, 234, 567, 536, 244 ], array_values( $segments_id ) );

    }

}