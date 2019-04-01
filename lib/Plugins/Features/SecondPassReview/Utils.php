<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/03/2019
 * Time: 12:30
 */


namespace Features\SecondPassReview;

class Utils {

    public static function revisionNumberToSourcePage($number) {
        return $number + 1 ;
    }
}