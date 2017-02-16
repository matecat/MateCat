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

    public function __construct( $data = null ) {
        $this->data = $data;
    }

    public function renderItem( OrganizationStruct $organization ) {
        $row  = [
            'id'         => (int) $organization->id,
            'name'       => $organization->name,
            'type'       => $organization->type,
            'created_at' => \Utils::api_timestamp( $organization->created_at ),
            'created_by' => (int) $organization->created_by
        ];

        $members = $organization->getMembers();
        if( !empty( $members ) ){
            $row[ 'members' ] = $members ;
        }

        return $row ;
    }

    public function render( $data = null ) {
        $out = array();

        if ( empty( $data ) ) {
            $data = $this->data;
        }

        /**
         * @var $data OrganizationStruct[]
         */
        foreach ( $data as $k => $organization ) {
            $out[] = $this->renderItem( $organization ) ;
        }

        return $out;
    }


}