<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\Exceptions\ValidationError;
use API\V2\KleinController;
use CatUtils;
use Features\QaCheckBlacklist;
use Features\QaCheckBlacklist\BlacklistFromTextFile;
use Features\QaCheckBlacklist\BlacklistFromZip;
use Features\QaCheckBlacklist\Utils\BlacklistUtils;
use FilesStorage\FilesStorageFactory;
use Langs_Languages;


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

    public function upload() {

        $this->idJob = $this->request->param( 'jid' );
        $this->password = $this->request->param( 'password' );
        $this->file = $this->request->files()->get('file');

        if($this->idJob === null or $this->password === null or $this->file === null ){
            $this->returnError('Missing params. Required: [jid, password, file]');
        }

        // validate job
        try {
            $chunk = \Chunks_ChunkDao::getByIdAndPassword( $this->idJob, $this->password );
        } catch ( \Exceptions\NotFoundException $e ) {
            $this->returnError($e->getMessage(), 0, 404);
        }

        // validate file size
        if($this->file['size'] > \INIT::$BLACKLIST_FILE_SIZE_MAX){
            $this->returnError('Filesize limit is 2Mb');
        }

        // validate file content/type
        if($this->file['type'] !== 'text/plain'){
            $this->returnError('File type MUST be text/plain');
        }

        // more validation???

        // project has_blacklist?
        $dao = new \Projects_MetadataDao() ;
        $has_blacklist = $dao->setCacheTTL( 60 * 60 * 24 )->get( $chunk->id_project,  'has_blacklist' ) ;

        if(! $has_blacklist){
            $this->returnError('Project has not set a blacklist');
        }

        // upload file
        try {
            BlacklistUtils::save($this->file['tmp_name'], $chunk->id, $chunk->password);
        } catch (\Exception $exception){
            $this->returnError($exception->getMessage());
        }

        // enqueue TranslationCheck
        foreach ($chunk->getTranslations() as $index => $segmentTranslation){

            $queue_element = [
                'id_segment'          => $segmentTranslation->id_segment,
                'id_job'              => $segmentTranslation->id_job,
                'job_password'        => $chunk->password,
                'id_project'          => $chunk->id_project,
                'segment'             => $chunk->getSegments()[$index]->segment,
                'translation'         => $segmentTranslation->translation,
                'from_upload'         => true,
                'recheck_translation' => false
            ];

            try {
                \WorkerClient::init( new \AMQHandler() );
                \WorkerClient::enqueue( 'QA_CHECKS',
                        '\Features\QaCheckBlacklist\Worker\BlacklistWorker',
                        $queue_element,
                        [ 'persistent' => \WorkerClient::$_HANDLER->persistent ]
                );
            } catch (\Exception $exception){
                $this->returnError($exception->getMessage());
            }
        }

        $this->response->json( [
            'success' => true,
            'message' => 'The blacklist file was passed to the queue handler.'
        ] ) ;
    }

    /**
     * @param string $message
     * @param int $code
     * @param int $httpCode
     */
    private function returnError($message, $code = 0, $httpCode = 500)
    {
        $this->response->status()->setCode( $httpCode );
        $this->response->json( [
                'errors' => [
                        'code' => $code,
                        'message' => $message
                ]
        ] );
        exit();
    }
}