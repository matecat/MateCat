<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\IController;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use ReflectionException;

/**
 * User: gremorian
 * Date: 11/05/15
 * Time: 20.37
 *
 */
class ActivityLogController extends BaseKleinViewController implements IController {

    protected function afterConstruct(): void {
        $this->appendValidator( new ViewLoginRedirectValidator( $this ) );
        $this->appendValidator(
                ( new ProjectPasswordValidator( $this ) )->onFailure( function () {
                    $this->setView( "project_not_found.html", [], 404 );
                    $this->render();
                } )
        );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function renderView() {

        $request = $this->validateTheRequest();

        $activityLogDao                 = new ActivityLogDao();
        $activityLogDao->epilogueString = " LIMIT 1;";
        $rawLogContent                  = $activityLogDao->read(
                new ActivityLogStruct(),
                [ 'id_project' => $request[ 'id_project' ] ]
        );

        //NO ACTIVITY DATA FOR THIS PROJECT
        if ( empty( $rawLogContent ) ) {
            $this->setView( "activity_log_not_found.html", [
                    'projectID' => $request[ 'id_project' ],
            ] );
            $this->render();
        }

        $this->setView( 'activity_log.html', [
                'project_id' => $request[ 'id_project' ],
                'password'   => $request[ 'password' ],
        ] );
        $this->render();

    }

    protected function validateTheRequest() {

        $filterArgs = [
                'id_project' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'   => [
                        'filter' => FILTER_SANITIZE_SPECIAL_CHARS, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        return filter_var_array( $this->request->paramsNamed()->all(), $filterArgs );

    }

}