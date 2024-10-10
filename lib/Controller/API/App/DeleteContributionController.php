<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkDao;
use Engine;
use Exception;
use INIT;
use Matecat\SubFiltering\MateCatFilter;
use TmKeyManagement_Filter;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use Translations_SegmentTranslationDao;

class DeleteContributionController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function delete()
    {
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $source_lang = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $target_lang = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $source = filter_var( $this->request->param( 'seg' ), FILTER_UNSAFE_RAW );
        $target = filter_var( $this->request->param( 'tra' ), FILTER_UNSAFE_RAW );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $id_translator = filter_var( $this->request->param( 'id_translator' ), FILTER_SANITIZE_NUMBER_INT );
        $id_match = filter_var( $this->request->param( 'id_match' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $received_password = filter_var( $this->request->param( 'current_password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        
        $source = trim($source);
        $target = trim($target);
        $password = trim($password);
        $received_password = trim($received_password);

        $errors = [];

        if ( empty( $source_lang ) ) {
            $errors[] = [
                "code" => -1,
                "message" => "missing source_lang"
            ];
        }

        if ( empty( $target_lang ) ) {
            $errors[] = [
                "code" => -2,
                "message" => "missing target_lang"
            ];
        }

        if ( empty( $source ) ) {
            $errors[] = [
                "code" => -3,
                "message" => "missing source"
            ];
        }

        if ( empty( $target ) ) {
            $errors[] = [
                "code" => -4,
                "message" => "missing target"
            ];
        }

        if(!empty($errors)){
            $this->response->code(400);

            return $this->response->json($errors);
        }

        //check Job password
        $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $id_job, $password );
        $this->featureSet->loadForProject( $jobStruct->getProject() );

        $tm_keys = $jobStruct[ 'tm_keys' ];

        $tms    = Engine::getInstance( $jobStruct[ 'id_tms' ] );
        $config = $tms->getConfigStruct();

        $Filter                  = MateCatFilter::getInstance( $this->getFeatureSet(), $source_lang, $target_lang, [] );
        $config[ 'segment' ]     = $Filter->fromLayer2ToLayer0( $source );
        $config[ 'translation' ] = $Filter->fromLayer2ToLayer0( $target );
        $config[ 'source' ]      = $source_lang;
        $config[ 'target' ]      = $target_lang;
        $config[ 'email' ]       = INIT::$MYMEMORY_API_KEY;
        $config[ 'id_user' ]     = [];
        $config[ 'id_match' ]    = $id_match;

        //get job's TM keys
        try {
            $userRole = ( $this->isRevision($id_job, $password) ) ?  TmKeyManagement_Filter::ROLE_REVISOR : TmKeyManagement_Filter::ROLE_TRANSLATOR;

            //get TM keys with read grants
            $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'tm', $this->user->uid, $userRole );
            $tm_keys = TmKeyManagement_TmKeyManagement::filterOutByOwnership( $tm_keys, $this->user->email, $jobStruct[ 'owner' ] );

        } catch ( Exception $e ) {
            $errors[] = [
                "code" => -11,
                "message" => "Cannot retrieve TM keys info."
            ];

            $this->response->code($e->getCode() >= 400 ? $e->getCode() : 500);

            return $this->response->json($errors);
        }

        //prepare the errors report
        $set_code = [];

        /**
         * @var $tm_key TmKeyManagement_TmKeyStruct
         */

        //if there's no key
        if ( empty( $tm_keys ) ) {
            //try deleting anyway, it may be a public segment and it may work
            $TMS_RESULT = $tms->delete( $config );

            if($TMS_RESULT){
                $this->updateSuggestionsArray($id_segment, $id_job, $id_match);
            }

            $set_code[] = $TMS_RESULT;
        } else {
            //loop over the list of keys
            foreach ( $tm_keys as $tm_key ) {
                //issue a separate call for each key
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT          = $tms->delete( $config );

                if($TMS_RESULT){
                    $this->updateSuggestionsArray($id_segment, $id_job, $id_match);
                }

                $set_code[]          = $TMS_RESULT;
            }
        }

        $set_successful = true;
        if ( array_search( false, $set_code, true ) ) {
            //There's an errors
            $set_successful = false;
        }

        return $this->response->json([
            'data' => ( $set_successful ? "OK" : null ),
            'code' => $set_successful,
        ]);
    }

    /**
     * Update suggestions array
     *
     * @param $id_segment
     * @param $id_job
     * @param $id_match
     */
    private function updateSuggestionsArray($id_segment, $id_job, $id_match) {

        $segmentTranslation = Translations_SegmentTranslationDao::findBySegmentAndJob($id_segment, $id_job);
        $oldSuggestionsArray = json_decode($segmentTranslation->suggestions_array);

        if(!empty($oldSuggestionsArray)){

            $newSuggestionsArray = [];
            foreach ($oldSuggestionsArray as $suggestion){
                if($suggestion->id != $id_match){
                    $newSuggestionsArray[] = $suggestion;
                }
            }

            Translations_SegmentTranslationDao::updateSuggestionsArray($id_segment, $newSuggestionsArray);
        }
    }
}