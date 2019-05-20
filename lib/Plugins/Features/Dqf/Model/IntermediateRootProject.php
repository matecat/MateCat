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
use Features\Dqf\Service\MasterProjectFiles;
use Features\Dqf\Service\ProjectMapping;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Projects_ProjectStruct;

class IntermediateRootProject {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    /** @var UserModel  */
    protected $user ;

    public function __construct( UserModel $user, Projects_ProjectStruct $project ) {
        $this->project = $project ;
        $this->user = $user ;
    }

    /**
     * @return CreateProjectResponseStruct[]
     */
    public function create( ) {
        $ownerSession = ( new UserModel($this->project->getOriginalOwner() ) )->getSession()->login() ;
        $dqfProjectMap = ( new DqfProjectMapDao() )->getMasterByChunk( $this->project->getChunks()[0] );

        $mapping              = new ProjectMapping( $ownerSession, $dqfProjectMap ) ;
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

            $masterRecord = ( new DqfProjectMapDao() )->findRootProject( $chunk ) ;

            $childProject = new ChildProjectCreationModel( $masterRecord, $chunk, 'vendor_root' );

            $childProject->setUser( $this->user ) ;
            $childProject->setFiles( $files ) ;

            $projects[] = $childProject->create();
            $ids[]      = $childProject->getSavedRecord()->id ;
        }

        // save into projects metadata the ids of the projects we just created
        $this->project->setMetadata(Dqf::INTERMEDIATE_PROJECT_METADATA_KEY, implode(',', $ids) );
        $this->project->setMetadata(Dqf::INTERMEDIATE_USER_METADATA_KEY, $this->user->getMateCatUser()->uid );

        return $projects ;
    }

}