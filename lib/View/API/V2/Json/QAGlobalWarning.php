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

        $issues_detail = [];
        $items         = [];
        $totals        = [ QA::ERROR => [], QA::WARNING => [], QA::INFO => [] ];

        foreach ( $this->tagIssues as $position => $_item ) {

            $items[] = $_item[ 'id_segment' ];

            $exceptionList                                          = QA::JSONtoExceptionList( $_item[ 'serialized_errors_list' ] );
            $issues_detail[ $_item[ 'id_segment' ] ][ QA::ERROR ]   = $exceptionList[ QA::ERROR ];
            $issues_detail[ $_item[ 'id_segment' ] ][ QA::WARNING ] = $exceptionList[ QA::WARNING ];
            $issues_detail[ $_item[ 'id_segment' ] ][ QA::INFO ]    = $exceptionList[ QA::INFO ];

            if ( count( $exceptionList[ QA::ERROR ] ) > 0 ) {
                $totals[ QA::ERROR ][] = $_item[ 'id_segment' ];
            }

            if ( count( $exceptionList[ QA::WARNING ] ) > 0 ) {
                $totals[ QA::WARNING ][] = $_item[ 'id_segment' ];
            }

            if ( count( $exceptionList[ QA::INFO ] ) > 0 ) {
                $totals[ QA::INFO ][] = $_item[ 'id_segment' ];
            }

        }

        $out = [];
        $out[ 'details' ][ 'tag_issues' ]  = array_values( $items );
        $out[ 'details' ][ 'issues_info' ] = $issues_detail;
        $out[ 'details' ][ 'totals' ] = $totals;


        $result = [ 'total' => count( $this->translationMismatches ), 'mine' => 0, 'list_in_my_job' => [] ];
        foreach ( $this->translationMismatches as $row ) {

            if ( !empty( $row[ 'first_of_my_job' ] ) ) {
                $result[ 'mine' ]++;
                $result[ 'list_in_my_job' ][] = $row[ 'first_of_my_job' ];
            }

        }

        $out[ 'details' ][ 'translation_mismatches' ] = $result[ 'list_in_my_job' ];
        $out[ 'translation_mismatches' ] = $result;

        return $out;

    }


}