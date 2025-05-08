<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Constants_TranslationStatus;
use Database;
use Exception;
use InvalidArgumentException;
use Jobs_JobDao;
use Segments_SegmentDao;
use TranslationsSplit_SplitDAO;
use TranslationsSplit_SplitStruct;

class SetCurrentSegmentController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function set()
    {
        try {
            $request = $this->validateTheRequest();
            $revision_number = $request['revision_number'];
            $id_segment = $request['id_segment'];
            $id_job = $request['id_job'];
            $password = $request['password'];
            $split_num = $request['split_num'];

            //get Job Info, we need only a row of jobs ( split )
            $job_data = Jobs_JobDao::getByIdAndPassword( $id_job, $password );

            if ( empty( $job_data ) ) {
                throw new InvalidArgumentException("wrong password", -10);
            }

            if ( empty( $id_segment ) ) {
                throw new InvalidArgumentException("missing segment id", -1);
            }

            $segmentStruct             = new TranslationsSplit_SplitStruct();
            $segmentStruct->id_segment = (int)$id_segment;
            $segmentStruct->id_job     = $id_job;

            $translationDao  = new TranslationsSplit_SplitDAO( Database::obtain() );
            $currSegmentInfo = $translationDao->read( $segmentStruct );

            /**
             * Split check control
             */
            $isASplittedSegment = false;
            $isLastSegmentChunk = true;

            if ( count( $currSegmentInfo ) > 0 ) {

                $isASplittedSegment = true;
                $currSegmentInfo    = array_shift( $currSegmentInfo );

                //get the chunk number and check whether it is the last one or not
                $isLastSegmentChunk = ( $split_num == count( $currSegmentInfo->source_chunk_lengths ) - 1 );

                if ( !$isLastSegmentChunk ) {
                    $nextSegmentId = $id_segment . "-" . ( $split_num + 1 );
                }
            }

            /**
             * End Split check control
             */
            if ( !$isASplittedSegment or $isLastSegmentChunk ) {

                $segmentList = Segments_SegmentDao::getNextSegment( $id_segment, $id_job, $password, $revision_number );

                if ( !$revision_number ) {
                    $nextSegmentId = CatUtils::fetchStatus( $id_segment, $segmentList );
                } else {
                    $nextSegmentId = CatUtils::fetchStatus( $id_segment, $segmentList, Constants_TranslationStatus::STATUS_TRANSLATED );
                    if ( !$nextSegmentId ) {
                        $nextSegmentId = CatUtils::fetchStatus( $id_segment, $segmentList, Constants_TranslationStatus::STATUS_APPROVED );
                    }
                }
            }

            return $this->response->json([
                'code' => 1,
                'errors' => [],
                'data' => [],
                'nextSegmentId' => $nextSegmentId ?? null,
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
        $revision_number = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( empty( $id_job) ) {
            throw new InvalidArgumentException("No id job provided", -1);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("No job password provided", -2);
        }

        if ( empty( $id_segment) ) {
            throw new InvalidArgumentException("No id segment provided", -3);
        }

        $segment = explode( "-", $id_segment );
        $id_segment = $segment[0];
        $split_num = $segment[1];

        return [
            'revision_number' => $revision_number,
            'id_segment' => $id_segment,
            'id_job' => $id_job,
            'password' => $password,
            'split_num' => $split_num,
        ];
    }
}
