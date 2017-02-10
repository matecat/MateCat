<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 08/02/17
 * Time: 18.28
 *
 */

namespace Organizations;


class WorkspaceDao {

    const TABLE       = "workspaces";
    const STRUCT_TYPE = "WorkspaceStruct";

    protected static $auto_increment_fields = array( 'id' );
    protected static $primary_keys          = array( 'id' );

    protected static $_query_create_workspace = "
            INSERT INTO workspaces ( name, id_organization, options ) VALUES ( :name, :id_organization, :options );
        ";


    public function create( WorkspaceStruct $workSpace ) {


        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_query_create_workspace );

        $stmt->execute( [
                'name'            => $workSpace->name,
                'id_organization' => $workSpace->id_organization,
                'options'         => json_encode( $workSpace->options )
        ] );

        $workSpace->id = $conn->lastInsertId();

        return $workSpace;

    }


    public function update() {

    }


    public function delete() {

    }

}