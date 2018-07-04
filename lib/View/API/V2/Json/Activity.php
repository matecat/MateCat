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
use FeatureSet;

class Activity {
    /**
     * @var \ActivityLog\ActivityLogStruct[]
     */
    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        $out = [];

        $featureSet = new FeatureSet();

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

            $formatted = array(
                'id'         => (int)$record->ID,
                'action'     => $record->action,
                'email'      => $record->email,
                'event_date' => \Utils::api_timestamp( $record->event_date ),
                'first_name' => $record->first_name,
                'id_job'     => (int)$record->id_job,
                'id_project' => (int)$record->id_project,
                'ip'         => $record->ip,
                'last_name'  => $record->last_name,
                'uid'        => (int)$record->uid
            );

            $out[] = $formatted ;

        }

        return $out;
    }

}