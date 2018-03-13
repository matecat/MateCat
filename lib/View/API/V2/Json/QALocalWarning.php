<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 14.46
 *
 */

namespace API\V2\Json;


use CatUtils;
use QA;

class QALocalWarning {

    protected $QA;
    protected $id_segment;

    /**
     * QALocalWarning constructor.
     *
     * @param QA  $QA
     * @param int $idSegment
     */
    public function __construct( QA $QA, $idSegment ) {
        $this->QA         = $QA;
        $this->id_segment = $idSegment;
    }

    /**
     * @return array
     */
    public function render() {

        $out[ 'details' ] = null;
        $out[ 'total' ]   = 0;

        if ( $this->QA->thereAreNotices() ) {

            $malformedStructs = $this->QA->getMalformedXmlStructs();

            foreach ( $malformedStructs[ 'source' ] as $k => $rawSource ) {
                $malformedStructs[ 'source' ][ $k ] = CatUtils::rawxliff2view( $rawSource );
            }

            foreach ( $malformedStructs[ 'target' ] as $k => $rawTarget ) {
                $malformedStructs[ 'target' ][ $k ] = CatUtils::rawxliff2view( $rawTarget );
            }

            $out[ 'details' ]                              = [];
            $out[ 'details' ][ 'id_segment' ]              = $this->id_segment;
            $out[ 'details' ][ 'warnings' ]                = $this->QA->getNoticesJSON();
            $out[ 'details' ][ 'tag_mismatch' ]            = $malformedStructs;
            $out[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $this->QA->getTargetTagPositionError();
            $out[ 'total' ]                                = count( $this->QA->getNotices() );
        }

        return $out;

    }


}