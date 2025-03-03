<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:11
 */

namespace API\V2;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use API\Commons\Validators\ProjectPasswordValidator;
use API\V2\Json\ProjectUrls;
use DataAccess\ShapelessConcreteStruct;

class UrlsController extends KleinController {

    /**
     * @var ProjectPasswordValidator
     */
    private $validator;

    public function urls() {

        $this->featureSet->loadForProject( $this->validator->getProject() );

        // @TODO is correct here?
        $jobCheck = 0;
        foreach ($this->validator->getProject()->getJobs() as $job){
            if (!$job->isDeleted()) {
                $jobCheck++;
            }
        }

        if($jobCheck === 0){
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                'errors' => [
                        'code' => 0,
                        'message' => 'No project found.'
                ]
            ] );
            exit();
        }

        /**
         * @var $projectData ShapelessConcreteStruct[]
         */
        $projectData = ( new \Projects_ProjectDao() )->setCacheTTL( 60 * 60 )->getProjectData( $this->validator->getProject()->id );

        $formatted = new ProjectUrls( $projectData );

        $formatted = $this->featureSet->filter( 'projectUrls', $formatted );

        $this->response->json( [ 'urls' => $formatted->render() ] );

    }

    protected function validateRequest() {
        $this->validator->validate();
    }

    protected function afterConstruct() {
        $this->validator = new ProjectPasswordValidator( $this );
        $this->appendValidator( new LoginValidator( $this ) );
    }

}