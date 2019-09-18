<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/09/2017
 * Time: 12:05
 */

namespace Features\Dqf\Model;


use Features\Dqf;
use Features\Dqf\Service\ChildProjectFiles;
use Features\Dqf\Service\MasterProjectFiles;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Users_UserDao;

class ProjectModel {

    /**
     * @var DqfProjectMapStruct
     */
    protected $project ;

    public function __construct( DqfProjectMapStruct $dqfProject ) {
        $this->project = $dqfProject ;
    }

    protected function getMateCatProject() {
        return $this->project->getChunk()->getProject();
    }

    /**
     * This method evaluates the presence of an intermediate vendor root project.
     * If one is found, the intermediate user is returned. Otherwise returns the user
     * for the root proejct.
     */
    public function getUserWithIntermediate() {
        $intermeiateUid =  $this->getMateCatProject()
                ->getMetadataValue(Dqf::INTERMEDIATE_USER_METADATA_KEY) ;

        if ( $intermeiateUid ) {
            $user = ( new Users_UserDao() )->getByUid( $intermeiateUid );
        }
        else {
            $user = $this->getMateCatProject()->getOriginalOwner();
        }
        return new UserModel( $user ) ;
    }

    public function getUserWithIntermediatex() {
        return $this->getUser();
    }

    /**
     * @return UserModel the DQF user assigend to the job
     */
    public function getUser() {
        return $this->project->getUser();
    }

    public function getResponseStruct() {
        return ( new CreateProjectResponseStruct([
                'dqfId'   => $this->project->dqf_project_id,
                'dqfUUID' => $this->project->dqf_project_uuid
        ] ) ) ;
    }

    public function getOwnerUser() {
       return new UserModel( $this->getMateCatProject()->getOriginalOwner() ) ;
    }

    public function isMaster() {
        return is_null( $this->project->dqf_parent_uuid ) ;
    }

    public function getFilesResponseStruct() {
        if ( $this->isMaster() ) {
            $object = new MasterProjectFiles(
                    $this->getUser()->getSession()->login(),
                    $this->getResponseStruct()
            ) ;
        }
        else {
            $object = new ChildProjectFiles(
                    $this->getUser()->getSession()->login(),
                    $this->getResponseStruct()
            ) ;
        }

        return $object->getFilesResponseStructs();
    }
}