<?php

namespace API\App;

use AjaxPasswordCheck;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\Analysis\Status;
use Projects_ProjectDao;

class GetVolumeAnalysisController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function analysis()
    {
        try {
            $pid = filter_var( $this->request->param( 'pid' ), FILTER_SANITIZE_NUMBER_INT );
            $ppassword = filter_var( $this->request->param( 'ppassword' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
            $jpassword = filter_var( $this->request->param( 'jpassword' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

            if ( empty( $pid ) ) {
                throw new InvalidArgumentException("No id project provided", -1);
            }

            $_project_data = Projects_ProjectDao::getProjectAndJobData( $pid );
            $passCheck = new AjaxPasswordCheck();
            $access    = $passCheck->grantProjectAccess( $_project_data, $ppassword ) or $passCheck->grantProjectJobAccessOnJobPass( $_project_data, null, $jpassword );

            if ( !$access ) {
                throw new AuthenticationError("Wrong Password. Access denied", -10);
            }

            if(!empty($errors)){
                $this->response->code(400);

                return $this->response->json($errors);
            }

            $analysisStatus = new Status( $_project_data, $this->featureSet, $this->user );

            return $this->response->json($analysisStatus->fetchData()->getResult());
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }
}