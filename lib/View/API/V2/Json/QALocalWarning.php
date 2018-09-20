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

class QALocalWarning extends QAWarning {

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

        $this->structure = [
                'ERROR'   => [
                        'Categories' => new \ArrayObject()
                ],
                'WARNING' => [
                        'Categories' => new \ArrayObject()
                ],
                'INFO'     => [
                        'Categories' => new \ArrayObject()
                ]
        ];

        $noticesJson = $this->QA->getNoticesJSON();

        $exceptionList                = QA::JSONtoExceptionList( $noticesJson );


        $issues_detail[ QA::ERROR ]   = $exceptionList[ QA::ERROR ];
        $issues_detail[ QA::WARNING ] = $exceptionList[ QA::WARNING ];
        $issues_detail[ QA::INFO ]    = $exceptionList[ QA::INFO ];


        if ( $this->QA->thereAreNotices() ) {

            if ( count( $exceptionList[ QA::ERROR ] ) > 0 ) {
                foreach ( $exceptionList[ QA::ERROR ] as $exception_error ) {
                    $this->pushErrorSegment( QA::ERROR, $exception_error->outcome, $exception_error );
                }
            }

            if ( count( $exceptionList[ QA::WARNING ] ) > 0 ) {
                foreach ( $exceptionList[ QA::WARNING ] as $exception_error ) {
                    $this->pushErrorSegment( QA::WARNING, $exception_error->outcome, $exception_error );
                }
            }

            if ( count( $exceptionList[ QA::INFO ] ) > 0 ) {
                foreach ( $exceptionList[ QA::INFO ] as $exception_error ) {
                    $this->pushErrorSegment( QA::INFO, $exception_error->outcome, $exception_error );
                }
            }

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

            $out[ 'details' ]                              = [];
            $out[ 'details' ][ 'issues_info' ]             = $this->structure;
            $out[ 'details' ][ 'id_segment' ]              = $this->id_segment;
            $out[ 'details' ][ 'tag_mismatch' ]            = $malformedStructs;
            $out[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $targetTagPositionError;
            $out[ 'total' ]                                = count( json_decode($noticesJson) );
        }

        return $out;

    }


}