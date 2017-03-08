<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 18/01/2017
 * Time: 18:26
 */

namespace API\App;


use Exceptions\NotFoundError;
use Exceptions\ValidationError;

class ProjectController extends AbstractStatefulKleinController
{

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    /**
     * @var \Users_UserStruct
     */
    protected $user ;

    public function update() {

        if ( $this->request->param('name') ) {

            $this->project->name = $this->request->param('name');
            \Projects_ProjectDao::updateStruct( $this->project ) ;

        }

        $this->response->json( $this->project->toArray( ) ) ;
    }

    protected function validateRequest()
    {
        parent::validateRequest();

        $this->project =  \Projects_ProjectDao::findById( $this->request->param('id_project'));

        $dao = new \Users_UserDao();
        $this->user = $dao->getByUid( $_SESSION['uid'] ) ;

        if ( !$this->user ) {
            throw new \API\V2\ValidationError("user session not found");
        }

        if ( !$this->project ) {
            throw new NotFoundError("Project not found") ;
        }

        if ( $this->project->id_customer != $this->user->email ) {
            throw new \API\V2\ValidationError("user is not allowed to update this resource");
        }

    }


}