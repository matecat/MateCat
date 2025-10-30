<?php

namespace Controller\API\V2;

use ArrayObject;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\ProjectManager\ProjectManager;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use ReflectionException;

class SplitJobController extends KleinController {

    protected function afterConstruct(): void {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function merge(): void {

        $request          = $this->validateTheRequest();
        $projectStructure = $this->getProjectData(
                $request[ 'project_id' ],
                $request[ 'project_pass' ],
                $request[ 'split_raw_words' ]
        );

        /** @var  $pStruct ArrayObject */
        $pStruct = $projectStructure[ 'pStruct' ];
        /** @var  $pManager ProjectManager */
        $pManager = $projectStructure[ 'pManager' ];
        /** @var $project ProjectStruct */
        $project = $projectStructure[ 'project' ];

        $jobStructs                = $this->checkMergeAccess( $request[ 'job_id' ], $project->getJobs() );
        $pStruct[ 'job_to_merge' ] = $request[ 'job_id' ];
        $pManager->mergeALL( $pStruct, $jobStructs );

        $this->response->json( [
                "data" => $pStruct[ 'split_result' ]
        ] );

    }

    /**
     * @throws Exception
     */
    public function check(): void {

        $request = $this->validateTheRequest();

        if ( empty( $request[ 'job_pass' ] ) ) {
            throw new InvalidArgumentException( "No job password provided", -4 );
        }

        /** @var  $pManager ProjectManager */
        /** @var  $pStruct ArrayObject */
        [ , $pStruct ] = $this->checkSplit( $request );

        $this->response->json( [
                "data" => $pStruct[ 'split_result' ]
        ] );

    }

    /**
     * @throws Exception
     */
    public function apply(): void {

        $request = $this->validateTheRequest();

        if ( empty( $request[ 'job_pass' ] ) ) {
            throw new InvalidArgumentException( "No job password provided", -4 );
        }

        /** @var  $pManager ProjectManager */
        /** @var  $pStruct ArrayObject */
        [ $pManager, $pStruct ] = $this->checkSplit( $request );
        $pManager->applySplit( $pStruct );

        $this->response->json( [
                "data" => $pStruct[ 'split_result' ]
        ] );

    }

    /**
     * @throws Exception
     */
    private function checkSplit( array $request ): array {

        $projectStructure = $this->getProjectData(
                $request[ 'project_id' ],
                $request[ 'project_pass' ],
                $request[ 'split_raw_words' ]
        );

        /** @var  $pStruct ArrayObject */
        $pStruct = $projectStructure[ 'pStruct' ];
        /** @var  $pManager ProjectManager */
        $pManager = $projectStructure[ 'pManager' ];
        /** @var $project ProjectStruct */
        $project    = $projectStructure[ 'project' ];
        $count_type = $projectStructure[ 'count_type' ];

        $this->checkSplitAccess( $project, $request[ 'job_id' ], $request[ 'job_pass' ], $project->getJobs() );

        $pStruct[ 'job_to_split' ]      = $request[ 'job_id' ];
        $pStruct[ 'job_to_split_pass' ] = $request[ 'job_pass' ];

        $pManager->getSplitData( $pStruct, $request[ 'num_split' ], $request[ 'split_values' ], $count_type );

        return [ $pManager, $pStruct ];

    }

    /**
     * Compatibility between the v2/v3 (api_v2_routes.php) API and the internal API obtained through the Elvis operator.
     * This covers the differences in the named parameters.
     * @return array
     */
    private function validateTheRequest(): array {

        $project_id = filter_var( $this->request->param( 'project_id' ), FILTER_SANITIZE_NUMBER_INT ) ?:
                filter_var( $this->request->param( 'id_project' ), FILTER_SANITIZE_NUMBER_INT );

        $project_pass = filter_var( $this->request->param( 'project_pass' ), FILTER_SANITIZE_SPECIAL_CHARS, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] ) ?:
                filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_SPECIAL_CHARS, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        $job_id = filter_var( $this->request->param( 'job_id' ), FILTER_SANITIZE_NUMBER_INT ) ?:
                filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );

