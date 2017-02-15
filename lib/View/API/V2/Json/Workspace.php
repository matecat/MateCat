<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 14/02/2017
 * Time: 15:50
 */

namespace API\V2\Json;


use Organizations\WorkspaceStruct;

class Workspace
{

    public static function renderItem( WorkspaceStruct $workspace ) {
        return array(
            'id'              => (int) $workspace->id,
            'name'            => $workspace->name,
            'id_organization' => (int) $workspace->id_organization
        );
    }

    public function render( $data ) {
        $out = [] ;

        foreach( $data as $workspace ) {
            $out[] = self::renderItem( $workspace ) ;
        }

        return $out ;


    }

}