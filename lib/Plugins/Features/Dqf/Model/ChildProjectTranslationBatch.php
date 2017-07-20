<?php

namespace Features\Dqf\Model ;

use Chunks_ChunkStruct;
use Features\Dqf\Service\ChildProjectTranslationBatchService;
use Features\Dqf\Service\FileIdMapping;
use Features\Dqf\Service\Session;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Features\Dqf\Utils\Functions;
use Files\FilesJobDao;
use Files\FilesJobStruct;
use Files_FileStruct;
use INIT;
use Jobs\MetadataDao;
use Translations_TranslationVersionDao;
use Users_UserDao;
use Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/07/2017
 * Time: 17:09
 */
class ChildProjectTranslationBatch {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk ;

    /**
     * @var Session
     */
    protected  $session ;

    /**
     * @var ChildProjectsMapStruct[]
     */
    protected  $remoteDqfProjects ;

    /**
     * @var Files_FileStruct[]
     */
    protected $files ;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;
        $record = ( new MetadataDao() )->get( $chunk->id, $chunk->password, 'dqf_translate_user' ) ;

        $dqfUser = new UserModel( ( new Users_UserDao() )->getByUid( $record->value ) ) ;
        $this->session = $dqfUser->getSession()->login();

        $this->service = new ChildProjectTranslationBatchService($this->session) ;

    }

    public function process() {
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
                $dao   = new Translations_TranslationVersionDao();
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
                            "sourceSegmentId"   => $segmentIdsMap[ $translation->id_segment ],
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

                $segmentParisChunks = array_chunk( $segmentPairs, 80 );

                foreach( $segmentParisChunks as $segmentParisChunk ) {
                    $requestStruct                 = new ChildProjectTranslationRequestStruct();
                    $requestStruct->sessionId      = $this->session->getSessionId();
                    $requestStruct->fileId         = $remoteFileId ;
                    $requestStruct->projectKey     = $dqfChildProject->dqf_project_uuid ;
                    $requestStruct->projectId      = $dqfChildProject->dqf_project_id ;
                    $requestStruct->targetLangCode = $this->chunk->target ;
                    $requestStruct->apiKey         = INIT::$DQF_API_KEY ;

                    $requestStruct->setSegments( $segmentParisChunk ) ;

                    $this->service->addRequestStruct( $requestStruct ) ;

                }
            }
        }

        $this->service->process() ;
    }

    protected function _findRemoteFileId( Files_FileStruct $file ) {
        $service = new FileIdMapping( $this->session, $file ) ;
        return $service->getRemoteId() ;
    }

}