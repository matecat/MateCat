<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use API\V2\Json\QAGlobalWarning;
use API\V2\Json\QALocalWarning;
use Chunks_ChunkDao;
use Exception;
use InvalidArgumentException;
use Jobs_JobStruct;
use Klein\Response;
use LQA\QA;
use Matecat\SubFiltering\MateCatFilter;
use Segments_SegmentDao;
use Translations\WarningDao;

class GetWarningController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function global(): Response
    {
        try {
            $request = $this->validateTheGlobalRequest();
            $id_job = $request['id_job'];
            $password = $request['password'];
            $token = $request['token'];

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
                $qa->render()
            );

            $result = $this->featureSet->filter( 'filterGlobalWarnings', $result, [
                'chunk' => $chunk,
            ] );

            return $this->response->json($result);
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheGlobalRequest(): array
    {
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $token = filter_var( $this->request->param( 'token' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException("Empty id job", -1);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("Empty job password", -2);
        }

        return [
            'id_job' => $id_job,
            'password' => $password,
            'token' => $token,
        ];
    }

    /**
     * @return Response
     */
    public function local(): Response
    {
        try {
            $request = $this->validateTheLocalRequest();
            $id = $request['id'];
            $id_job = $request['id_job'];
            $src_content = $request['src_content'];
            $trg_content = $request['trg_content'];
            $password = $request['password'];
            $token = $request['token'];
            $logs = $request['logs'];
            $segment_status = $request['segment_status'];
            $characters_counter = $request['characters_counter'];

            /**
             * Update 2015/08/11, roberto@translated.net
             * getWarning needs the segment status too because of a bug:
             *   sometimes the client calls getWarning and sends an empty trg_content
             *   because the suggestion has not been loaded yet.
             *   This happens only if segment is in status NEW
             */
            if ( $segment_status == 'new' and empty( $trg_content )) {
                return $this->response->json([
                    'data' => [],
                    'errors' => []
                ]);
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

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheLocalRequest(): array
    {
        $id = filter_var( $this->request->param( 'id' ), FILTER_SANITIZE_NUMBER_INT );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $src_content = filter_var( $this->request->param( 'src_content' ), FILTER_UNSAFE_RAW );
        $trg_content = filter_var( $this->request->param( 'trg_content' ), FILTER_UNSAFE_RAW );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $token = filter_var( $this->request->param( 'token' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $logs = filter_var( $this->request->param( 'logs' ), FILTER_UNSAFE_RAW );
        $segment_status = filter_var( $this->request->param( 'segment_status' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $characters_counter = filter_var( $this->request->param( 'characters_counter' ), FILTER_SANITIZE_NUMBER_INT );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException("Empty id job", -1);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("Empty job password", -2);
        }

        /**
         * Update 2015/08/11, roberto@translated.net
         * getWarning needs the segment status too because of a bug:
         *   sometimes the client calls getWarning and sends an empty trg_content
         *   because the suggestion has not been loaded yet.
         *   This happens only if segment is in status NEW
         */
        if(empty($segment_status)){
            $segment_status = 'draft';
        }

        return [
            'id' => $id,
            'id_job' => $id_job,
            'src_content' => $src_content,
            'trg_content' => $trg_content,
            'password' => $password,
            'token' => $token,
            'logs' => $logs,
            'segment_status' => $segment_status,
            'characters_counter' => $characters_counter,
        ];
    }

    /**
     * @param $id_job
     * @param $password
     * @return Jobs_JobStruct|null
     * @throws Exception
     */
    private function getChunk($id_job, $password): ?Jobs_JobStruct
    {
        $chunk = Chunks_ChunkDao::getByIdAndPassword($id_job, $password);
        $project = $chunk->getProject();
        $this->featureSet->loadForProject( $project );

        return $chunk;
    }

    /**
     * @param Jobs_JobStruct $chunk
     * @param $src_content
     * @param $trg_content
     * @return array
     * @throws Exception
     */
    private function invokeLocalWarningsOnFeatures(Jobs_JobStruct $chunk, $src_content, $trg_content): array
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
