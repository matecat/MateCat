<?php

namespace API\V2  ;

/**
 * @deprecated use Validators\ChunkPasswordValidator
 */

use Projects_ProjectDao;
use API\V2\Json\Project;

class ProjectRenameController extends KleinController {

    /**
     * @var \Projects_ProjectStruct
     */
    private $project;

    public function rename() {

        $this->project = Projects_ProjectDao::findByIdAndPassword(
            $this->request->paramsNamed()->id_project,
            $this->request->paramsNamed()->password
        );

        $pDao = new Projects_ProjectDao();
        $this->project = $pDao->updateField( $this->project, 'name', $this->request->param( 'name' ) );

        $projectFormatted = new Project( $this->project );
        $this->response->json( array( 'project' => $projectFormatted->render() ) );

    }

}
