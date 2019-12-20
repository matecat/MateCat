<?php

namespace Features\Dqf\Model ;

use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkStruct;
use DataAccess\LoudArray;
use Exception;
use Features\Dqf\Model\CachedAttributes\SegmentOrigin;
use Features\Dqf\Service\Struct\Request\ChildProjectTranslationRequestStruct;
use Features\Dqf\Service\TranslationBatchService;
use Features\Dqf\Utils\Functions;
use INIT;

class TranslationChildProject extends AbstractChildProject {

    const SEGMENT_PAIRS_CHUNK_SIZE = 80 ;

    /**
     * @var SegmentOrigin
     */
    protected $originMap ;

    /**
     * TranslationChildProject constructor.
     *
     * @param Chunks_ChunkStruct $chunk
     *
     * @throws Exception
     */

    public function __construct( Chunks_ChunkStruct $chunk ) {
        parent::__construct( $chunk, 'translate' );
        $this->originMap = new SegmentOrigin() ;
    }

    protected function _submitData() {
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

        $service   = new TranslationBatchService( $this->userSession ) ;
        $limitDate = $this->getLimitDate() ;

        $sourceMap = new SegmentOrigin();

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
                $dao = new TranslationVersionDao();
                $translations = $dao->getExtendedTranslationByFile(
                        $file,
                        $limitDate,
                        $dqfChildProject->first_segment,
                        $dqfChildProject->last_segment
                ) ;

                // Now we have translations, make the actual call, one per file per project
                $segmentPairs = [] ;
                foreach ( $translations as $translation ) {
                    // Using a struct and converting it to array immediately allows us to validate the
                    // input array.

                    list( $segmentOriginId, $matchRate ) = $this->getSegmentOriginAndMatchRate( $translation ) ;

                    $segmentPairs[] = ( new SegmentPairStruct([
                            "sourceSegmentId"   => $segmentIdsMap[ $translation->id_segment ]['dqf_segment_id'],
                            // TODO: the corect form of this key should be the following, to so to get back the
                            // id_job for multi-language projects.
                            "clientId"          => $this->translationIdToDqf( $translation, $dqfChildProject ),
                            "targetSegment"     => ''. $translation->translation_before,
                            "editedSegment"     => $translation->translation_after,
                            "time"              => $this->transaltionTimeWithTimeout( $translation->time ),
                            "segmentOriginId"   => $segmentOriginId,
                            "matchRate"         => $matchRate,
                            "mtEngineId"        => 22, // MyMemory
                            // "mtEngineId"        => Functions::mapMtEngine( $this->chunk->id_mt_engine ),
                            "mtEngineOtherName" => '',
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

    /**
     * TODO: use this function to implement a timeout
     *
     * @param $time
     *
     * @return mixed
     */
    protected function transaltionTimeWithTimeout( $time ) {
        return $time ;
    }

    protected function getSegmentOriginAndMatchRate( ExtendedTranslationStruct $translation ) {
        $data = [
                'originName' => $translation->segment_origin,
                'matchRate'  => $translation->suggestion_match
        ] ;

        $data = $this->chunk->getProject()->getFeaturesSet()->filter(
                'filterDqfSegmentOriginAndMatchRate', $data, $translation, $this->chunk
        ) ;

        $object = $this->originMap->getByName( $data['originName'] ) ;

        return [ $object['id'], $data['matchRate'] ];
    }

    protected function translationIdToDqf( ExtendedTranslationStruct $translation, DqfProjectMapStruct $dqfChildProject ) {
        return Functions::scopeId( $dqfChildProject->id . "-" . $translation->id_segment ) ;
    }

    protected function translationIdFromDqf( $id ) {
        $cleanId = Functions::descope( $id );
        list( $dqfMapId, $segmentId ) = explode('-', $cleanId );
        return $segmentId ;
    }

    protected function _saveResults( $results ) {
        $results = array_map( function( $item ) {
            $translations = json_decode( $item, true )['translations'];
            return array_map( function($item) {
                return [
                        $this->translationIdFromDqf($item['clientId']), $item['dqfId']
                ] ;
            }, $translations );
        }, $results );

        $dao = new DqfSegmentsDao() ;

        foreach( $results as $batch ) {
            $dao->insertBulkMapForTranslationId( $batch ) ;
        }
    }

    protected function getLimitDate() {
        // find date of completion event for inverse type
        $is_review = ( $this->type == DqfProjectMapDao::PROJECT_TYPE_REVISE ) ;
        $prevEvent = Chunks_ChunkCompletionEventDao::lastCompletionRecord( $this->chunk, ['is_review' => !$is_review ] );

        if ( $prevEvent ) {
            return $prevEvent['create_date'];
        }
        else {
            return $this->chunk->getProject()->create_date ;
        }
    }

}