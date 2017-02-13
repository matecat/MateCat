<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 15:01
 */

namespace API\V2\Json;


use Organizations\MembershipStruct;

class Membership {

    protected $data ;

    public function __construct($data)
    {
        $this->data = $data  ;
    }

    public function renderItem(MembershipStruct $membership ) {
        $out = array(
            'id' => $membership->id,
            'id_organization' => $membership->id_organization,
        );

        if ( !is_null( $membership->getUser() ) ) {
            $out['user'] = User::renderItem( $membership->getUser() ) ;
        }

        return $out;
    }

    public function render() {
        $out = [] ;
        foreach($this->data as $membership) {
            $out[] = $this->renderItem( $membership );
        }
        return $out ;
    }


}