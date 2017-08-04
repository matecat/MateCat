<?php

namespace Features\Dqf\Model ;

use Chunks_ChunkStruct;
use Features\Dqf\Service\ChildProjectService;
use Features\Dqf\Service\ChildProjectTranslationBatchService;
use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\Session;
use Features\Dqf\Service\Struct\Request\ChildProjectRequestStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Features\Dqf\Service\Struct\Response\ProjectResponseStruct;
use Files_FileStruct;
use INIT;
use Jobs\MetadataDao;
use Translations_TranslationVersionDao;
use Users_UserDao;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/07/2017
 * Time: 17:09
 */
class TranslationChildProject {

    const SEGMENT_PAIRS_CHUNK_SIZE = 80 ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    /**
     * @var Session
     */
    protected $ownerSession ;

    /**
     * @var Session
     */
    protected $userSession ;

    /**
     * @var ChildProjectsMapStruct[]
     */
    protected $remoteDqfProjects ;

    /**
     * @var Files_FileStruct[]
     */
    protected $files ;

    /**
     * @var ChildProjectTranslationBatchService
     */
    protected $translationBatchService ;

    /**
     * @var UserModel
     */
    protected $dqfTranslateUser ;


    /**
     * @var ChildProjectsMapStruct[]
     */
    protected $dqfChildProjects ;

    protected $parentKeysMap = [] ;

    /**
     * ChildProjectTranslationBatch constructor.
     *
     * @param Chunks_ChunkStruct $chunk
     */

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;

        $uid = ( new MetadataDao() )->get( $chunk->id, $chunk->password, 'dqf_translate_user' )->value ;
        $this->dqfTranslateUser = new UserModel( ( new Users_UserDao() )->getByUid( $uid ) );

        $ownerUser = ( new Users_UserDao() )->getByEmail( $this->chunk->getProject()->id_customer );
        $this->ownerSession = ( new UserModel( $ownerUser ) )->getSession();
        $this->ownerSession->login();

        $this->userSession = $this->dqfTranslateUser->getSession();
        $this->userSession->login();

        $this->translationBatchService = new ChildProjectTranslationBatchService($this->userSession) ;

