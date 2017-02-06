<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/02/2017
 * Time: 17:36
 */

namespace API\V2\Json;


use Organizations\OrganizationStruct;

class Organization {

    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render( $data = null ) {
        $out = array();

        if ( empty( $data ) ) {
            $data = $this->data;
        }

        foreach ( $data as $organization ) {
            /**
             * @var $organization OrganizationStruct
             */
            $row   = array(
                    'id'         => (int)$organization->id,
                    'name'       => $organization->name,
                    'type'       => $organization->type,
                    'created_at' => \Utils::api_timestamp( $organization->created_at ),
                    'created_by' => $organization->created_by
            );
            $out[] = $row;
        }

        return $out;
    }


}