<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 18:02
 */

namespace Features\Dqf\Model;

use Features\Dqf;
use Features\Dqf\Service\MasterProject;
use Features\Dqf\Service\AbstractProjectFiles;
use Features\Dqf\Service\MasterProjectFiles;
use Features\Dqf\Service\ProjectMapping;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct;
use Projects_ProjectStruct;

class IntermediateRootProject {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    public function __construct( Projects_ProjectStruct $project ) {
        $this->project = $project ;
    }

    /**
     * @param UserModel $assignee
     *
     * @return CreateProjectResponseStruct[]
     */
    public function createWithAssignment( UserModel $assignee ) {
        $ownerSession = ( new UserModel($this->project->getOwner() ) )->getSession()->login() ;

        $mapping              = new ProjectMapping( $ownerSession, $this->project ) ;
        $mappingResponse      = $mapping->getRemoteId();
        $masterProjectService = new MasterProject($ownerSession) ;
        $masterProject        = $masterProjectService->getByDqfId( $mappingResponse ) ;

        $createProjectResponseStruct = ( new CreateProjectResponseStruct([
                'dqfId'   => $masterProject->id,
                'dqfUUID' => $masterProject->uuid
        ] ) ) ;

        $masterProjectFiles = new MasterProjectFiles($ownerSession, $createProjectResponseStruct ) ;
        $files = $masterProjectFiles->getFilesResponseStructs();

        $projects = [] ;
        $ids      = [] ;

        foreach( $this->project->getChunks() as $chunk ) {
            $childProject = new ChildProjectCreationModel( $createProjectResponseStruct, $chunk );

            $childProject->setOwnerSession( $ownerSession ) ;
            $childProject->setFiles( $files ) ;
            $childProject->setAssignee( $assignee ) ;

            $projects[] = $childProject->createForTranslation();
            $ids[]      = $childProject->getChildProjectRecordId() ;
        }

        // save into projects metadata the ids of the projects we just created
        $this->project->setMetadata(Dqf::INTERMEDIATE_PROJECT_METADATA_KEY, implode(',', $ids) );
        $this->project->setMetadata(Dqf::INTERMEDIATE_USER_METADATA_KEY, $assignee->getMateCatUser()->uid );

        return $projects ;
    }

}