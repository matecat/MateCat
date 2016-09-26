<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:11
 */

namespace API\V2;

use API\V2\Json\ProjectUrls;
use API\V2\Validators\ProjectPasswordValidator ;

class UrlsController extends ProtectedKleinController {

    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    public function urls() {
        $projectData = getProjectData( $this->validator->getProject()->id );

        $formatted = new ProjectUrls( $projectData );

        $this->response->json( $formatted->render() );

    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    protected function afterConstruct() {
        $this->validator = new Validators\ProjectPasswordValidator( $this->request );
    }

}