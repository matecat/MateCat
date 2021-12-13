<?php

namespace Glossary\Blacklist;

use DataAccess\ShapelessConcreteStruct;

class BlacklistDao extends \DataAccess_AbstractDao
{
    const TABLE = 'blacklist_files';

    /**
     * @param     $jobId
     * @param     $password
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getByJobIdAndPassword($jobId, $password, $ttl = 60){
        $thisDao = new self();
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM ". self::TABLE ." where id_job = :jid AND password = :password ");

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'jid' => $jobId, 'password' => $password ] )[ 0 ];
    }

    /**
     * @param     $uid
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getByUid($uid, $ttl = 60){
        $thisDao = new self();
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM ". self::TABLE ." where uid = :uid ");

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'uid' => $uid ] )[ 0 ];
    }

    /**
     * @param     $id
     * @param int $ttl
     *
     * @return \DataAccess_IDaoStruct
     */
    public function getById($id, $ttl = 600){
        $thisDao = new self();
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM ". self::TABLE ." where id = :id ");

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct, [ 'id' => $id ] )[ 0 ];
    }

    /**
     * @param BlacklistModel $blacklistStruct
     *
     * @return int
     */
    public function save( BlacklistModel $blacklistStruct){

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare( "
            INSERT INTO ". self::TABLE ." 
             ( `id_job`, `password`, `file_path`, `file_name`, `target` )
             VALUES 
             (:id_job, :password, :file_path, :file_name, :target)
         ");

        $stmt->execute( [
            'id_job' => $blacklistStruct->chunk->id,
            'password' => $blacklistStruct->chunk->password,
            'file_path' => $blacklistStruct->file_path,
            'file_name' => $blacklistStruct->file_name,
            'target' => $blacklistStruct->target,
        ] ) ;

        return $stmt->rowCount();
    }

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
}