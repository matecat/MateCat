<?php

namespace API\App;

use AjaxPasswordCheck;
use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Model\Analysis\Status;
use Projects_ProjectDao;

class GetVolumeAnalysisController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function analysis()
    {
        $pid = filter_var( $this->request->param( 'pid' ), FILTER_SANITIZE_NUMBER_INT );
        $ppassword = filter_var( $this->request->param( 'ppassword' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $jpassword = filter_var( $this->request->param( 'jpassword' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        $errors = [];

        if ( empty( $pid ) ) {
            $errors[] = [
                'code' => -1,
                'message' => "No id project provided",
            ];
        }

        $_project_data = Projects_ProjectDao::getProjectAndJobData( $pid );
        $passCheck = new AjaxPasswordCheck();
        $access    = $passCheck->grantProjectAccess( $_project_data, $ppassword ) or $passCheck->grantProjectJobAccessOnJobPass( $_project_data, null, $jpassword );

        if ( !$access ) {
            $errors[] = [
                'code' => -10,
                'message' => "Wrong Password. Access denied",
            ];
        }

        if(!empty($errors)){
            $this->response->code(400);

            return $this->response->json($errors);
        }

        $analysisStatus = new Status( $_project_data, $this->featureSet, $this->user );

        return $this->response->json($analysisStatus->fetchData()->getResult());
    }
}