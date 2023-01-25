<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 14.46
 *
 */

namespace API\V2\Json;



use LQA\QA;

class QAGlobalWarning extends QAWarning {

    protected $tagIssues;
    protected $translationMismatches;

    protected $structure;

    /**
     * QAGlobalWarning constructor.
     *
     * from query: getWarning( id_job, password )
     *
     * @param array $tagIssues [ [ total_sources, translations_available, first_of_my_job ] ]
     * @param array $translationMismatches [ [ total_sources, translations_available, first_of_my_job ] ]
     */
    public function __construct( $tagIssues, $translationMismatches ) {
        $this->tagIssues = $tagIssues;
        $this->translationMismatches = $translationMismatches;
    }

    /**
     * @return array
     */
    public function render() {

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


        foreach ( $this->tagIssues as $position => $_item ) {

            $exceptionList = QA::JSONtoExceptionList( $_item[ 'serialized_errors_list' ] );

            if ( count( $exceptionList[ QA::ERROR ] ) > 0 ) {
                foreach ( $exceptionList[ QA::ERROR ] as $exception_error ) {
                    $this->pushErrorSegment( QA::ERROR, $exception_error->outcome, $_item[ 'id_segment' ] );
                }
            }

            if ( count( $exceptionList[ QA::WARNING ] ) > 0 ) {
                foreach ( $exceptionList[ QA::WARNING ] as $exception_error ) {
                    $this->pushErrorSegment( QA::WARNING, $exception_error->outcome, $_item[ 'id_segment' ] );
                }
            }

            if ( count( $exceptionList[ QA::INFO ] ) > 0 ) {
                foreach ( $exceptionList[ QA::INFO ] as $exception_error ) {
                    $this->pushErrorSegment( QA::INFO, $exception_error->outcome, $_item[ 'id_segment' ] );
                }
            }

        }

        foreach ( $this->translationMismatches as $row ) {

            if ( !empty( $row[ 'first_of_my_job' ] ) ) {
                $this->structure[ QA::WARNING ][ 'Categories' ][ 'MISMATCH' ][] = $row[ 'first_of_my_job' ];
            }

        }

        $out['details'] = $this->structure;

        return $out;

    }


}