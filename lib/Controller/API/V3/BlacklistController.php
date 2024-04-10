<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Chunks_ChunkDao;
use Exception;
use Exceptions\NotFoundException;
use Features\QaCheckBlacklist\Utils\BlacklistUtils;
use Glossary\Blacklist\BlacklistDao;
use RedisHandler;
use WorkerClient;


class BlacklistController extends KleinController {

    /**
     * @var int
     */
    private $idJob;

    /**
     * @var string
     */
    private $password;

    /**
     * @var array
     */
    private $file;

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @Route /blacklist/delete/[:id_file]
     */
    public function delete() {

        $dao   = new BlacklistDao();
        $model = $dao->getById( $this->request->param( 'id_file' ) );

        if ( empty( $model ) ) {
            $this->returnError( 'Blacklist not found', 0, 404 );
        }

        try {
            $blacklistUtils = new BlacklistUtils( ( new RedisHandler() )->getConnection() );
            $blacklistUtils->delete( $this->request->param( 'id_file' ) );
            $dao->destroyGetByIdCache( $this->request->param( 'id_file' ) );
            $dao->destroyGetByJobIdAndPasswordCache( $model->id_job, $model->password );

            $this->response->json( [
                    'success' => true,
                    'id'      => $this->request->param( 'id_file' )
            ] );
        } catch ( Exception $exception ) {
            $this->returnError( $exception->getMessage() );
        }
    }

    /**
     * @Route  /blacklist/get/[:id_file]
     */
    public function get() {

        $dao   = new BlacklistDao();
        $model = $dao->getById( $this->request->param( 'id_file' ) );

        if ( empty( $model ) ) {
            return $this->returnError( "Blacklist not found", 0, 404 );
        }

        $blacklistUtils              = new BlacklistUtils( ( new RedisHandler() )->getConnection() );
        $model->content              = $blacklistUtils->getContent( $this->request->param( 'id_file' ) );
        $model->blacklist_word_count = ( !empty( $model->content ) ) ? count( $model->content ) : null;
        $this->response->json( $model );
    }

    /**
     * @Route /blacklist/upload
     */
    public function upload() {

        $this->idJob    = $this->request->param( 'jid' );
        $this->password = $this->request->param( 'password' );
        $this->file     = $this->request->files()->get( 'file' );

        if ( $this->idJob === null or $this->password === null or $this->file === null ) {
            $this->returnError( 'Missing params. Required: [jid, password, file]' );
        }

        // validate job
        try {
            $chunk = Chunks_ChunkDao::getByIdAndPassword( $this->idJob, $this->password );
        } catch ( NotFoundException $e ) {
            $this->returnError( $e->getMessage(), 0, 404 );
        }

        // validate file size
        if ( $this->file[ 'size' ] > \INIT::$BLACKLIST_FILE_SIZE_MAX ) {
            $this->returnError( 'Filesize limit is 2Mb' );
        }

        // validate file content/type
        if ( $this->file[ 'type' ] !== 'text/plain' ) {
            $this->returnError( 'File type MUST be text/plain' );
        }

        // project has_blacklist?
        $dao           = new \Projects_MetadataDao();
        $has_blacklist = $dao->setCacheTTL( 60 * 60 * 24 )->get( $chunk->id_project, 'has_blacklist' );

        if ( !$has_blacklist ) {
            $this->returnError( 'Project has not set a blacklist' );
        }

        // check if project has already a blacklist
        $dao = new BlacklistDao();
        $dao->destroyGetByJobIdAndPasswordCache( $chunk->id, $chunk->password );
        $model = $dao->getByJobIdAndPassword( $chunk->id, $chunk->password );
        if ( !empty( $model ) ) {
            $this->returnError( 'Job has already a blacklist' );
        }

        // upload file
        try {
            $blacklistUtils = new BlacklistUtils( ( new RedisHandler() )->getConnection() );
            $idBlacklist    = $blacklistUtils->save( $this->file[ 'tmp_name' ], $chunk, $this->user->uid );
        } catch ( Exception $exception ) {
            $this->returnError( $exception->getMessage() );
        }

        // enqueue TranslationCheck
        foreach ( $chunk->getTranslations() as $index => $segmentTranslation ) {

            $queue_element = [
                    'id_segment'          => $segmentTranslation->id_segment,
                    'id_job'              => $segmentTranslation->id_job,
                    'job_password'        => $chunk->password,
                    'id_project'          => $chunk->id_project,
                    'segment'             => $chunk->getSegments()[ $index ]->segment,
                    'translation'         => $segmentTranslation->translation,
                    'from_upload'         => true,
                    'recheck_translation' => false
            ];

            try {
                WorkerClient::enqueue( 'QA_CHECKS',
                        '\Features\QaCheckBlacklist\Worker\BlacklistWorker',
                        $queue_element,
                        [ 'persistent' => WorkerClient::$_HANDLER->persistent ]
                );
            } catch ( Exception $exception ) {
                $this->returnError( $exception->getMessage() );
            }
        }

        $this->response->json( [
                'success' => true,
                'id'      => $idBlacklist
        ] );
    }

    /**
     * @param string $message
     * @param int    $code
     * @param int    $httpCode
     */
    private function returnError( $message, $code = 0, $httpCode = 500 ) {
        $this->response->status()->setCode( $httpCode );
        $this->response->json( [
                'errors' => [
                        'code'    => $code,
                        'message' => $message
                ]
        ] );
        exit();
    }
}