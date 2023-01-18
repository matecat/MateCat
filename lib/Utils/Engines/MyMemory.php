<?php

use Validator\Contracts\ValidatorErrorObject;
use Validator\GlossaryCSVValidator;
use Validator\GlossaryCSVValidatorObject;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 18.53
 *
 */
class Engines_MyMemory extends Engines_AbstractEngine {

    /**
     * @var string
     */
    protected $content_type = 'json';

    /**
     * @var array
     */
    protected $_config = [
        'dataRefMap'    => [],
        'segment'       => null,
        'translation'   => null,
        'tnote'         => null,
        'source'        => null,
        'target'        => null,
        'email'         => null,
        'prop'          => null,
        'get_mt'        => 1,
        'id_user'       => null,
        'num_result'    => 3,
        'mt_only'       => false,
        'isConcordance' => false,
        'isGlossary'    => false,
    ];

    /**
     * @param $engineRecord
     *
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->engineRecord->type != "TM" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a TMS engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $rawValue
     *
     * @return Engines_Results_AbstractResponse
     */
    protected function _decode( $rawValue ) {
        $args         = func_get_args();
        $functionName = $args[ 2 ];

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $dataRefMap = isset($this->_config['dataRefMap']) ? $this->_config['dataRefMap'] : [];

        $result_object = null;

        switch ( $functionName ) {

            case 'glossary_domains_relative_url':
                $result_object = Engines_Results_MyMemory_DomainsResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_check_relative_url':
                $result_object = Engines_Results_MyMemory_CheckGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_update_relative_url':
                $result_object = Engines_Results_MyMemory_UpdateGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_delete_relative_url':
                $result_object = Engines_Results_MyMemory_DeleteGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_set_relative_url':
                $result_object = Engines_Results_MyMemory_SetGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_get_relative_url':
                $result_object = Engines_Results_MyMemory_GetGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_keys_relative_url':
                $result_object = Engines_Results_MyMemory_KeysGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'tags_projection' :
                $result_object = Engines_Results_MyMemory_TagProjectionResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'api_key_check_auth_url':
                $result_object = Engines_Results_MyMemory_AuthKeyResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'api_key_create_user_url':
                $result_object = Engines_Results_MyMemory_CreateUserResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;

            case 'glossary_import_status_relative_url':
            case 'glossary_import_relative_url':
            case 'tmx_import_relative_url':
            case 'tmx_status_relative_url':
                $result_object = Engines_Results_MyMemory_TmxResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'tmx_export_create_url' :
            case 'tmx_export_check_url' :
            case 'tmx_export_email_url' :
            case 'glossary_export_relative_url' :
                $result_object = Engines_Results_MyMemory_ExportResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'analyze_url':
                $result_object = Engines_Results_MyMemory_AnalyzeResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'contribute_relative_url':
            case 'update_relative_url':
                $result_object = Engines_Results_MyMemory_SetContributionResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            default:

                if ( isset( $decoded[ 'matches' ] ) && !empty( $decoded[ 'matches' ] ) ) {
                    foreach ( $decoded[ 'matches' ] as $pos => $match ) {
                        $decoded[ 'matches' ][ $pos ][ 'segment' ]     = $this->_resetSpecialStrings( $match[ 'segment' ] );
                        $decoded[ 'matches' ][ $pos ][ 'translation' ] = $this->_resetSpecialStrings( $match[ 'translation' ] );
                    }
                }

                $result_object = Engines_Results_MyMemory_TMS::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
        }

        return $result_object;
    }

    /**
     * This method is used for help to rebuild result from MyMemory.
     * Because when in CURL you send something using method POST and value's param start with "@"
     * he assume you are sending a file.
     *
     * Passing prefix you left before, this method, rebuild result putting prefix at start of translated phrase.
     *
     * @param $prefix
     *
     * @return array
     */
    private function rebuildResult( $prefix ) {

        if ( !empty( $this->result->responseData[ 'translatedText' ] ) ) {
            $this->result->responseData[ 'translatedText' ] = $prefix . $this->result->responseData[ 'translatedText' ];
        }

        if ( !empty( $this->result->matches ) ) {
            $matches_keys = [ 'raw_segment', 'segment', 'translation', 'raw_translation' ];
            foreach ( $this->result->matches as $match ) {
                foreach ( $matches_keys as $match_key ) {
                    $match->$match_key = $prefix . $match->$match_key;
                }
            }
        }

        return $this->result;

    }

