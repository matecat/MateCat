<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 08/02/17
 * Time: 18.28
 *
 */

namespace Organizations;


class WorkspaceDao extends \DataAccess_AbstractDao {

    const TABLE       = "workspaces";
    const STRUCT_TYPE = "WorkspaceStruct";

    protected static $auto_increment_fields = array( 'id' );
    protected static $primary_keys          = array( 'id' );

    protected static $_query_create_workspace = "
            INSERT INTO workspaces ( name, id_organization, options ) VALUES ( :name, :id_organization, :options );
        ";

    protected static $_query_organization_workspaces = "
            SELECT * FROM workspaces WHERE id_organization = :id_organization
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

    public function getByOrganizationId( $orgId ){

        $stmt = $this->_getStatementForCache( self::$_query_organization_workspaces );
        $workSpaceQuery = new WorkspaceStruct();
        return ( $this->_fetchObject( $stmt,
                $workSpaceQuery,
                array(
                        'id_organization' => $orgId,
                )
        ) );

    }

    public function destroyCacheForOrganizationId( $orgId ){
        $stmt = $this->_getStatementForCache( self::$_query_organization_workspaces );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_organization' => $orgId,
                )
        );
    }

    public function getById(){
        
    }

}