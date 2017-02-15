<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace API\V2;


use API\App\AbstractStatefulKleinController;

use API\V2\Json\Project;
use API\V2\Validators\OrganizationProjectValidator;

class ProjectsController extends KleinController {


    /**
     * @var OrganizationProjectValidator
     */
    private $validator;

    public function index() {


    }

    public function update() {

        $acceptedFields = array( 'id_assignee', 'name', 'id_workspace' );
        $projectModel   = new \ProjectModel( $this->validator->project );

        $putParams = $this->getPutParams();
        foreach ( $acceptedFields as $field ) {
            if ( isset( $putParams[ $field ] ) ) {
                $projectModel->prepareUpdate( $field, $putParams[ $field ] );
            }
        }

        $updatedStruct = $projectModel->update();
        $formatted     = new Project();
        $this->response->json( array( 'project' => $formatted->renderItem( $updatedStruct ) ) );

    }

    public function validateRequest() {
        parent::validateRequest();
        $this->requireIdentifiedUser();

        $this->validator->validate( $this->user );
    }

    protected function afterConstruct() {
        parent::afterConstruct();

        $this->validator = new OrganizationProjectValidator( $this );

    }

}