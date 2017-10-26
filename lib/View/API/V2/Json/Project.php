<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace API\V2\Json;

use Constants_JobStatus;
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
     */
    public function renderItem( Projects_ProjectStruct $data ) {

        $jobs = $data->getJobs(); //cached

        $jobJSONs    = [];
        $jobStatuses = [];
        if ( !empty( $jobs ) ) {

            $jobJSON = new $this->jRenderer();
            foreach ( $jobs as $job ) {
                /**
                 * @var $jobJSON Job
                 */
                $jobJSONs[]    = $jobJSON->renderItem( $job );
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
                'remote_file_service'  => $data->getRemoteFileServiceName()
        ];

        return $projectOutputFields;

    }

    public function render() {
        $out = [];
        foreach ( $this->data as $membership ) {
            $out[] = $this->renderItem( $membership );
        }

        return $out;
    }

}