<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 18:02
 */

namespace Features\Dqf\Model;

use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\MasterProject;
use Features\Dqf\Service\ProjectMapping;
use Projects_ProjectStruct;
use Users_UserDao;

class IntermediateRootProject {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    public function __construct( Projects_ProjectStruct $project ) {
        $this->project = $project ;
    }

    public function create() {
        $user = ( new Users_UserDao())->getByEmail($this->project->id_customer ) ;
        $dqf_user = new UserModel( $user )  ;
        $session = $dqf_user->getSession();

        $mapping = new ProjectMapping($session, $this->project) ;

        $remoteProjectId = $mapping->getRemoteId();

        // find MasterProject based on DQF id

        $masterProjectService = new MasterProject($session) ;

        $masterProject = $masterProjectService->getByDqfId( $remoteProjectId ) ;


    }

}