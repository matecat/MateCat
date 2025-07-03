<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/12/16
 * Time: 19.16
 *
 */

namespace View\API\V2\Json;


use Model\ActivityLog\ActivityLogStruct;
use Model\FeaturesBase\FeatureSet;

class Activity {
    /**
     * @var ActivityLogStruct[]
     */
    private array $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render(): array {
        $out = [];

        $featureSet = new FeatureSet();

        foreach ( $this->data as $record ) {

            $record->action = $record->getAction( $record->action );
            if ( empty( $record->email ) ) {
                $record->first_name = "Anonymous";
                $record->last_name  = "User";
                $record->email      = "Unknown";
            }

            $record = $featureSet->filter( 'filterActivityLogEntry', $record );

            $formatted = [
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
            ];

            $out[] = $formatted;

        }

        return $out;
    }

}