<?php


namespace Features\Dqf\Utils ;

class Metadata {

    public static $keys = array(
            'dqf'
    );

    /**
     * This function is to be used to filter both postInput from UI and
     * JSON string received from APIs.
     *
     * @return array
     */
    public static function getInputFilter() {
        return array(
                'dqf' => array(
                        'filter' => FILTER_VALIDATE_BOOLEAN,
                )
        );

    }
}