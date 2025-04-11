<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkDao;
use Constants_JobStatus;
use Exception;
use Exceptions\NotFoundException;
use INIT;
use InvalidArgumentException;
use Jobs_JobDao;
use Klein\Response;
use Projects_ProjectDao;
use Translations_SegmentTranslationDao;
use Utils;

class ChangeJobsStatusController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function changeStatus(): Response
    {
        try {
            $request = $this->validateTheRequest();

            if ( $request['res_type'] == "prj" ) {

                try {
                    $project = Projects_ProjectDao::findByIdAndPassword( $request['res_id'], $request['password'] );
                } catch( Exception $e ){
                    $msg = "Error : wrong password provided for Change Project Status \n\n " . var_export( $_POST, true ) . "\n";
                    $this->log( $msg );
                    Utils::sendErrMailReport( $msg );
                    throw new NotFoundException("Job not found");
                }

                $chunks = $project->getJobs();

                Jobs_JobDao::updateAllJobsStatusesByProjectId( $project->id, $request['new_status'] );

                foreach( $chunks as $chunk ){
                    $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $chunk );
                    Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
                }

            } else {

                try {
                    $firstChunk = Chunks_ChunkDao::getByIdAndPassword( $request['res_id'], $request['password'] );
                } catch( Exception $e ){
                    $msg = "Error : wrong password provided for Change Job Status \n\n " . var_export( $_POST, true ) . "\n";
                    $this->log( $msg );
                    Utils::sendErrMailReport( $msg );
                    throw new NotFoundException("Job not found");
                }

                Jobs_JobDao::updateJobStatus( $firstChunk, $request['new_status'] );
                $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $firstChunk );
                Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
            }

            return $this->response->json([
                'errors' => [],
                'code' => 1,
                'data' => 'OK',
                'status' => $request['new_status']
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $pn = filter_var( $this->request->param( 'pn' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW  ] );
        $id = filter_var( $this->request->param( 'id' ), FILTER_VALIDATE_INT );
        $res = filter_var( $this->request->param( 'res' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $new_status = filter_var( $this->request->param( 'new_status' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );

        if ( !Constants_JobStatus::isAllowedStatus( $new_status ) ) {
            throw new Exception( "Invalid Status" );
        }

        return [
            'pn' => $pn,
            'res_type' => $res,
            'res_id' => $id,
            'password' => $password,
            'new_status' => $new_status,
        ];
    }
}
