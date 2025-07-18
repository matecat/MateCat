<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;
use Utils\Constants\JobStatus;
use Utils\Tools\Utils;

class ChangeJobsStatusController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function changeStatus(): void {

        $request = $this->validateTheRequest();

        if ( $request[ 'res_type' ] == "prj" ) {

            try {
                $project = ProjectDao::findByIdAndPassword( $request[ 'res_id' ], $request[ 'password' ] );
            } catch ( Exception $e ) {
                $msg = "Error : wrong password provided for Change Project Status \n\n " . var_export( $_POST, true ) . "\n";
                $this->log( $msg );
                Utils::sendErrMailReport( $msg );
                throw new NotFoundException( "Job not found" );
            }

            $chunks = $project->getJobs();

            JobDao::updateAllJobsStatusesByProjectId( $project->id, $request[ 'new_status' ] );

            foreach ( $chunks as $chunk ) {
                $lastSegmentsList = SegmentTranslationDao::getMaxSegmentIdsFromJob( $chunk );
                SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
            }

        } else {

            try {
                $firstChunk = ChunkDao::getByIdAndPassword( $request[ 'res_id' ], $request[ 'password' ] );
            } catch ( Exception $e ) {
                $msg = "Error : wrong password provided for Change Job Status \n\n " . var_export( $_POST, true ) . "\n";
                $this->log( $msg );
                Utils::sendErrMailReport( $msg );
                throw new NotFoundException( "Job not found" );
            }

            JobDao::updateJobStatus( $firstChunk, $request[ 'new_status' ] );
            $lastSegmentsList = SegmentTranslationDao::getMaxSegmentIdsFromJob( $firstChunk );
            SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );
        }

        $this->response->json( [
                'errors' => [],
                'code'   => 1,
                'data'   => 'OK',
                'status' => $request[ 'new_status' ]
        ] );

    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $pn         = filter_var( $this->request->param( 'pn' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $id         = filter_var( $this->request->param( 'id' ), FILTER_VALIDATE_INT );
        $res        = filter_var( $this->request->param( 'res' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $password   = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $new_status = filter_var( $this->request->param( 'new_status' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );

        if ( !JobStatus::isAllowedStatus( $new_status ) ) {
            throw new Exception( "Invalid Status" );
        }

        return [
                'pn'         => $pn,
                'res_type'   => $res,
                'res_id'     => $id,
                'password'   => $password,
                'new_status' => $new_status,
        ];
    }
}
