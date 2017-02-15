<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 08/02/17
 * Time: 18.28
 *
 */

namespace Organizations;


use Database;
use PDO;

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

    protected static $_query_workspace_by_id = "
            SELECT * FROM workspaces WHERE id = :id 
    ";

    protected static $_query_update_workspace = "
        UPDATE workspaces SET name = :name , options = :options WHERE id = :id
    ";

    protected static $_query_delete_workspace = "
        DELETE FROM workspaces WHERE id = :id
    ";

    /**
     * @param \DataAccess_IDaoStruct|WorkspaceStruct $workSpace
     *
     * @return \DataAccess_IDaoStruct
     */
    public function create( \DataAccess_IDaoStruct $workSpace ) {


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

    /**
     * @param \DataAccess_IDaoStruct|WorkspaceStruct $wSpace
     *
     * @return WorkspaceStruct
     */
    public function update( \DataAccess_IDaoStruct $wSpace ) {

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( self::$_query_update_workspace );
        $stmt->bindValue( ':id', $wSpace->id, PDO::PARAM_INT );
        $stmt->bindValue( ':name', $wSpace->name, PDO::PARAM_STR );
        $stmt->bindValue( ':options', $wSpace->options, PDO::PARAM_STR );
        $stmt->execute();

        return $wSpace;

    }


    /**
     * @param \DataAccess_IDaoStruct|WorkspaceStruct $workspace
     *
     * @return int
     */
    public function delete( \DataAccess_IDaoStruct $workspace ) {

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_query_delete_workspace );
        $stmt->execute( [
                'id' => $workspace->id,
        ] );
        return $stmt->rowCount();

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

    /**
     * Destroy cache for @see WorkspaceDao::getByOrganizationId()
     *
     * @param $orgId
     *
     * @return bool|int
     */
    public function destroyCacheForOrganizationId( $orgId ){
        $stmt = $this->_getStatementForCache( self::$_query_organization_workspaces );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_organization' => $orgId,
                )
        );
    }

    /**
     * @param $wId
     *
     * @return WorkspaceStruct|null
     */
    public function getById( $wId ){
        $stmt = $this->_getStatementForCache( self::$_query_workspace_by_id );
        $workSpaceQuery = new WorkspaceStruct();
        return static::resultOrNull( $this->_fetchObject( $stmt,
                $workSpaceQuery,
                array(
                        'id' => $wId,
                )
        )[ 0 ] );
    }

    /**
     * Destroy cache for @see WorkspaceDao::getById()
     *
     * @param $wId
     *
     * @return bool|int
     */
    public function destroyCacheById( $wId ){
        $stmt = $this->_getStatementForCache( self::$_query_workspace_by_id );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id' => $wId,
                )
        );
    }

}