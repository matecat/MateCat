<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace View\API\V2\Json;

use Constants_JobStatus;
use Exception;
use Model\Analysis\Status;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use ReflectionException;
use Users_UserStruct;
use Utils;

class Project {

    /**
     * @var Job
     */
    protected Job $jRenderer;

    /**
     * @var ProjectStruct[]
     */
    protected array $data = [];

    /**
     * @var string|null
     */
    protected ?string $status = null;

    /**
     * @var bool
     */
    protected bool $called_from_api = false;

    /**
     * @var Users_UserStruct
     */
    protected Users_UserStruct $user;

    /**
     * @param Users_UserStruct $user
     *
     * @return $this
     */
    public function setUser( Users_UserStruct $user ): Project {
        $this->user = $user;

        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( bool $called_from_api ): Project {
        $this->called_from_api = $called_from_api;

        return $this;
    }

    /**
     * Project constructor.
     *
     * @param ProjectStruct[] $data
     * @param string|null     $search_status
     */
    public function __construct( array $data = [], ?string $search_status = null ) {

        $this->data   = $data;
        $this->status = $search_status;
        $jRendered    = new Job();

        if ( $search_status ) {
            $jRendered->setStatus( $search_status );
        }

        $this->jRenderer = $jRendered;
    }

    /**
     * @param       $project ProjectStruct
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    public function renderItem( ProjectStruct $project ): array {

        $featureSet = $project->getFeaturesSet();
        $jobs       = $project->getJobs( 60 * 10 ); //cached

        $jobJSONs    = [];
        $jobStatuses = [];
        if ( !empty( $jobs ) ) {

            /**
             * @var $jobJSON Job
             */
            $jobJSON = new $this->jRenderer();

            if ( !empty( $this->user ) ) {
                $jobJSON->setUser( $this->user );
            }

            if ( !empty( $this->status ) ) {
                $jobJSON->setStatus( $this->status );
            }

            if ( $this->called_from_api ) {
                $jobJSON->setCalledFromApi( true );
            }

            foreach ( $jobs as $job ) {

                // if status is set, then filter off the jobs by owner_status
                if ( $this->status ) {
                    if ( $job->status_owner === $this->status and !$job->isDeleted() ) {
                        $jobJSONs[]    = $jobJSON->renderItem( new JobStruct( $job->getArrayCopy() ), $project, $featureSet );
                        $jobStatuses[] = $job->status_owner;
                    }
                } else {
                    if ( !$job->isDeleted() ) {
                        $jobJSONs[]    = $jobJSON->renderItem( new JobStruct( $job->getArrayCopy() ), $project, $featureSet );
                        $jobStatuses[] = $job->status_owner;
                    }
                }
            }
        }

        $metadataDao = new MetadataDao();
        $projectInfo = $metadataDao->get( (int)$project->id, 'project_info' );
        $fromApi     = $metadataDao->get( (int)$project->id, 'from_api' );

        $_project_data  = ProjectDao::getProjectAndJobData( $project->id );
        $analysisStatus = new Status( $_project_data, $featureSet, $this->user );

        return [
                'id'                   => (int)$project->id,
                'password'             => $project->password,
                'name'                 => $project->name,
                'id_team'              => (int)$project->id_team,
                'id_assignee'          => (int)$project->id_assignee,
                'from_api'             => ( $fromApi->value ?? 0 ) == 1,
                'analysis'             => $analysisStatus->fetchData()->getResult(),
                'create_date'          => $project->create_date,
                'fast_analysis_wc'     => (int)$project->fast_analysis_wc,
                'standard_analysis_wc' => (int)$project->standard_analysis_wc,
                'tm_analysis_wc'       => $project->tm_analysis_wc,
                'project_slug'         => Utils::friendly_slug( $project->name ),
                'jobs'                 => $jobJSONs,
                'features'             => implode( ",", $featureSet->getCodes() ),
                'is_cancelled'         => ( in_array( Constants_JobStatus::STATUS_CANCELLED, $jobStatuses ) ),
                'is_archived'          => ( in_array( Constants_JobStatus::STATUS_ARCHIVED, $jobStatuses ) ),
                'remote_file_service'  => $project->getRemoteFileServiceName(),
                'due_date'             => Utils::api_timestamp( $project->due_date ),
                'project_info'         => ( null !== $projectInfo ) ? $projectInfo->value : null,
        ];
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function render(): array {

        $out = [];
        foreach ( $this->data as $project ) {
            $out[] = $this->renderItem( $project );
        }

        return $out;
    }

}