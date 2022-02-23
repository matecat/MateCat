<?php

use Features\ReviewExtended\ReviewUtils;
use LQA\ChunkReviewDao;

class changePasswordController extends ajaxController {
    protected $res_type;
    protected $res_id;
    protected $password;
    protected $undo;
    protected $old_password;
    protected $revision_number;

    public function __construct() {

        parent::readLoginInfo( false ); //need to write to the sessions
        parent::__construct();

        $filterArgs = [
                'res'             => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'id'              => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'old_password'    => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'revision_number' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'undo'            => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->res_type        = $__postInput[ 'res' ];
        $this->res_id          = $__postInput[ 'id' ];
        $this->password        = $__postInput[ 'password' ];
        $this->old_password    = $__postInput[ 'old_password' ];
        $this->undo            = $__postInput[ 'undo' ];
        $this->revision_number = $__postInput[ 'revision_number' ];

    }

    public function doAction() {

        if ( !$this->userIsLogged ) {
            throw new Exception( 'User not Logged' );
        }

        if ( $this->undo ) {
            $new_pwd    = $this->old_password;
            $actual_pwd = $this->password;
        } else {
            $new_pwd    = Utils::randomString( 15, true );
            $actual_pwd = $this->password;
        }

        $this->changePassword( $actual_pwd, $new_pwd );

        $this->result[ 'password' ] = $new_pwd;
        $this->result[ 'undo' ]     = $this->password;

    }

    protected function changePassword( $actual_pwd, $new_password ) {

        if ( $this->res_type == "prj" ) {

            $pStruct = Projects_ProjectDao::findByIdAndPassword( $this->res_id, $actual_pwd );
            $pDao    = new Projects_ProjectDao();
            $pDao->changePassword( $pStruct, $new_password );
            $pDao->destroyCacheById( $this->res_id );

            // invalidate cache for ProjectData
            $pDao->destroyCacheForProjectData($pStruct->id, $pStruct->password);

            $pStruct->getFeaturesSet()
                    ->run( 'project_password_changed', $pStruct, $actual_pwd );

        } else {

            Database::obtain()->begin();

            if ( $this->revision_number ) {

                $jStruct     = CatUtils::getJobFromIdAndAnyPassword( $this->res_id, $actual_pwd );
                $source_page = ReviewUtils::revisionNumberToSourcePage( $this->revision_number );
                $dao         = new ChunkReviewDao();
                $dao->updateReviewPassword( $this->res_id, $actual_pwd, $new_password, $source_page );
                $jStruct->getProject()
                        ->getFeaturesSet()
                        ->run( 'review_password_changed', $this->res_id, $actual_pwd, $new_password, $this->revision_number );


            } else {
                $jStruct = Jobs_JobDao::getByIdAndPassword( $this->res_id, $actual_pwd );
                $jDao    = new Jobs_JobDao();
                $jDao->changePassword( $jStruct, $new_password );
                $jStruct->getProject()
                        ->getFeaturesSet()
                        ->run( 'job_password_changed', $jStruct, $actual_pwd );
            }

            // invalidate cache for ProjectData
            $pDao = new Projects_ProjectDao();
            $pDao->destroyCacheForProjectData($jStruct->getProject()->id, $jStruct->getProject()->password);

            Database::obtain()->commit();

        }

    }

}