<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use API\V2\Json\QAGlobalWarning;
use API\V2\Json\QALocalWarning;
use Chunks_ChunkDao;
use Chunks_ChunkStruct;
use Exception;
use LQA\QA;
use Matecat\SubFiltering\MateCatFilter;
use Segments_SegmentDao;
use Translations\WarningDao;
use Utils;

class GetWarningController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function global()
    {
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $token = filter_var( $this->request->param( 'token' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );

        try {
            $chunk = $this->getChunk($id_job, $password);
            $warnings    = WarningDao::getWarningsByJobIdAndPassword( $id_job, $password );
            $tMismatch = ( new Segments_SegmentDao() )->setCacheTTL( 10 * 60 /* 10 minutes cache */ )->getTranslationsMismatches( $id_job, $password );
        } catch ( Exception $e ) {
            return $this->response->json([
                'details' => []
            ]);
        }

        $qa = new QAGlobalWarning( $warnings, $tMismatch );

        $result = array_merge(
            [
                'data' => [],
                'errors' => [],
                'token' => (!empty($token) ? $token : null)
            ],
            $qa->render(),
            Utils::getGlobalMessage()
        );

        $result = $this->featureSet->filter( 'filterGlobalWarnings', $result, [
            'chunk' => $chunk,
        ] );

        return $this->response->json($result);
    }

    /**
     * @return \Klein\Response|void
     * @throws \API\Commons\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    public function local()
    {
        $id = filter_var( $this->request->param( 'id' ), FILTER_SANITIZE_NUMBER_INT );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $src_content = filter_var( $this->request->param( 'src_content' ), FILTER_UNSAFE_RAW );
        $trg_content = filter_var( $this->request->param( 'trg_content' ), FILTER_UNSAFE_RAW );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $token = filter_var( $this->request->param( 'token' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $logs = filter_var( $this->request->param( 'logs' ), FILTER_UNSAFE_RAW );
        $segment_status = filter_var( $this->request->param( 'segment_status' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $characters_counter = filter_var( $this->request->param( 'characters_counter' ), FILTER_SANITIZE_NUMBER_INT );

        if(empty($segment_status)){
            $segment_status = 'draft';
        }

        /**
         * Update 2015/08/11, roberto@translated.net
         * getWarning needs the segment status too because of a bug:
         *   sometimes the client calls getWarning and sends an empty trg_content
         *   because the suggestion has not been loaded yet.
         *   This happens only if segment is in status NEW
         */
        if ( $segment_status == 'new' and empty( $trg_content )) {
            return;
        }

        $chunk = $this->getChunk($id_job, $password);
        $featureSet = $this->getFeatureSet();
        $Filter     = MateCatFilter::getInstance( $featureSet, $chunk->source, $chunk->target, [] );

        $src_content = $Filter->fromLayer0ToLayer2( $src_content );
        $trg_content = $Filter->fromLayer0ToLayer2( $trg_content );

        $QA = new QA( $src_content, $trg_content );
        $QA->setFeatureSet( $featureSet );
        $QA->setChunk( $chunk );
        $QA->setIdSegment( $id );
        $QA->setSourceSegLang( $chunk->source );
        $QA->setTargetSegLang( $chunk->target );

        if(isset($characters_counter )){
            $QA->setCharactersCount($characters_counter);
        }

        $QA->performConsistencyCheck();

        $result = array_merge(
            [
                'data' => [],
                'errors' => []
            ],
            $this->invokeLocalWarningsOnFeatures($chunk, $src_content, $trg_content),
            ( new QALocalWarning( $QA, $id ) )->render()
        );

        return $this->response->json($result);
    }

    /**
     * @param $id_job
     * @param $password
     * @return \Chunks_ChunkStruct|\DataAccess_IDaoStruct
     * @throws \Exceptions\NotFoundException
     * @throws Exception
     */
    private function getChunk($id_job, $password)
    {
        $chunk = Chunks_ChunkDao::getByIdAndPassword($id_job, $password);
        $project = $chunk->getProject();
        $this->featureSet->loadForProject( $project );

        return $chunk;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     * @param $src_content
     * @param $trg_content
     * @return array
     * @throws \API\Commons\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    private function invokeLocalWarningsOnFeatures(Chunks_ChunkStruct $chunk, $src_content, $trg_content)
    {
        $data = [];
        $data = $this->featureSet->filter( 'filterSegmentWarnings', $data, [
            'src_content' => $src_content,
            'trg_content' => $trg_content,
            'project'     => $chunk->getProject(),
            'chunk'       => $chunk
        ] );

        return [
             'data' => $data
        ];
    }
}
