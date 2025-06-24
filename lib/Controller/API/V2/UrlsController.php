<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:11
 */

namespace Controller\API\V2;

use API\V2\Json\ProjectUrls;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use DataAccess\ShapelessConcreteStruct;
use Exception;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use Projects_ProjectDao;
use ReflectionException;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

class UrlsController extends KleinController {

    /**
     * @var ProjectPasswordValidator
     */
    private ProjectPasswordValidator $validator;

    /**
     * @throws Exception
     */
    public function urls() {

        $this->featureSet->loadForProject( $this->validator->getProject() );

        // @TODO is correct here?
        $jobCheck = 0;
        foreach ( $this->validator->getProject()->getJobs() as $job ) {
            if ( !$job->isDeleted() ) {
                $jobCheck++;
            }
        }

        if ( $jobCheck === 0 ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'errors' => [
                            'code'    => 0,
                            'message' => 'No project found.'
                    ]
            ] );
            exit();
        }

        $projectData = ( new Projects_ProjectDao() )->setCacheTTL( 60 * 60 )->getProjectData( $this->validator->getProject()->id );

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