<?php

namespace Features\Dqf\Model ;

use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkStruct;
use Exception;
use Features\Dqf\Service\ChildProjectService;
use Features\Dqf\Service\TranslationBatchService;
use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\Session;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectRequestStruct;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Files_FileStruct;
use INIT;
use Jobs\MetadataDao;
use LoudArray;
use Translations_TranslationVersionDao;
use Users_UserDao;

class TranslationChildProject {

    const SEGMENT_PAIRS_CHUNK_SIZE = 80 ;

    /** * @var Chunks_ChunkStruct */
    protected $chunk ;

    /** * @var Session */
    protected $userSession ;

    /** * @var DqfProjectMapStruct[] */
    protected $remoteDqfProjects ;

    /** * @var Files_FileStruct[] */
    protected $files ;

    /** * @var TranslationBatchService */
    protected $translationBatchService ;

    /** * @var UserModel */
    protected $dqfTranslateUser ;

    /** * @var DqfProjectMapStruct[] */
    protected $dqfChildProjects = [];

    protected $parentKeysMap = [] ;

    /** * @var ProjectMapResolverModel */
    protected $dqfProjectMapResolver  ;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;

        $this->_initUserAndSession() ;

        $this->dqfProjectMapResolver = new ProjectMapResolverModel( $this->chunk, 'translate' );

        $this->dqfChildProjects = $this->dqfProjectMapResolver->getMappedProjects();
    }

    public function setCompleted() {
        /** @var DqfProjectMapStruct $project */
        foreach( $this->dqfChildProjects as $project ) {
            $service = new ChildProjectService(
                    $this->userSession, $this->chunk, $project->id ) ;

            $struct = new ChildProjectRequestStruct([
                    'projectId' => $project->dqf_project_id,
                    'projectKey' => $project->dqf_project_uuid
            ]);

            $service->setCompleted( $struct );
        }
    }

    public function submitTranslationBatch() {
        if ( $this->projectCreationRequired() ) {
            $this->createRemoteProjects();
        }
        $this->_submitSegmentPairs() ;
    }

    protected function projectCreationRequired() {
        return empty( $this->dqfChildProjects ) ;
    }

    protected function createRemoteProjects() {
        $parents = $this->dqfProjectMapResolver->getParents();

        $this->dqfChildProjects = [] ;

        foreach( $parents as $parent ) {
            $project = new ChildProjectCreationModel($parent, $this->chunk, 'translate' );

            $model = new ProjectModel( $parent );
            $project->setUser( $this->dqfTranslateUser ) ;
            $project->setFiles( $model->getFilesResponseStruct() ) ;

            $project->create();

            // TODO: not sure this assignment is really helpful
            // reloading the projectMapper should be enough
            $this->dqfChildProjects[] = $project->getSavedRecord() ;

            $this->dqfProjectMapResolver->reload() ;
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

        $service = new TranslationBatchService( $this->userSession ) ;

        foreach( $this->files as $file ) {
            list ( $fileMinIdSegment, $fileMaxIdSegment ) = $file->getMaxMinSegmentBoundariesForChunk( $this->chunk );

            $segmentIdsMap = new LoudArray(
                    ( new DqfSegmentsDao() )->getByIdSegmentRange( $fileMinIdSegment, $fileMaxIdSegment )
            );

            $remoteFileId = $this->_findRemoteFileId( $file );

            $dqfChildProjects = $this->dqfProjectMapResolver
                    ->getCurrentInSegmentIdBoundaries(
                            $fileMinIdSegment, $fileMaxIdSegment
                    );

            foreach ( $dqfChildProjects as $dqfChildProject ) {
                $dao = new Translations_TranslationVersionDao();
                $translations = $dao->getExtendedTranslationByFile(
                        $file,
                        $this->getLimitDate($dqfChildProject),
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
                            // TODO: the corect form of this key should be the following, to so to get back the
                            // id_job for multi-language projects.
                            // "clientId"          => "{$translation->id_job}-{$translation->id_segment}",
                            "clientId"          => $translation->id_segment,
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

                    $service->addRequestStruct( $requestStruct ) ;
                }
            }
        }

        // TODO: fix this this is likely to break at 50 requests. Create a further chunked array to limit.
        $results = $service->process() ;

        $this->_saveResults( $results ) ;
    }

    protected function _saveResults( $results ) {
        $results = array_map( function( $item ) {
            $translations = json_decode( $item, true )['translations'];
            return array_map( function($item) {
                return [ $item['clientId'], $item['dqfId'] ] ;
            }, $translations );
        }, $results );

        $dao = new DqfSegmentsDao() ;

        foreach( $results as $batch ) {
            $dao->insertBulkMapForTranslationId( $batch ) ;
        }
    }

    protected function _findRemoteFileId( Files_FileStruct $file ) {
        $projectOwner = new UserModel ( $this->chunk->getProject()->getOwner()  ) ;
        $service = new FileIdMapping( $projectOwner->getSession()->login(), $file ) ;

        return $service->getRemoteId() ;
    }

    protected function getLimitDate( DqfProjectMapStruct $dqfChildProject) {
        $lastEvent = Chunks_ChunkCompletionEventDao::lastCompletionRecord( $this->chunk, ['is_review' => false ] );
        if ( $lastEvent ) {
            return $dqfChildProject->create_date ;
        }
        else {
            return $lastEvent['create_date'];
        }
    }

    protected function _initUserAndSession() {
        $uid = ( new MetadataDao() )
                ->get( $this->chunk->id, $this->chunk->password, 'dqf_translate_user' )
                ->value ;

        if ( !$uid ) {
            throw new Exception('dqf_translate_user must be set') ;
        }

        $this->dqfTranslateUser = new UserModel( ( new Users_UserDao() )->getByUid( $uid ) );
        $this->userSession      = $this->dqfTranslateUser->getSession()->login();
    }


}