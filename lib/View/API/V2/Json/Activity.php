<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/12/16
 * Time: 19.16
 *
 */

namespace API\V2\Json;


use ActivityLog\ActivityLogStruct;

class Activity {
    /**
     * @var \Comments_BaseCommentStruct[]
     */
    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        $out = [];

        $featureSet = new \FeatureSet();

        /**
         * @var $record ActivityLogStruct
         */
        foreach ( $this->data as $record ) {

            $record->action = $record->getAction( $record->action );
            if( empty( $record->email ) ) {
                $record->first_name = "Anonymous";
                $record->last_name = "User";
                $record->email = "Unknown";
            }

            $record = $featureSet->filter('filterActivityLogEntry', $record );

            $out[] = $record->toArray();
        }

        return $out;
    }

}