<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 08/04/16
 * Time: 23:55
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use Model\Jobs\JobDao;
use Model\ProjectManager\ProjectManager;
use Model\Projects\ProjectStruct;


class JobMergeController extends KleinController {

    private ProjectStruct $project;
    private array         $jobList = [];

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function merge() {

        $pManager = new ProjectManager();
        $pManager->setProjectAndReLoadFeatures( $this->project );

        $pStruct                   = $pManager->getProjectStructure();
        $pStruct[ 'id_customer' ]  = $this->project->id_customer;
        $pStruct[ 'job_to_merge' ] = (int)$this->request->param( 'id_job' );

        $pManager->mergeALL( $pStruct, $this->jobList );

        $this->response->code( 200 );
        $this->response->json( [ 'success' => true ] );
    }

    /**
     * Handles the initialization process after object construction by setting up
     * project validation and appending the necessary validators.
     *
     * This method performs the following steps:
     * - Creates a `ProjectPasswordValidator` instance to validate the project password.
     * - Defines a success callback for the password validator:
     *   - On success, retrieves and assigns the validated project to the `$project` property.
     *   - Retrieves the job list associated with the project using the job ID from the request.
     *   - Validates the first job in the list to ensure it belongs to the project and is not deleted.
     *     Throws a `NotFoundException` if validation fails.
     * - Appends a `LoginValidator` and the `ProjectPasswordValidator` to the list of validators.
     *
     * @return void
     */
    protected function afterConstruct(): void {
        // Initialize the project password validator.
        $validator = new ProjectPasswordValidator( $this );

        // Define the success callback for the password validator.
        $validator->onSuccess( function () use ( $validator ) {
            // Assign the validated project to the $project property.
            $this->project = $validator->getProject();

            // Retrieve the job list associated with the project.
            $this->jobList = JobDao::getById( (int)$this->request->param( 'id_job' ) );

            // Validate the first job in the list.
            $firstChunk = $this->jobList[ 0 ] ?? null;
            if ( !$firstChunk || $firstChunk->id_project != $this->project->id || $firstChunk->isDeleted() ) {
                throw new NotFoundException();
            }
        } );

        // Append the login validator to the list of validators.
        $this->appendValidator( new LoginValidator( $this ) );

        // Append the project password validator to the list of validators.
        $this->appendValidator( $validator );
    }

}