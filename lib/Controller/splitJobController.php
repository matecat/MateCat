<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 20/11/13
 * Time: 11.32
 *
 */


class splitJobController extends ajaxController {

    private $exec;
    private $project_id;
    private $project_pass;
    private $job_id;
    private $job_pass;
    private $num_split;
    private $split_values;
    private $split_raw_words;

    private $project_data;

    /**
     * @var \Projects_ProjectStruct
     *
     * This is the new variable to use to store all data for the project. This should be
     * used instead of the data provided by `queries.php`.
     */
    private $project_struct;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $this->setUserCredentials();

        $filterArgs = array(
                'exec'         => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'project_id'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'project_pass' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'job_id'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'job_pass'     => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'split_raw_words' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
                'num_split'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'split_values'    => array( 'filter' => FILTER_CALLBACK, 'options' => array( 'self', 'valuesToInt' ) ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->exec            = $__postInput[ 'exec' ];
        $this->project_id      = $__postInput[ 'project_id' ];
        $this->project_pass    = $__postInput[ 'project_pass' ];
        $this->job_id          = $__postInput[ 'job_id' ];
        $this->job_pass        = $__postInput[ 'job_pass' ];
        $this->num_split       = $__postInput[ 'num_split' ];
        $this->split_values    = $__postInput[ 'split_values' ];
        $this->split_raw_words = $__postInput[ 'split_raw_words' ];
    }

    protected function valuesToInt( $float_val ) {
        return (int)$float_val;
    }

    public function doAction() {

        try {
            $count_type = ($this->split_raw_words == true) ? Projects_MetadataDao::SPLIT_RAW_WORD_TYPE : Projects_MetadataDao::SPLIT_EQUIVALENT_WORD_TYPE;
            $this->project_struct = \Projects_ProjectDao::findByIdAndPassword( $this->project_id, $this->project_pass, 60 * 60 );

            $pManager = new ProjectManager();

            if ( $this->user ) {
                $projectStructure[ 'userIsLogged' ] = true;
                $projectStructure[ 'uid' ]          = $this->user->getUid();
            }

            $pManager->setProjectAndReLoadFeatures( $this->project_struct );

            $pStruct = $pManager->getProjectStructure();

            switch ( $this->exec ) {
                case 'merge':
                    $jobStructs = $this->checkMergeAccess( $this->project_struct->getJobs() );
                    $pStruct[ 'job_to_merge' ] = $this->job_id;
                    $pManager->mergeALL( $pStruct, $jobStructs );
                    break;
                case 'check':
                    $this->checkSplitAccess( $this->project_struct->getJobs() );

                    $pStruct[ 'job_to_split' ]      = $this->job_id;
                    $pStruct[ 'job_to_split_pass' ] = $this->job_pass;

                    $pManager->getSplitData( $pStruct, $this->num_split, $this->split_values, $count_type );
                    break;
                case 'apply':
                    $this->checkSplitAccess( $this->project_struct->getJobs() );

                    $pStruct[ 'job_to_split' ]       = $this->job_id;
                    $pStruct[ 'job_to_split_pass' ]  = $this->job_pass;

                    $pManager->getSplitData( $pStruct, $this->num_split, $this->split_values, $count_type );
                    $pManager->applySplit( $pStruct );
                    break;

            }

            $this->result[ "data" ] = $pStruct[ 'split_result' ];

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }


    }

    /**
     * @param Jobs_JobStruct[] $jobList
     *
     * @return Jobs_JobStruct[]
     * @throws Exception
     */
    protected function checkMergeAccess( array $jobList ) {

        return $this->filterJobsById( $jobList );

    }

    protected function checkSplitAccess( array $jobList ) {

        $jobToSplit = $this->filterJobsById( $jobList );

        if ( array_shift( $jobToSplit )->password != $this->job_pass ) {
            throw new Exception( "Wrong Password. Access denied", -10 );
        }

        $this->project_struct->getFeaturesSet()->run('checkSplitAccess', $jobList ) ;
    }

    protected function filterJobsById(  array $jobList  ){

        $found = false;
        $jid   = $this->job_id;
        $filteredJobs = array_values( array_filter( $jobList, function ( Jobs_JobStruct $jobStruct ) use ( &$found, $jid ) {
            return $jobStruct->id == $jid and !$jobStruct->wasDeleted();
        } ) );

        if ( empty( $filteredJobs ) ) {
            throw new Exception( "Access denied", -10 );
        }

        return $filteredJobs;
    }

}
