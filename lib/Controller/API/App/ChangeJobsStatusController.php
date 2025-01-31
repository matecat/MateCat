<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkDao;
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
     */
    private function validateTheRequest(): array
    {
        $name = filter_var( $this->request->param( 'name' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW  ] );
        $tm_key = filter_var( $this->request->param( 'tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $uuid = filter_var( $this->request->param( 'uuid' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $res_id = filter_var( $this->request->param( 'res_id' ), FILTER_VALIDATE_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $new_status = filter_var( $this->request->param( 'new_status' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );

        if ( empty( $tm_key ) ) {

            if ( empty( INIT::$DEFAULT_TM_KEY ) ) {
                throw new InvalidArgumentException("Please specify a TM key.", -2);
            }

            /*
             * Added the default Key.
             * This means if no private key are provided the TMX will be loaded in the default MyMemory key
             */
            $tm_key = INIT::$DEFAULT_TM_KEY;
        }

        if ( empty( $res_id) ) {
            throw new InvalidArgumentException("No id job provided", -1);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("No job password provided", -2);
        }

        if ( empty( $new_status ) ) {
            throw new InvalidArgumentException("No new status provided", -3);
        }

        return [
            'name' => $name,
            'tm_key' => $tm_key,
            'uuid' => $uuid,
            'res_id' => $res_id,
            'password' => $password,
            'new_status' => $new_status,
        ];
    }
}
