<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */


namespace Features\SecondPassReview;

use LQA\ModelStruct;

class Utils {
    public static function revisionNumberToSourcePage($number) {
        return $number + 1 ;
    }

    public static function sourcePageToRevisionNumber( $number ) {
        if ( $number - 1 < 1 ) {
            return null ;
        } else {
            return $number - 1 ;
        }
    }

    public static function filterLQAModelLimit( ModelStruct $lqaModel, $sourcePage ) {
        $limit = $lqaModel->getLimit() ;

        if ( is_array( $limit ) ) {
            /**
             * Limit array index equals to $source_page -2.
             */
            return isset( $limit[ $sourcePage - 2 ] ) ? $limit[ $sourcePage - 2 ] : end( $limit ) ;
        }
        else {
            return $limit ;
        }
    }
}