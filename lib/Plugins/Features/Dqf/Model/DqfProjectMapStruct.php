<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 15:25
 */

namespace Features\Dqf\Model;

use Chunks_ChunkDao;
use Chunks_ChunkStruct;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use Users_UserDao;

class DqfProjectMapStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {
    public $id ;
    public $id_job ;

    public $first_segment ;
    public $last_segment ;
    public $password ;

    public $dqf_project_id ;
    public $dqf_project_uuid ;
    public $dqf_parent_uuid ;

    public $project_type ;
    public $uid ;

    public $archive_date ;
    public $create_date ;

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk() {
        return $this->cachable(__METHOD__, $this, function($obj) {
            return Chunks_ChunkDao::getByIdAndPassword($obj->id_job, $obj->password );
        });
    }

    /**
     * @return UserModel
     */
    public function getUser() {
        $matecatUser = (new Users_UserDao())->getByUid( $this->uid );
        return ( new UserModel( $matecatUser ) );
    }

}