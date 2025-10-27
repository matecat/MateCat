<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Interfaces\ChunkPasswordValidatorInterface;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Model\Files\FilesPartsDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use ReflectionException;

class FilesController extends AbstractStatefulKleinController implements ChunkPasswordValidatorInterface {

    /**
     * @var JobStruct
     */
    protected JobStruct $chunk;

    protected int    $id_job;
    protected string $jobPassword;

    /**
     * @param int $id_job
     *
     * @return $this
     */
    public function setIdJob( int $id_job ): static {
        $this->id_job = $id_job;

        return $this;
    }

    /**
     * @param string $jobPassword
     *
     * @return $this
     */
    public function setJobPassword( string $jobPassword ): static {
        $this->jobPassword = $jobPassword;

        return $this;
    }


    public function setChunk( JobStruct $chunk ): void {
        $this->chunk = $chunk;
    }

    /**
     * @throws ReflectionException
     */
    public function segments(): void {
        // `file_part_id` has the priority
        if ( isset( $_POST[ 'file_part_id' ] ) ) {
            $filePartId = $_POST[ 'file_part_id' ];
            $this->validateInteger( $filePartId );
            $this->getFirstAndLastSegmentFromFilePartId( $filePartId );
        }

        if ( isset( $_POST[ 'file_id' ] ) ) {
            $fileId = $_POST[ 'file_id' ];
            $this->validateInteger( $fileId );
            $this->getFirstAndLastSegmentFromFileId( $fileId );
        }

        $this->response->status()->setCode( 500 );
        $this->response->json( [
                'error' => 'Missing parameters. `file_part_id` or `file_id` must be provided'
        ] );
    }

    /**
     * @param int $filePartId
     *
     * @throws ReflectionException
     */
    private function getFirstAndLastSegmentFromFilePartId( int $filePartId ): void {
        $filePartsDao        = new FilesPartsDao();
        $firstAndLastSegment = $filePartsDao->getFirstAndLastSegment( $filePartId );

        if ( null === $firstAndLastSegment->first_segment ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'error' => 'File part id ' . $filePartId . ' was not found'
            ] );
        }

        $this->response->json( [
                'first_segment' => (int)$firstAndLastSegment->first_segment,
                'last_segment'  => (int)$firstAndLastSegment->last_segment,
        ] );
    }

    /**
     * @param int $fileId
     *
     * @throws ReflectionException
     */
    private function getFirstAndLastSegmentFromFileId( int $fileId ): void {
        $fileInfo = JobDao::getFirstSegmentOfFilesInJob( $this->chunk, 60 * 5 );

        if ( empty( $fileInfo ) ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'error' => 'File id ' . $fileId . ' was not found'
            ] );
        }

        $firstAndLastSegment = array_filter( $fileInfo, function ( $item ) use ( $fileId ) {
            return $item->id_file == $fileId;
        } )[ 0 ];

        $this->response->json( [
                'fist_segment' => (int)$firstAndLastSegment->first_segment,
                'last_segment' => (int)$firstAndLastSegment->last_segment,
        ] );
    }

    /**
     * @param mixed $value
     */
    private function validateInteger( mixed $value ): void {
        if ( !filter_var( $value, FILTER_VALIDATE_INT ) ) {

            $this->response->status()->setCode( 500 );
            $this->response->json( [
                    'error' => '`file_part_id` is not an integer'
            ] );
            exit();
        }
    }

    protected function afterConstruct() {
        $Validator  = ( new ChunkPasswordValidator( $this ) );
        $Controller = $this;
        $Validator->onSuccess( function () use ( $Validator, $Controller ) {
            $Controller->setChunk( $Validator->getChunk() );
        } );

        $this->appendValidator( $Validator );
        $this->appendValidator( new LoginValidator( $this ) );
    }
}