<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace API\V2\Json;

use Chunks_ChunkStruct;
use Constants_JobStatus;
use DataAccess\ShapelessConcreteStruct;
use Projects_ProjectStruct;
use Utils;

class Project {

    /**
     * @var Job
     */
    protected $jRenderer;

    /**
     * @var Projects_ProjectStruct[]
     */
    protected $data = [];

    /**
     * @var bool
     */
    protected $called_from_api = false;

    /**
     * @var \Users_UserStruct
     */
    protected $user;

    /**
     * @param \Users_UserStruct $user
     *
     * @return $this
     */
    public function setUser( $user ) {
        $this->user = $user;
        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( $called_from_api ) {
        $this->called_from_api = (bool)$called_from_api;

        return $this;
    }

    /**
     * Project constructor.
     *
     * @param Projects_ProjectStruct[] $data
     */
    public function __construct( array $data = [] ) {
        $this->data = $data;
        $this->jRenderer = new Job();
    }

    /**
     * @param       $data Projects_ProjectStruct
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundError
     */
    public function renderItem( Projects_ProjectStruct $data ) {

        $jobs = $data->getJobs(60 * 10 ); //cached

        $jobJSONs    = [];
        $jobStatuses = [];
        if ( !empty( $jobs ) ) {

            /**
             * @var $jobJSON Job
             */
            $jobJSON = new $this->jRenderer();

            if( !empty( $this->user ) ){
                $jobJSON->setUser( $this->user );
            }

            if( $this->called_from_api ) {
                $jobJSON->setCalledFromApi( true );
            }

            foreach ( $jobs as $job ) {
                /**
                 * @var $jobJSON Job
                 */
                $jobJSONs[]    = $jobJSON->renderItem( new Chunks_ChunkStruct( $job->getArrayCopy() ) );
                $jobStatuses[] = $job->status_owner;
            }

        }

        $projectOutputFields = [
                'id'                   => (int)$data->id,
                'password'             => $data->password,
                'name'                 => $data->name,
                'id_team'              => (int)$data->id_team,
                'id_assignee'          => (int)$data->id_assignee,
                'create_date'          => $data->create_date,
                'fast_analysis_wc'     => (int)$data->fast_analysis_wc,
                'standard_analysis_wc' => (int)$data->standard_analysis_wc,
                'project_slug'         => Utils::friendly_slug( $data->name ),
                'jobs'                 => $jobJSONs,
                'features'             => implode( ",", $data->getFeatures()->getCodes() ),
                'is_cancelled'        => ( in_array( Constants_JobStatus::STATUS_CANCELLED, $jobStatuses ) ),
                'is_archived'         => ( in_array( Constants_JobStatus::STATUS_ARCHIVED, $jobStatuses ) ),
                'remote_file_service'  => $data->getRemoteFileServiceName(),
                'due_date'             => Utils::api_timestamp( $data->due_date )
        ];

        return $projectOutputFields;

    }

    /**
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundError
     */
    public function render() {
        $out = [];
        foreach ( $this->data as $membership ) {
            $out[] = $this->renderItem( $membership );
        }

        return $out;
    }

}