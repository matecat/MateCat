<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/12/16
 * Time: 12.13
 *
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use ReflectionException;
use View\API\V2\Json\Activity;

class ActivityLogController extends KleinController {

    /**
     * @throws Exception
     */
    public function allOnProject() {
        $validator = new ProjectPasswordValidator( $this );
        $validator->validate();

        $activityLogDao = new ActivityLogDao();
        $rawContent     = $activityLogDao->getAllForProject( $validator->getIdProject() );

        $formatted = new Activity( $rawContent );
        $this->response->json( $formatted->render() );
    }

    /**
     * @throws Exception
     */
    public function lastOnProject() {

        $validator = new ProjectPasswordValidator( $this );
        $validator->validate();

        $activityLogDao = new ActivityLogDao();
        $rawContent     = $activityLogDao->getLastActionInProject( $validator->getIdProject() );

        $formatted = new Activity( $rawContent );
        $this->response->json( [ 'activity' => $formatted->render() ] );

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function lastOnJob() {

        $validator = new ChunkPasswordValidator( $this );
        $validator->validate();

        $activityLogDao                  = new ActivityLogDao();
        $activityLogDao->whereConditions = ' id_job = :id_job ';
        $activityLogDao->epilogueString  = " ORDER BY ID DESC LIMIT 1";
        $rawLogContent                   = $activityLogDao->read(
                new ActivityLogStruct(),
                [ 'id_job' => $validator->getJobId() ]
        );

        $formatted = new Activity( $rawLogContent );
        $this->response->json( [ 'activity' => $formatted->render() ] );

    }

}