<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkDao;
use Engine;
use Engines_MyMemory;
use Exception;
use INIT;
use InvalidArgumentException;
use Log;
use Matecat\SubFiltering\MateCatFilter;
use Segments_SegmentOriginalDataDao;
use TmKeyManagement_Filter;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use Translations_SegmentTranslationDao;
use Utils;

class GetTagProjectionController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function call()
    {
        try {
            Log::$fileName = 'tagProjection.log';

            $request = $this->validateTheRequest();
            $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $request['id_job'], $request['password'] );
            $this->featureSet->loadForProject( $jobStruct->getProject() );

            /**
             * @var $engine Engines_MyMemory
             */
            $engine = Engine::getInstance( 1 );
            $engine->setFeatureSet( $this->featureSet );

            $dataRefMap = Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $request['id_segment'] );
            $Filter     = MateCatFilter::getInstance( $this->getFeatureSet(), $request['source_lang'], $request['target_lang'], $dataRefMap );

            $config                  = [];
            $config[ 'dataRefMap' ]  = $dataRefMap;
            $config[ 'source' ]      = $Filter->fromLayer2ToLayer1( $request['source'] );
            $config[ 'target' ]      = $Filter->fromLayer2ToLayer1( $request['target'] );
            $config[ 'source_lang' ] = $request['source_lang'];
            $config[ 'target_lang' ] = $request['target_lang'];
            $config[ 'suggestion' ]  = $Filter->fromLayer2ToLayer1( $request['suggestion'] );

            $result = $engine->getTagProjection( $config );

            // no errors, response ok
            if ( empty( $result->error ) ) {
                return $this->response->json([
                    'code' => 0,
                    'data' => [
                        'translation' => $Filter->fromLayer1ToLayer2( $result->responseData )
                    ],
                ]);
            }

            $this->logTagProjection(
                [
                    'request' => $config,
                    'error'   => $result->error
                ]
            );

            $this->response->code($result->error->code > 400 ? $result->error->code : 500);

            return $this->response->json([
                'code' => $result->error->code,
                'errors' => $result->error,
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest()
    {
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $source = filter_var( $this->request->param( 'source' ), FILTER_UNSAFE_RAW  );
        $target = filter_var( $this->request->param( 'target' ), FILTER_UNSAFE_RAW  );
        $suggestion = filter_var( $this->request->param( 'suggestion' ), FILTER_UNSAFE_RAW  );
        $source_lang = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $target_lang = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( is_null( $source ) || $source === '' ) {
            throw new InvalidArgumentException("missing source segment" , -1);
        }

        if ( is_null( $target ) || $target === '' ) {
            throw new InvalidArgumentException("missing target segment" , -2);
        }

        if ( empty( $source_lang ) ) {
            throw new InvalidArgumentException("missing source lang" , -3);
        }

        if ( empty( $target_lang ) ) {
            throw new InvalidArgumentException("missing target lang" , -4);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("missing job password" , -5);
        }

        if ( empty( $id_segment ) ) {
            throw new InvalidArgumentException("missing id segment" , -6);
        }

        if ( empty( $id_job ) ) {
            $msg = "\n\n Critical. Quit. \n\n " . var_export( $_POST, true );
            $this->log( $msg );
            Utils::sendErrMailReport( $msg );

            throw new InvalidArgumentException("id_job not valid" , -4);
        }

        return [
            'id_segment' => $id_segment,
            'id_job' => $id_job,
            'password' => $password,
            'source' => $source,
            'target' => $target,
            'suggestion' => $suggestion,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
        ];
    }


    /**
     * @param $data
     * @param null $msg
     */
    private function logTagProjection( $data, $msg = null )
    {
        if ( !$msg ) {
            Log::doJsonLog( $data );
        } else {
            Log::doJsonLog( $msg );
        }
    }
}