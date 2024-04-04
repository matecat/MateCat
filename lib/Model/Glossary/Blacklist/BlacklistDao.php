<?php

namespace Glossary\Blacklist;

use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use DataAccess_IDaoStruct;
use Database;
use PDOStatement;
use ReflectionException;

class BlacklistDao extends DataAccess_AbstractDao
{
    const TABLE = 'blacklist_files';

    /**
     * @param $id
     *
     * @return int
     */
    public function deleteById($id){
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( " DELETE FROM ". self::TABLE ." WHERE id = ?");
        $stmt->execute( [$id] ) ;

        return $stmt->rowCount();
    }

    /**
     * @param     $jobId
     * @param     $password
     * @param int $ttl
     *
     * @return null|ShapelessConcreteStruct
     */
    public function getByJobIdAndPassword($jobId, $password, $ttl = 60){
        $thisDao = new self();
        $stmt = $this->_getStatementGetByJobIdAndPasswordForCache();

        /** @var  ShapelessConcreteStruct[] $result */
        $result = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'jid' => $jobId, 'password' => $password ] );
        return !empty( $result ) ? $result[ 0 ] : null;
    }

    /**
     *
     * @return PDOStatement
     */
    protected function _getStatementGetByJobIdAndPasswordForCache() {

        $conn = Database::obtain()->getConnection();

        return $conn->prepare( "SELECT * FROM ". self::TABLE ." where id_job = :jid AND password = :password ");
    }

    /**
     * @param $jobId
     * @param $password
     *
     * @return bool|int
     */
    public function destroyGetByJobIdAndPasswordCache( $jobId, $password ) {
        $stmt = $this->_getStatementGetByJobIdAndPasswordForCache();

        return $this->_destroyObjectCache( $stmt, [ 'jid' => $jobId, 'password' => $password ] );
    }

    /**
     * @param     $id
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct
     */
    public function getById($id, $ttl = 600){

        $thisDao = new self();
        $stmt = $this->_getStatementGetByIdForCache();

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct, [ 'id' => $id ] )[ 0 ];
    }

    /**
     *
     * @return PDOStatement
     */
    protected function _getStatementGetByIdForCache() {

        $conn = Database::obtain()->getConnection();

        return $conn->prepare( "SELECT * FROM ". self::TABLE ." where id = :id ");
    }

    /**
     * @param $id
     *
     * @return bool|int
     */
    public function destroyGetByIdCache( $id ) {
        $stmt = $this->_getStatementGetByIdForCache();

        return $this->_destroyObjectCache( $stmt, [ 'id' => $id ]);
    }

    /**
     * @param BlacklistStruct $blacklistStruct
     *
     * @return string|null
     * @throws ReflectionException
     */
    public function save( BlacklistStruct $blacklistStruct){

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( "
            INSERT INTO ". self::TABLE ." 
             ( `id_job`, `password`, `file_path`, `file_name`, `target`, `uid` )
             VALUES 
             (:id_job, :password, :file_path, :file_name, :target, :uid )
         ");

        $blacklistArray = $blacklistStruct->toPlainArray();
        $blacklistArray['uid'] = (!empty($blacklistArray['uid'])) ? $blacklistArray['uid'] : 0;
        unset($blacklistArray['id']); // we need to removed it here

        $stmt->execute( $blacklistArray ) ;

        if($stmt->rowCount() > 0){
            return $conn->lastInsertId();
        }

        return null;
    }
}