        $this->dqfChildProjects = ( new ChildProjectsMapDao() )->getByChunk( $this->chunk ) ;
    }

    public function submitTranslationBatch() {
        $this->_assignToTranslator() ;
        $this->_submitSegmentPairs() ;
    }

    protected function _assignToTranslator() {
        // $service = new ChildProjectService($this->ownerSession, $this->chunk ) ;
        // $service->updateTranslationChildren( ['assignee' => $this->dqfTranslateUser->email ] ) ;
        $this->_findRemoteDqfChildProjects() ;

        $updateRequests = array_filter( array_map( function( ProjectResponseStruct $project ) {

            if ( !is_null($project->user->email ) ) {
                return null ;
            }

            return new ChildProjectRequestStruct([
                    'projectKey'   =>   $project->uuid,
                    'projectId'    =>   $project->id,
                    'parentKey'    =>   $this->parentKeysMap[ $project->id ],
                    'assignee'     =>   $this->dqfTranslateUser->getDqfUsername(),
                    'type'         =>   $project->type
            ]) ;

        }, $this->remoteDqfProjects ) )  ;

        if ( !empty( $updateRequests ) ) {
            $childProjectService = new ChildProjectService( $this->ownerSession, $this->chunk );
            $childProjectService->updateChildProjects( $updateRequests ) ;
        }
    }

    protected function _findRemoteDqfChildProjects() {
        $childProjectService = new ChildProjectService( $this->ownerSession, $this->chunk );
        $this->remoteDqfProjects = $childProjectService->getRemoteResources( array_map( function( ChildProjectsMapStruct $item ) {
            return new ChildProjectRequestStruct([
                    'projectId'  =>  $item->dqf_project_id,
                    'projectKey' =>  $item->dqf_project_uuid,
            ]) ;
        }, $this->dqfChildProjects ) ) ;

        $this->parentKeysMap = [] ;
        foreach( $this->dqfChildProjects as $dqfChildProject ) {
            $this->parentKeysMap[ $dqfChildProject->dqf_project_id ] = $dqfChildProject->dqf_parent_uuid ;
        }
    }

    protected function _submitSegmentPairs() {
        /**
         * At this point we must call this endpoint:
         * https://dqf-api.stag.taus.net/#!/Project%2FChild%2FFile%2FTarget_Language%2FSegment/add_0

         *
         * in order to do that, the most complext data structure we need to arrange is the one we pass in the
         * request's body:
         *
         * https://github.com/TAUSBV/dqf-api/blob/master/v3/README.md#batchUpload
         *
         * Example:
         *
         * { "segmentPairs":[
         *    {
         *       "sourceSegmentId":1, <---  id of the source segment
         *       "clientId":"8ab68bd9-8ae7-4860-be6c-bc9a4b276e37", <-- segment_id
         *       "targetSegment":"",                                                            <--- in order to collect this data we must read all segment versions since the last update...
         *       "editedSegment":"Proin interdum mauris non ligula pellentesque ultrices.",     <--- in fact we cannot rely on the latest version only. Subsequent edits may have happened.
         *       "time":6582,                                                                   <-- same thing here, we must make a sum of the time to edit of all versions ??? hum...
         *       "segmentOriginId":5,                         <--- segment origin mapping, read the docs
         *       "mtEngineId":null,                           <--- ??  we should have this field
         *       "mtEngineOtherName":null,                    <--- not needed ? ?
         *       "matchRate":0                                <---- we have this one.
         *    },
         *    {
         *       "sourceSegmentId":2,
         *       "clientId":"e5e6f2ae-7811-4d49-89df-d1b18d11f591",
         *       "targetSegment":"Duis mattis egestas metus.",
         *       "editedSegment":"Duis mattis egostas ligula matus.",
         *       "time":5530,
         *       "segmentOriginId":2,
         *       "mtEngineId":null,
         *       "mtEngineOtherName":null,
         *       "matchRate":100
         *    } ]
         *  }
         *
         * Given an input chunk, we may end up needing to make multiple batch requests, reading the Project Map.
         *
         */

        $this->files = $this->chunk->getFiles() ;

        foreach( $this->files as $file ) {
            list ( $min, $max ) = $file->getMaxMinSegmentBoundariesForChunk( $this->chunk );

            $dqfChildProjects = ( new ChildProjectsMapDao() )->getByChunkAndSegmentsInterval( $this->chunk, $min, $max ) ;
            $segmentIdsMap = ( new DqfSegmentsDao() )->getByIdSegmentRange( $min, $max ) ;

            // DQF child project

            $remoteFileId = $this->_findRemoteFileId( $file );

            foreach ( $dqfChildProjects as $dqfChildProject ) {
                $dao = new Translations_TranslationVersionDao();
                $translations = $dao->getExtendedTranslationByFile(
                        $file,
                        $dqfChildProject->create_date,  // <--- TODO: check if this is correct
                        $dqfChildProject->first_segment,
                        $dqfChildProject->last_segment
                ) ;

                // Now we have translations, make the actual call, one per file per project
                $segmentPairs = [] ;
                foreach ( $translations as $translation ) {
                    // Using a struct and converting it to array immediately allows us to validate the
                    // input array.
                    $segmentPairs[] = ( new SegmentPairStruct([
                            "sourceSegmentId"   => $segmentIdsMap[ $translation->id_segment ]['dqf_segment_id'],
                            "clientId"          => "{$translation->id_job}-{$translation->id_segment}",
                            "targetSegment"     => $translation->translation_before,
                            "editedSegment"     => $translation->translation_after,
                            "time"              => $translation->time,
                            "segmentOriginId"   => 5, // HT hardcoded for now
                            "mtEngineId"        => 22,
                            // "mtEngineId"        => Functions::mapMtEngine( $this->chunk->id_mt_engine ),
                            "mtEngineOtherName" => '',
                            "matchRate"         => '85' // $translation->suggestion_match
                    ]) )->toArray() ;
                }

                $segmentParisChunks = array_chunk( $segmentPairs, self::SEGMENT_PAIRS_CHUNK_SIZE );

                foreach( $segmentParisChunks as $segmentParisChunk ) {
                    $requestStruct                 = new ChildProjectTranslationRequestStruct();
                    $requestStruct->sessionId      = $this->userSession->getSessionId();
                    $requestStruct->fileId         = $remoteFileId ;
                    $requestStruct->projectKey     = $dqfChildProject->dqf_project_uuid ;
                    $requestStruct->projectId      = $dqfChildProject->dqf_project_id ;
                    $requestStruct->targetLangCode = $this->chunk->target ;
                    $requestStruct->apiKey         = INIT::$DQF_API_KEY ;

                    $requestStruct->setSegments( $segmentParisChunk ) ;

                    $this->translationBatchService->addRequestStruct( $requestStruct ) ;
                }
            }
        }

        $this->translationBatchService->process() ;
    }

    protected function _findRemoteFileId( Files_FileStruct $file ) {
        $service = new FileIdMapping( $this->userSession, $file ) ;
        return $service->getRemoteId() ;
    }

}