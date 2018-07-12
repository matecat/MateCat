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

        $exceptionList                = QA::JSONtoExceptionList( $this->QA->getNoticesJSON() );
        $issues_detail[ QA::ERROR ]   = $exceptionList[ QA::ERROR ];
        $issues_detail[ QA::WARNING ] = $exceptionList[ QA::WARNING ];
        $issues_detail[ QA::INFO ]    = $exceptionList[ QA::INFO ];


        if ( $this->QA->thereAreNotices() ) {

            $malformedStructs = $this->QA->getMalformedXmlStructs();

            foreach ( $malformedStructs[ 'source' ] as $k => $rawSource ) {
                $malformedStructs[ 'source' ][ $k ] = CatUtils::rawxliff2view( $rawSource );
            }

            foreach ( $malformedStructs[ 'target' ] as $k => $rawTarget ) {
                $malformedStructs[ 'target' ][ $k ] = CatUtils::rawxliff2view( $rawTarget );
            }

            $targetTagPositionError = $this->QA->getTargetTagPositionError();
            foreach ( $targetTagPositionError as $item => $value ) {
                $targetTagPositionError[ $item ] = CatUtils::rawxliff2view( $value );
            }

            $notices = $this->QA->getNotices();

            $out[ 'details' ]                              = [];
            $out[ 'details' ][ 'issues_info' ]             = $issues_detail;
            $out[ 'details' ][ 'id_segment' ]              = $this->id_segment;
            $out[ 'details' ][ 'tag_mismatch' ]            = $malformedStructs;
            $out[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $targetTagPositionError;
            $out[ 'total' ]                                = count( $notices );
        }

        return $out;

    }


}