<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 20/11/13
 * Time: 11.32
 * 
 */

include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';
include_once INIT::$UTILS_ROOT . '/RecursiveArrayObject.php';
include_once INIT::$UTILS_ROOT . '/ProjectManager.php';
include_once INIT::$UTILS_ROOT . '/CatUtils.php';
include_once INIT::$UTILS_ROOT . '/Log.php';

class splitJobController extends ajaxController {

    private $exec;
    private $project_id;
    private $project_pass;
    private $job_id;
    private $job_pass;
    private $num_split;
    private $split_values;

    private $project_data;

    /**
     * @var \Projects_ProjectStruct
     *
     * This is the new variable to use to store all data for the project. This should be
     * used instead of the data provided by `queries.php`.
     */
    private $project_struct ;

    public function __construct() {

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        $filterArgs = array(
            'exec'         => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'project_id'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'project_pass' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'job_id'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'job_pass'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'num_split'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'split_values' => array( 'filter' => FILTER_CALLBACK, 'options' => array( 'self', 'valuesToInt' ) ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->exec         = $__postInput[ 'exec' ];
        $this->project_id   = $__postInput[ 'project_id' ];
        $this->project_pass = $__postInput[ 'project_pass' ];
        $this->job_id       = $__postInput[ 'job_id' ];
        $this->job_pass     = $__postInput[ 'job_pass' ];
        $this->num_split    = $__postInput[ 'num_split' ];
        $this->split_values = $__postInput[ 'split_values' ];

        $this->project_struct = \Projects_ProjectDao::findById( $__postInput['project_id'] ) ;
    }

    protected function valuesToInt( $float_val ){
        return (int)$float_val;
    }

    public function doAction() {

        $this->project_data = getProjectJobData( $this->project_id );

        try {

            if( empty( $this->project_data ) ){
                throw new Exception( "No Project Found.", -1 );
            }

            $pManager = new ProjectManager();
            $pManager->setProjectIdAndLoadProject( $this->project_struct->id );

            $pStruct = $pManager->getProjectStructure();
            $pStruct['id_customer'] = $this->project_struct->id_customer ;

            switch ( $this->exec ) {
                case 'merge':
                    $this->checkMergeAccess();
                    $pStruct[ 'job_to_merge' ]      = $this->job_id;
                    $pManager->mergeALL( $pStruct );
                    break;
                case 'check':
                    $this->checkSplitAccess();

                    $pStruct[ 'job_to_split' ]      = $this->job_id;
                    $pStruct[ 'job_to_split_pass' ] = $this->job_pass;

                    $pManager->getSplitData( $pStruct, $this->num_split, $this->split_values );
                    break;
                case 'apply':
                    $this->checkSplitAccess();

                    $pStruct[ 'job_to_split' ]      = $this->job_id;
                    $pStruct[ 'job_to_split_pass' ] = $this->job_pass;

                    $pManager->getSplitData( $pStruct, $this->num_split, $this->split_values );
                    $pManager->applySplit( $pStruct );
                    break;

            }

            $this->result["data"] = $pStruct['split_result'];

        } catch ( Exception $e ){
            $this->result['errors'][] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
        }


    }

    protected function checkMergeAccess(){

        $passCheck = new AjaxPasswordCheck();
        $access = $passCheck->grantProjectAccessOnJobID( $this->project_data, $this->project_pass, $this->job_id );

        if( !$access ){
            throw new Exception( "Access denied", -10 );
        }

    }

    protected function checkSplitAccess(){

        $passCheck = new AjaxPasswordCheck();
        $access = $passCheck->grantProjectJobAccessOnJobPass( $this->project_data, $this->project_pass, $this->job_pass );

        if( !$access ){
            throw new Exception( "Wrong Password. Access denied", -10 );
        }

    }

}
