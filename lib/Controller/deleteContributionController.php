<?php

use Matecat\SubFiltering\MateCatFilter;

class deleteContributionController extends ajaxController {

    protected $id_segment;
    protected $id_job;
    protected $password;
    private   $id_match;
    private   $target;
    private   $source;
    private   $source_lang;
    private   $target_lang;
    private   $id_translator;
    private   $tm_keys;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_segment'       => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'source_lang'      => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'target_lang'      => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'seg'              => [ 'filter' => FILTER_UNSAFE_RAW ],
                'tra'              => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id_match'         => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'         => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'current_password' => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'id_job'           => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_segment        = $__postInput[ 'id_segment' ];
        $this->source_lang       = $__postInput[ 'source_lang' ];
        $this->target_lang       = $__postInput[ 'target_lang' ];
        $this->source            = trim( $__postInput[ 'seg' ] );
        $this->target            = trim( $__postInput[ 'tra' ] );
        $this->id_translator     = (isset($__postInput[ 'id_translator' ]) ? trim( $__postInput[ 'id_translator' ] ) : null );
        $this->password          = trim( $__postInput[ 'password' ] );
        $this->received_password = trim( $__postInput[ 'current_password' ] );
        $this->id_match          = $__postInput[ 'id_match' ];
        $this->id_job            = $__postInput[ 'id_job' ];

    }

    public function doAction() {

        if ( empty( $this->source_lang ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "missing source_lang" ];
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => "missing target_lang" ];
        }

        if ( empty( $this->source ) ) {
            $this->result[ 'errors' ][] = [ "code" => -3, "message" => "missing source" ];
        }

        if ( empty( $this->target ) ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => "missing target" ];
        }

        //check Job password
        $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $this->id_job, $this->password );
        $this->featureSet->loadForProject( $jobStruct->getProject() );

        $this->tm_keys = $jobStruct[ 'tm_keys' ];
        $this->readLoginInfo();

        $tms    = Engine::getInstance( $jobStruct[ 'id_tms' ] );
        $config = $tms->getConfigStruct();

        $Filter                  = MateCatFilter::getInstance( $this->getFeatureSet(), $this->source_lang, $this->target_lang, [] );
        $config[ 'segment' ]     = $Filter->fromLayer2ToLayer0( $this->source );
        $config[ 'translation' ] = $Filter->fromLayer2ToLayer0( $this->target );
        $config[ 'source' ]      = $this->source_lang;
        $config[ 'target' ]      = $this->target_lang;
        $config[ 'email' ]       = INIT::$MYMEMORY_API_KEY;
        $config[ 'id_user' ]     = [];
        $config[ 'id_match' ]    = $this->id_match;

        //get job's TM keys
        try {

            $tm_keys = $this->tm_keys;

            if ( self::isRevision() ) {
                $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
            }

            //get TM keys with read grants
            $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'w', 'tm', $this->user->uid, $this->userRole );
            $tm_keys = TmKeyManagement_TmKeyManagement::filterOutByOwnership( $tm_keys, $this->user->email, $jobStruct[ 'owner' ] );

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -11, "message" => "Cannot retrieve TM keys info." ];

            return;
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
                $this->updateSuggestionsArray();
            }

            $set_code[] = $TMS_RESULT;
        } else {
            //loop over the list of keys
            foreach ( $tm_keys as $tm_key ) {
                //issue a separate call for each key
                $config[ 'id_user' ] = $tm_key->key;
                $TMS_RESULT          = $tms->delete( $config );

                if($TMS_RESULT){
                    $this->updateSuggestionsArray();
                }

                $set_code[]          = $TMS_RESULT;
            }
        }

        $set_successful = true;
        if ( array_search( false, $set_code, true ) ) {
            //There's an errors
            $set_successful = false;
        }

        $this->result[ 'data' ] = ( $set_successful ? "OK" : null );
        $this->result[ 'code' ] = $set_successful;

    }

    /**
     * update suggestions array
     */
    private function updateSuggestionsArray() {

        $segmentTranslation = Translations_SegmentTranslationDao::findBySegmentAndJob($this->id_segment, $this->id_job);
        $oldSuggestionsArray = json_decode($segmentTranslation->suggestions_array);

        if(!empty($oldSuggestionsArray)){

            $newSuggestionsArray = [];
            foreach ($oldSuggestionsArray as $suggestion){
                if($suggestion->id != $this->id_match){
                    $newSuggestionsArray[] = $suggestion;
                }
            }

            Translations_SegmentTranslationDao::updateSuggestionsArray($this->id_segment, $newSuggestionsArray);
        }
    }

}