        $job_pass = filter_var( $this->request->param( 'job_pass' ), FILTER_SANITIZE_SPECIAL_CHARS, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] ) ?:
                filter_var( $this->request->param( 'job_password' ), FILTER_SANITIZE_SPECIAL_CHARS, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        $split_raw_words = filter_var( $this->request->param( 'split_raw_words' ), FILTER_VALIDATE_BOOLEAN );
        $num_split       = filter_var( $this->request->param( 'num_split' ), FILTER_SANITIZE_NUMBER_INT );
        $split_values    = is_array( $this->request->param( 'split_values' ) ) ? filter_var_array( $this->request->param( 'split_values' ), FILTER_SANITIZE_NUMBER_INT ) : [];

        if ( empty( $project_id ) ) {
            throw new InvalidArgumentException( "No id project provided", -1 );
        }

        if ( empty( $project_pass ) ) {
            throw new InvalidArgumentException( "No project password provided", -2 );
        }

        if ( empty( $job_id ) ) {
            throw new InvalidArgumentException( "No id job provided", -3 );
        }

        return [
                'project_id'      => (int)$project_id,
                'project_pass'    => $project_pass,
                'job_id'          => (int)$job_id,
                'job_pass'        => $job_pass,
                'split_raw_words' => $split_raw_words,
                'num_split'       => (int)$num_split,
                'split_values'    => $split_values,
        ];
    }

    /**
     * @param int    $project_id
     * @param string $project_pass
     * @param bool   $split_raw_words
     *
     * @return array
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    private function getProjectData( int $project_id, string $project_pass, bool $split_raw_words = false ): array {
        $count_type     = $split_raw_words ? MetadataDao::SPLIT_RAW_WORD_TYPE : MetadataDao::SPLIT_EQUIVALENT_WORD_TYPE;
        $project_struct = ProjectDao::findByIdAndPassword( $project_id, $project_pass, 60 * 60 );

        $pManager = new ProjectManager();
        $pManager->setProjectAndReLoadFeatures( $project_struct );

        $pStruct = $pManager->getProjectStructure();

        return [
                'pStruct'    => $pStruct,
                'pManager'   => $pManager,
                'count_type' => $count_type,
                'project'    => $project_struct,
        ];
    }

    /**
     * @param int         $jid
     * @param JobStruct[] $jobList
     *
     * @return JobStruct[]
     * @throws Exception
     */
    private function checkMergeAccess( int $jid, array $jobList ): array {
        return $this->filterJobsById( $jid, $jobList );
    }

    /**
     * @param ProjectStruct $project_struct
     * @param int           $jid
     * @param string        $job_pass
     * @param array         $jobList
     *
     * @throws AuthenticationError
     */
    private function checkSplitAccess( ProjectStruct $project_struct, int $jid, string $job_pass, array $jobList ) {

        $jobToSplit = $this->filterJobsById( $jid, $jobList );

        if ( array_shift( $jobToSplit )->password != $job_pass ) {
            throw new InvalidArgumentException( "Access denied", -10 );
        }

        $project_struct->getFeaturesSet()->run( 'checkSplitAccess', $jobList );
    }

    /**
     * @param int   $jid
     * @param array $jobList
     *
     * @return array
     * @throws AuthenticationError
     */
    private function filterJobsById( int $jid, array $jobList ): array {

        $filteredJobs = array_values( array_filter( $jobList, function ( JobStruct $jobStruct ) use ( $jid ) {
            return $jobStruct->id == $jid and !$jobStruct->isDeleted();
        } ) );

        if ( empty( $filteredJobs ) ) {
            throw new AuthenticationError( "Access denied", -10 );
        }

        return $filteredJobs;
    }

}
