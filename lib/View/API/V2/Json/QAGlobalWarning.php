<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 14.46
 *
 */

namespace API\V2\Json;


use QA;

class QAGlobalWarning {

    protected $tagIssues;
    protected $translationMismatches;

    protected $structure;

    const TAGS_CATEGORY = "TAGS";
    const MISMATCH_CATEGORY = "MISMATCH";

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
                        'Categories' => []
                ],
                'WARNING' => [
                        'Categories' => []
                ],
                'INFO'     => [
                        'Categories' => []
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

        $result = [ 'total' => count( $this->translationMismatches ), 'mine' => 0, 'list_in_my_job' => [] ];
        foreach ( $this->translationMismatches as $row ) {

            if ( !empty( $row[ 'first_of_my_job' ] ) ) {
                $result[ 'mine' ]++;
                $result[ 'list_in_my_job' ][] = $row[ 'first_of_my_job' ];
                $this->structure[ QA::WARNING ][ 'Categories' ][ 'MISMATCH' ][] = $row[ 'first_of_my_job' ];
            }

        }
        $out['details'] = $this->structure;

        $out[ 'translation_mismatches' ] = $result;

        return $out;

    }

    public function pushErrorSegment( $error_type, $error_category, $segment_id ) {

        switch ( $error_category ) {
            case QA::ERR_TAG_MISMATCH:
            case QA::ERR_TAG_ID:
            case QA::ERR_UNCLOSED_X_TAG:
            case QA::ERR_TAG_ORDER:
                $category = self::TAGS_CATEGORY;
                break;
            case QA::ERR_SPACE_MISMATCH_TEXT:
            case QA::ERR_TAB_MISMATCH:
            case QA::ERR_SPACE_MISMATCH:
            case QA::ERR_SYMBOL_MISMATCH:
            case QA::ERR_NEWLINE_MISMATCH:
                $category = self::MISMATCH_CATEGORY;
                break;
        }

        if ( !isset( $this->structure[ $error_type ][ 'Categories' ][ $category ] ) ) {
            $this->structure[ $error_type ][ 'Categories' ][ $category ] = [];
        }

        if ( !in_array( $segment_id, $this->structure[ $error_type ][ 'Categories' ][ $category ] ) ) {
            $this->structure[ $error_type ][ 'Categories' ][ $category ][] = $segment_id;
        }

    }


}