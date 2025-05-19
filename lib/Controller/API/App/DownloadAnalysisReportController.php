<?php

namespace API\App;

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use AjaxPasswordCheck;
use API\Commons\Validators\LoginValidator;
use API\V2\AbstractDownloadController;
use FeatureSet;
use InvalidArgumentException;
use Model\Analysis\XTRFStatus;
use Projects_ProjectDao;
use RuntimeException;
use Utils;
use ZipContentObject;

class DownloadAnalysisReportController extends AbstractDownloadController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function download(): void {

        $this->featureSet = new FeatureSet();
        $request          = $this->validateTheRequest();
        $_project_data    = Projects_ProjectDao::getProjectAndJobData( $request[ 'id_project' ] );
        $this->id_job     = (int)$_project_data[ 0 ][ 'jid' ];

        $pCheck = new AjaxPasswordCheck();
        $access = $pCheck->grantProjectAccess( $_project_data, $request[ 'password' ] );

        //check for Password correctness
        if ( !$access ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            $this->log( $msg );
            Utils::sendErrMailReport( $msg );

            throw new RuntimeException( $msg );
        }

        $this->featureSet->loadForProject( Projects_ProjectDao::findById( $request[ 'id_project' ], 60 * 60 * 24 ) );

        $analysisStatus = new XTRFStatus( $_project_data, $this->featureSet );
        $outputContent  = $analysisStatus->fetchData()->getResult();

        // cast $outputContent elements to ZipContentObject
        foreach ( $outputContent as $key => $__output_content_elem ) {
            $outputContent[ $key ] = new ZipContentObject( [
                    'output_filename'  => $key,
                    'document_content' => $__output_content_elem,
                    'input_filename'   => $key,
            ] );
        }

        $this->outputContent = $this->composeZip( $outputContent );
        $this->_filename     = $_project_data[ 0 ][ 'pname' ] . ".zip";

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $_project_data[ 0 ][ 'jid' ];
        $activity->id_project = $request[ 'id_project' ]; //assume that all rows have the same project id
        $activity->action     = ActivityLogStruct::DOWNLOAD_ANALYSIS_REPORT;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

        $this->finalize();

    }

    /**
     * @return array|\Klein\Response
     * @throws \ReflectionException
     */
    private function validateTheRequest(): array {
        $id_project    = filter_var( $this->request->param( 'id_project' ), FILTER_SANITIZE_NUMBER_INT );
        $password      = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $download_type = filter_var( $this->request->param( 'download_type' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( empty( $id_project ) ) {
            throw new InvalidArgumentException( "Id project not provided" );
        }

        $project = Projects_ProjectDao::findById( $id_project );

        if ( empty( $project ) ) {
            throw new InvalidArgumentException( -10, "Wrong Id project provided" );
        }

        $this->project = $project;

        return [
                'project'       => $project,
                'id_project'    => $id_project,
                'password'      => $password,
                'download_type' => $download_type,
        ];
    }
}
