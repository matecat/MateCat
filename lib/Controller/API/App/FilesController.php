<?php

namespace Controller\API\App;

use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Model\Files\FilesPartsDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use ReflectionException;

class FilesController extends AbstractStatefulKleinController {

    /**
     * @var \Model\Jobs\JobStruct
     */
    protected JobStruct $chunk;

    public function setChunk( JobStruct $chunk ) {
        $this->chunk = $chunk;
    }

    /**
     * @throws ReflectionException
     */
    public function segments() {
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
     * @param $filePartId
     *
     * @throws ReflectionException
     */
    private function getFirstAndLastSegmentFromFilePartId( $filePartId ) {
        $filePartsDao        = new FilesPartsDao();
        $firstAndLastSegment = $filePartsDao->getFirstAndLastSegment( $filePartId );

        if ( null === $firstAndLastSegment->first_segment ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'error' => 'File part id ' . $filePartId . ' was not found'
            ] );
            exit();
        }

        $this->response->json( [
                'first_segment' => (int)$firstAndLastSegment->first_segment,
                'last_segment'  => (int)$firstAndLastSegment->last_segment,
        ] );
        exit();
    }

    /**
     * @param $fileId
     *
     * @throws ReflectionException
     */
    private function getFirstAndLastSegmentFromFileId( $fileId ) {
        $fileInfo = JobDao::getFirstSegmentOfFilesInJob( $this->chunk, 60 * 5 );

        if ( empty( $fileInfo ) ) {
            $this->response->status()->setCode( 404 );
            $this->response->json( [
                    'error' => 'File id ' . $fileId . ' was not found'
            ] );
            exit();
        }

        $firstAndLastSegment = array_filter( $fileInfo, function ( $item ) use ( $fileId ) {
            return $item->id_file == $fileId;
        } )[ 0 ];

        $this->response->json( [
                'fist_segment' => (int)$firstAndLastSegment->first_segment,
                'last_segment' => (int)$firstAndLastSegment->last_segment,
        ] );
        exit();
    }

    /**
     * @param $value
     */
    private function validateInteger( $value ) {
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