    /**
     * @param $_config
     *
     * @return array
     * @throws \Exceptions\NotFoundException
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );
        if ( preg_match( "/^(-?@-?)/", $_config[ 'segment' ], $segment_file_chr ) ) {
            $_config[ 'segment' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        }

        $parameters               = [];
        $parameters[ 'q' ]        = $_config[ 'segment' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'mt' ]       = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]   = $_config[ 'num_result' ];

        ( @$_config[ 'onlyprivate' ] ? $parameters[ 'onlyprivate' ] = 1 : null );
        ( @$_config[ 'isConcordance' ] ? $parameters[ 'conc' ] = 'true' : null );
        ( @$_config[ 'isConcordance' ] ? $parameters[ 'extended' ] = '1' : null );
        ( @$_config[ 'mt_only' ] ? $parameters[ 'mtonly' ] = '1' : null );

        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = ltrim( $_config[ 'context_after' ], "@-" );
            $parameters[ 'context_before' ] = ltrim( $_config[ 'context_before' ], "@-" );
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "translate_relative_url" : $function = "gloss_get_relative_url" );


        $parameters = $this->featureSet->filter( 'filterMyMemoryGetParameters', $parameters, $_config );
        $this->call( $function, $parameters, true );

        if ( isset( $segment_file_chr[ 1 ] ) ) {
            $this->rebuildResult( $segment_file_chr[ 1 ] );
        }

        return $this->result;

    }

    /**
     * @param $_config
     *
     * @return array|bool
     */
    public function set( $_config ) {
        $parameters               = [];
        $parameters[ 'seg' ]      = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        $parameters[ 'tra' ]      = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];
        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = preg_replace( "/^(-?@-?)/", "", @$_config[ 'context_after' ] );
            $parameters[ 'context_before' ] = preg_replace( "/^(-?@-?)/", "", @$_config[ 'context_before' ] );
        }
        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "contribute_relative_url" : $function = "gloss_set_relative_url" );

        $this->call( $function, $parameters, true );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return $this->result->responseDetails[ 0 ]; // return the MyMemory ID

    }

    public function update( $_config ) {

        $parameters               = [];
        $parameters[ 'seg' ]      = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        $parameters[ 'tra' ]      = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        $parameters[ 'newseg' ]   = preg_replace( "/^(-?@-?)/", "", $_config[ 'newsegment' ] );
        $parameters[ 'newtra' ]   = preg_replace( "/^(-?@-?)/", "", $_config[ 'newtranslation' ] );
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = preg_replace( "/^(-?@-?)/", "", @$_config[ 'context_after' ] );
            $parameters[ 'context_before' ] = preg_replace( "/^(-?@-?)/", "", @$_config[ 'context_before' ] );
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( "update_relative_url", $parameters, true );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return true;

    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete( $_config ) {

        $parameters               = [];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( isset( $_config[ 'segment' ] ) and isset( $_config[ 'translation' ] ) ) {
            $parameters[ 'seg' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
            $parameters[ 'tra' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        }

        if ( isset( $_config[ 'id_match' ] ) ) {
            $parameters[ 'id' ] = $_config[ 'id_match' ];
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {

            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "delete_relative_url" : $function = "gloss_delete_relative_url" );

        $this->call( $function, $parameters, true );

        if ( $this->result->responseStatus != "200" &&
                ( $this->result->responseStatus != "404" ||
                        $this->result->responseDetails != "NO ID FOUND" )
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function updateGlossary( $_config ) {

        $parameters               = [];
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'newseg' ]   = $_config[ 'newsegment' ];
        $parameters[ 'newtra' ]   = $_config[ 'newtranslation' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'snote' ]    = $_config[ 'tnote' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];
        $parameters[ 'id' ]       = $_config[ 'id_match' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( "gloss_update_relative_url", $parameters );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return true;

    }

    /**
     * Post a file to myMemory
     *
     * Remove the first line from csv ( source and target )
     * and rewrite the csv because MyMemory doesn't want the header line
     *
     * @param            $file
     * @param            $key
     * @param bool|false $name
     *
     * @return Engines_Results_MyMemory_TmxResponse
     * @throws Exception
     */
    public function glossaryImport( $file, $key, $name = false ) {

        try {

            $origFile = new SplFileObject( $file, 'r+' );
            $origFile->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD );

            $tmpFileName = tempnam( "/tmp", 'GLOS' );
            $newFile     = new SplFileObject( $tmpFileName, 'r+' );
            $newFile->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD );

            $index = 0;

            foreach ( $origFile as $line_num => $line ) {

                if(in_array("1", $line)){
                    foreach ($line as $lineKey => $item){
                        if($item == "1"){
                            $line[$lineKey] = "True";
                        }
                    }
                }

                //copy stream to stream
                $index++;
                $newFile->fputcsv( $line );
            }

            $newFile->fflush();

            $origFile = null;
            $newFile  = null;
            copy( $tmpFileName, $file );
            unlink( $tmpFileName );

        } catch ( RuntimeException $e ) {
            $this->result = new Engines_Results_MyMemory_TmxResponse( [
                    "responseStatus"  => 406,
                    "responseData"    => null,
                    "responseDetails" => $e->getMessage()
            ] );

            return $this->result;
        }

        // validate the CSV
        $validateCSVFileErrors = $this->validateCSVFile($file);

        if(count($validateCSVFileErrors) > 0){
            throw new \Exception($validateCSVFileErrors[0]);
        }

        $postFields = [
            'glossary'    => $this->getCurlFile($file),
            'key'         => trim( $key ),
        ];

        if($name and $name !== ''){
            $postFields['key_name'] = $name;
        }

        $this->call( "glossary_import_relative_url", $postFields, true );

        return $this->result;
    }

    /**
     * @param $uuid
     *
     * @return array
     */
    public function getGlossaryImportStatus($uuid)
    {
        $this->call( 'glossary_import_status_relative_url', [
                'uuid' => $uuid
        ], false );

        return $this->result;
    }

    /**
     * @param $key
     * @param $keyName
     * @param $userEmail
     * @param $userName
     *
     * @return array
     */
    public function glossaryExport($key, $keyName, $userEmail, $userName)
    {
        $this->call( 'glossary_export_relative_url', [
            'key' => $key,
            'key_name' => $keyName,
            'user_name' => $userName,
            'user_email' => $userEmail,
        ], true );

        return $this->result;
    }

    /**
     * Poll MM for obtain the status of a write operation
     * using a cyclic barrier
     * (import, update, set, delete)
     *
     * @param $uuid
     * @param $relativeUrl
     */
    private function pollForStatus($uuid, $relativeUrl)
    {
        $limit = 10;
        $sleep = 1;
        $startTime = time();

        do {

            $this->call( $relativeUrl, [
                'uuid' => $uuid
            ], false );

            if($this->result->responseStatus === 202){
                sleep( $sleep );
            }

        } while ( $this->result->responseStatus === 202 and (time() - $startTime) <= $limit );
    }

    /**
     * @param       $source
     * @param       $target
     * @param       $sourceLanguage
     * @param       $targetLanguage
     * @param array $keys
     *
     * @return array
     */
    public function glossaryCheck($source, $target, $sourceLanguage, $targetLanguage, $keys = [])
    {
        $payload = [
                'de' => \INIT::$MYMEMORY_API_KEY,
                'source' => $source,
                'target' => $target,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'keys' => $keys,
        ];
        $this->call( "glossary_check_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param array $keys
     *
     * @return array
     */
    public function glossaryDomains($keys = [])
    {
        $payload = [
            'de' => \INIT::$MYMEMORY_API_KEY,
            'keys' => $keys,
        ];
        $this->call( "glossary_domains_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $term
     *
     * @return array
     */
    public function glossaryDelete($idSegment, $idJob, $password, $term)
    {
        $payload = [
                'de' => \INIT::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job" => $idJob,
                "password" => $password,
                "term" => $term,
        ];
        $this->call( "glossary_delete_relative_url", $payload, true, true );

        if( $this->result->responseData === 'OK' and isset($this->result->responseDetails)){
            $uuid = $this->result->responseDetails;
            $this->pollForStatus($uuid, 'glossary_entry_status_relative_url');
        }

        return $this->result;
    }

    /**
     * @param $source
     * @param $sourceLanguage
     * @param $targetLanguage
     * @param $keys
     *
     * @return array
     */
    public function glossaryGet($source, $sourceLanguage, $targetLanguage, $keys)
    {
        $payload = [
            'de' => \INIT::$MYMEMORY_API_KEY,
            "source" => $source,
            "source_language" => $sourceLanguage,
            "target_language" => $targetLanguage,
            "keys" => $keys,
        ];

        $this->call( "glossary_get_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param array $keys
     *
     * @return array
     */
    public function glossaryKeys($sourceLanguage, $targetLanguage, $keys = [])
    {
        $payload = [
            'de' => \INIT::$MYMEMORY_API_KEY,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'keys' => $keys,
        ];
        $this->call( "glossary_keys_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $term
     *
     * @return array
     */
    public function glossarySet($idSegment, $idJob, $password, $term)
    {
        $payload = [
                'de' => \INIT::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job" => $idJob,
                "password" => $password,
                "term" => $term,
        ];

        $this->call( "glossary_set_relative_url", $payload, true, true );

//        if( $this->result->responseData === 'OK' and isset($this->result->responseDetails)){
//            $uuid = $this->result->responseDetails;
//            $this->pollForStatus($uuid, 'glossary_entry_status_relative_url');
//        }

        return $this->result;
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $term
     *
     * @return array
     */
    public function glossaryUpdate($idSegment, $idJob, $password, $term)
    {
        $payload = [
                'de' => \INIT::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job" => $idJob,
                "password" => $password,
                "term" => $term,
        ];
        $this->call( "glossary_update_relative_url", $payload, true, true );

        if( $this->result->responseData === 'OK' and isset($this->result->responseDetails)){
            $uuid = $this->result->responseDetails;
            $this->pollForStatus($uuid, 'glossary_entry_status_relative_url');
        }

        return $this->result;
    }

    /**
     * @param $file
     * @return ValidatorErrorObject[]
     * @throws Exception
     */
    private function validateCSVFile( $file ) {
        $validatorObject      = new GlossaryCSVValidatorObject();
        $validatorObject->csv = $file;
        $validator            = new GlossaryCSVValidator();
        $validator->validate( $validatorObject );

        return $validator->getErrors();
    }

    public function import( $file, $key, $name = false ) {

        $postFields = [
                'tmx'  => $this->getCurlFile($file),
                'name' => $name,
                'key'  => trim( $key )
        ];

        $this->call( "tmx_import_relative_url", $postFields, true );

        return $this->result;
    }

    public function getStatus( $key, $name = false ) {

        $parameters          = [];
        $parameters[ 'key' ] = trim( $key );

        //if provided, add name parameter
        if ( $name ) {
            $parameters[ 'name' ] = $name;
        }

        $this->call( 'tmx_status_relative_url', $parameters );

        return $this->result;
    }

    /**
     * Memory Export creation request.
     *
     * <ul>
     *  <li>key: MyMemory key</li>
     *  <li>source: all segments with source language ( default 'all' )</li>
     *  <li>target: all segments with target language ( default 'all' )</li>
     *  <li>strict: strict check for languages ( no back translations ), only source-target and not target-source
     * </ul>
     *
     * @param string       $key
     * @param null|string  $source
     * @param null|string  $target
     * @param null|boolean $strict
     *
     * @return array
     */
    public function createExport( $key, $source = null, $target = null, $strict = null ) {

        $parameters = [];

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->call( 'tmx_export_create_url', $parameters );

        return $this->result;

    }

    /**
     * Memory Export check for status,
     * <br />invoke with the same parameters of createExport
     *
     * @param      $key
     * @param null $source
     * @param null $target
     * @param null $strict
     *
     * @return mixed
     * @see Engines_MyMemory::createExport
     *      ,
     *      $this->name,
     *      $userMail,
     *      $userName,
     *      $userSurname
     */
    public function checkExport( $key, $source = null, $target = null, $strict = null ) {

        $parameters = [];

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->call( 'tmx_export_check_url', $parameters );

        return $this->result;

    }

    /**
     * Get the zip file with the TM inside or a "EMPTY ARCHIVE" message
     * <br> if there are not segments inside the TM
     *
     * @param $key
     * @param $hashPass
     * @param $isGlossary
     * @param $fileName
     *
     * @return resource
     *
     * @throws Exception
     */
    public function downloadExport( $key, $hashPass = null, $isGlossary = false, $fileName = null ) {

        $parameters = [];

        $parameters[ 'key' ]  = trim( $key );
        $parameters[ 'pass' ] = trim( $hashPass );

        ( $isGlossary ? $method = "glossary_export_relative_url" : $method = "tmx_export_download_url" );

        if ( is_null( $fileName ) ) {
            $fileName = "/tmp/TMX" . $key;
        }
        $handle = fopen( $fileName, "w+" );

        $this->_setAdditionalCurlParams( [
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FILE    => $handle
        ] );

        $this->call( $method, $parameters );

        /**
         * Code block not useful at moment until MyMemory does not respond with HTTP 404
         *
         * $result Engines_Results_MyMemory_ExportResponse
         */
        /*
         *
         *        if ( $this->result->responseStatus >= 400 ) {
         *            throw new Exception( $this->result->error->message, $this->result->responseStatus );
         *        }
         *        fwrite( $handle, $this->result );
         */

        fflush( $handle );
        rewind( $handle );

        return $handle;

    }

    /**
     * Calls the MyMemory endpoint to send the TMX download URL to the user e-mail
     *
     * @param $key
     * @param $name
     * @param $userEmail
     * @param $userName
     * @param $userSurname
     *
     *
     * @return Engines_Results_MyMemory_ExportResponse
     * @throws Exception
     *
     */
    public function emailExport( $key, $name, $userEmail, $userName, $userSurname ) {
        $parameters = [];

        $parameters[ 'key' ]        = trim( $key );
        $parameters[ 'user_email' ] = trim( $userEmail );
        $parameters[ 'user_name' ]  = trim( $userName ) . " " . trim( $userSurname );
        ( !empty( $name ) ? $parameters[ 'zip_name' ] = $name : $parameters[ 'zip_name' ] = $key );
        $parameters[ 'zip_name' ] = $parameters[ 'zip_name' ] . ".zip";

        $this->call( 'tmx_export_email_url', $parameters );

        /**
         * $result Engines_Results_MyMemory_ExportResponse
         */
        if ( $this->result->responseStatus >= 400 ) {
            throw new Exception( $this->result->error->message, $this->result->responseStatus );
        }

        Log::doJsonLog( 'TMX exported to E-mail.' );

        return $this->result;
    }

    /*****************************************/
    public function createMyMemoryKey() {

        //query db
        $this->call( 'api_key_create_user_url' );

        if ( !$this->result instanceof Engines_Results_MyMemory_CreateUserResponse ) {
            if ( empty( $this->result ) || $this->result[ 'error' ] || $this->result[ 'error' ][ 'code' ] != 200 ) {
                throw new Exception( "Private TM key .", -1 );
            }
        }

        unset( $this->result->responseStatus );
        unset( $this->result->responseDetails );
        unset( $this->result->responseData );

        return $this->result;

    }

    /**
     * Checks for MyMemory Api Key correctness
     *
     * Filter Validate returns true/false for correct/not correct key and NULL is returned for all non-boolean values. ( 404, html, etc. )
     *
     * @param $apiKey
     *
     * @return bool|null
     * @throws Exception
     */
    public function checkCorrectKey( $apiKey ) {

        $postFields = [
                'key' => trim( $apiKey )
        ];

        //query db
//        $this->doQuery( 'api_key_check_auth', $postFields );
        $this->call( 'api_key_check_auth_url', $postFields );

        if ( !$this->result->responseStatus == 200 ) {
            Log::doJsonLog( "Error: The check for MyMemory private key correctness failed: " . $this->result[ 'error' ][ 'message' ] . " ErrNum: " . $this->result[ 'error' ][ 'code' ] );
            throw new Exception( "Error: The private TM key you entered ( $apiKey ) seems to be invalid. Please, check that the key is correct.", -2 );
        }

        $isValidKey = filter_var( $this->result->responseData, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The private TM key you entered seems to be invalid: $apiKey", -3 );
        }

        return $isValidKey;

    }

    /******************************************/

    public function fastAnalysis( &$segs_array ) {
        if ( !is_array( $segs_array ) ) {

            return null;
        }
        $json_segs = json_encode( $segs_array );

        $parameters[ 'fast' ] = "1";
        $parameters[ 'df' ]   = "matecat_array";
        $parameters[ 'segs' ] = $json_segs;

        $this->_setAdditionalCurlParams( [
                        CURLOPT_TIMEOUT => 300
                ]
        );

        $this->engineRecord['base_url'] = "https://analyze.mymemory.translated.net/api/v1";

        $this->call( "analyze_url", array_values( $segs_array ), true, true );

        return $this->result;

    }

    /**
     * Detect language for an array of file's segments.
     *
     * @param $segs_array Array An array whose keys are file IDs and values are array of segments.
     *
     * @return mixed
     */
    public function detectLanguage( $segs_array, $lang_detect_files ) {
        //In this array we will put a meaningful string for each job.
        $segmentsToBeDetected = [];


        /**
         * @var $segs_array ArrayObject
         */
        $arrayIterator = $segs_array->getIterator();

        $counter = 0;
        //iterate through files and extract a meaningful
        //string long at least 150 characters for language detection
        while ( $arrayIterator->valid() ) {
            $currFileName = key( $lang_detect_files );

            if ( $lang_detect_files[ $currFileName ] == "skip" ) {
                //this will force google to answer with "und" language code
                $segmentsToBeDetected[] = "q[$counter]=1";

                next( $lang_detect_files );
                $arrayIterator->next();
                $counter++;
                continue;
            }

            $currFileId = $arrayIterator->key();

            $currFile = $arrayIterator->current();

            /**
             * @var $currFileIterator ArrayIterator
             */
            $segmentArray = $currFile->getIterator()->current();

            //take first 50 segments
            $segmentArray = array_slice( $segmentArray, 0, 50 );

            foreach ( $segmentArray as $i => $singleSegment ) {
                $singleSegment = explode( ",", $singleSegment );
                $singleSegment = array_slice( $singleSegment, 3, 1 );

                //remove tags, duplicated spaces and all not Unicode Letter
                $singleSegment[ 0 ] = preg_replace( [ "#<[^<>]*>#", "#\x20{2,}#", '#\PL+#u' ], [
                        "", " ", " "
                ], $singleSegment[ 0 ] );

                //remove not useful spaces
                $singleSegment[ 0 ] = preg_replace( "#\x20{2,}#", " ", $singleSegment[ 0 ] );

                $segmentArray[ $i ] = $singleSegment[ 0 ];
            }

            usort( $segmentArray, function ( $a, $b ) {
                return strlen( $a ) >= strlen( $b );
            } );

            $textToBeDetected = "";
            /**
             * take first 150 characters starting from the longest segment in the slice
             */
            for ( $i = count( $segmentArray ) - 1; $i >= 0; $i-- ) {
                $textToBeDetected .= " " . trim( $segmentArray[ $i ], "'" );
                if ( mb_strlen( $textToBeDetected ) > 150 ) {
                    break;
                }
            }
            $segmentsToBeDetected[] = "q[$counter]=" . urlencode( $textToBeDetected );

            next( $lang_detect_files );
            $arrayIterator->next();
            $counter++;
        }

        $curl_parameters = implode( "&", $segmentsToBeDetected ) . "&of=json";

        Log::doJsonLog( "DETECT LANG :", $segmentsToBeDetected );

        $options = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => 0,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $curl_parameters,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        ];

        $url = strtolower( $this->base_url . "/" . $this->detect_language_url );

        $mh        = new MultiCurlHandler();
        $tokenHash = $mh->createResource( $url, $options );
        Log::doJsonLog( "DETECT LANG TOKENHASH: $tokenHash" );

        $mh->multiExec();

        $res = $mh->getAllContents();
        Log::doJsonLog( "DETECT LANG RES:", $res );

        return json_decode( $res[ $tokenHash ], true );
    }


    /**
     * MyMemory private endpoint
     *
     * @param $config
     *
     * @return array|Engines_Results_MyMemory_TagProjectionResponse
     */
    public function getTagProjection( $config ) {

        // set dataRefMap needed to instance
        // Engines_Results_MyMemory_TagProjectionResponse class
        $this->_config[ 'dataRefMap' ] = isset($config[ 'dataRefMap' ]) ? $config[ 'dataRefMap' ] : [];

        $parameters                 = [];
        $parameters[ 's' ]          = $config[ 'source' ];
        $parameters[ 't' ]          = $config[ 'target' ];
        $parameters[ 'hint' ]       = $config[ 'suggestion' ];

        $this->_setAdditionalCurlParams( [
                CURLOPT_FOLLOWLOCATION => true,
        ] );

        $this->engineRecord->base_url = parse_url( $this->engineRecord->base_url, PHP_URL_HOST ) . ":10000";

        $this->engineRecord->others[ 'tags_projection' ] .= '/' . $config[ 'source_lang' ] . "/" . $config[ 'target_lang' ] . "/";

        $this->call( 'tags_projection', $parameters );

        return $this->result;

    }

}
