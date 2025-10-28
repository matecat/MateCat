<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 14.46
 *
 */

namespace View\API\V2\Json;


use ArrayObject;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\Segments\SegmentOriginalDataDao;
use Utils\LQA\QA;

class QALocalWarning extends QAWarning {

    protected QA  $QA;
    protected int $id_segment;
    protected int $idProject;

    /**
     * QALocalWarning constructor.
     *
     * @param QA  $QA
     * @param int $idSegment
     * @param int $idProject
     */
    public function __construct( QA $QA, int $idSegment, int $idProject ) {
        $this->QA         = $QA;
        $this->id_segment = $idSegment;
        $this->idProject  = $idProject;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function render(): array {

        $out[ 'details' ] = null;
        $out[ 'total' ]   = 0;

        $this->structure = [
                'ERROR'   => [
                        'Categories' => new ArrayObject()
                ],
                'WARNING' => [
                        'Categories' => new ArrayObject()
                ],
                'INFO'    => [
                        'Categories' => new ArrayObject()
                ]
        ];

        $exceptionList = $this->QA->getExceptionList();

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

            /** * @var MateCatFilter $Filter */
            $Filter      = MateCatFilter::getInstance(
                    $this->QA->getFeatureSet(),
                    $this->QA->getSourceSegLang(),
                    $this->QA->getTargetSegLang(),
                    SegmentOriginalDataDao::getSegmentDataRefMap( $this->id_segment )
            );

            foreach ( $malformedStructs[ 'source' ] as $k => $rawSource ) {
                $malformedStructs[ 'source' ][ $k ] = $Filter->fromLayer1ToLayer2( $rawSource );
            }

            foreach ( $malformedStructs[ 'target' ] as $k => $rawTarget ) {
                $malformedStructs[ 'target' ][ $k ] = $Filter->fromLayer1ToLayer2( $rawTarget );
            }

            $targetTagPositionError = $this->QA->getTargetTagPositionError();
            foreach ( $targetTagPositionError as $item => $value ) {
                $targetTagPositionError[ $item ] = $value;
            }

            $out[ 'details' ]                              = [];
            $out[ 'details' ][ 'issues_info' ]             = $this->structure;
            $out[ 'details' ][ 'id_segment' ]              = $this->id_segment;
            $out[ 'details' ][ 'tag_mismatch' ]            = $malformedStructs;
            $out[ 'details' ][ 'tag_mismatch' ][ 'order' ] = $targetTagPositionError;
            $out[ 'total' ]                                = count( json_decode( $this->QA->getNoticesJSON() ) );
        }

        return $out;

    }


}