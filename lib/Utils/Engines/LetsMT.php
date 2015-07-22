<?php

/**
 * @author Rihards Krislauks rihards.krislauks@tilde.lv / rihards.krislauks@gmail.com
 */

/**
 * Class Engines_LetsMT
 * @property string oauth_url
 * @property int token_endlife
 * @property string token
 * @property string   client_id
 * @property string   client_secret
 */

class Engines_LetsMT extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $_config = array(
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
    );

    public function __construct($engineRecord) {
        parent::__construct($engineRecord);
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    protected function _fixLangCode( $lang ) {

        $lang = strtolower( trim( $lang ) );
        $l = explode( "-", $lang );
        return $l[0];

    }

    /**
     * @param $rawValue
     *
     * @return array
     */
    protected function _decode( $rawValue ){

        $all_args =  func_get_args();

        if( is_string( $rawValue ) ) {
            $parsed = json_decode( $rawValue, true );
            if(!empty($parsed['translation'])){
                // this is a response from a translate request
                
                $decoded = array(
                            'data' => array(
                                    "translations" => array(
                                            array( 'translatedText' =>
                                                $this->use_qe && floatval($parsed['qualityEstimate']) < $this->minimum_qe ?
                                                    "" : $this->_resetSpecialStrings($parsed['translation']))
                                    )
                            )
                    );
                
                $mt_result = new Engines_Results_MT( $decoded );

                if ( $mt_result->error->code < 0 ) {
                    $mt_result = $mt_result->get_as_array();
                    $mt_result['error'] = (array)$mt_result['error'];
                    return $mt_result;
                }

                $mt_match_res = new Engines_Results_MyMemory_Matches(
                        $this->_resetSpecialStrings( $all_args[ 1 ][ 'text' ] ),
                        $mt_result->translatedText,
                        100 - $this->getPenalty() . "%",
                        "MT-" . $this->getName(),
                        date( "Y-m-d" )
                );

                $mt_res                          = $mt_match_res->get_as_array();
                $mt_res[ 'sentence_confidence' ] = $mt_result->sentence_confidence; //can be null

                return $mt_res;
            } elseif (!empty($parsed['System'])){
                // this is a response from a getSystemList request
                
                $decoded = array();
                foreach($parsed['System'] as $systemData){
                    $status = "";
                    foreach ($systemData['Metadata'] as $value){
                        if ($value['Key'] === 'status'){
                            switch ($value['Value'])
                            {
                                case "running":
                                    $status = "Running";
                                    break;
                                case "queuingtransl":
                                    $status = "Queuing";
                                    break;
                                case "notstarted":
                                    $status = "Not Started";
                                    break;
                                case "nottrained":
                                    $status = "Not Trained";
                                    break;
                                case "error":
                                    $status = "Not Trained";
                                    break;
                                case "training":
                                    $status = "Training";
                                    break;
                                case "standby":
                                    $status = "Standby";
                                    break;
                                default:
                                    $status = $value['Value'];
                                    break;
                            }
                            //strSysOnlineStatus = prop.Value;
                            break;
                        }
                    }
                    $systemName = sprintf('%s-%s %s (%s)',
                                            $systemData['SourceLanguage']['Code'],
                                            $systemData['TargetLanguage']['Code'],
                                            $systemData['Title']['Text'],
                                            $status);
                    $systemMetadata = array('source-language-code' => $systemData['SourceLanguage']['Code'],
                                            'target-language-code' => $systemData['TargetLanguage']['Code'],
                                            'source-language-name' => $systemData['SourceLanguage']['Name']['Text'],
                                            'target-language-name' => $systemData['TargetLanguage']['Name']['Text'],
                                            'status'               => $status
                        );
                    $decoded[$systemData['ID']] = array('name'     => $systemName,
                                                        'metadata' => $systemMetadata
                        );
                }
                asort($decoded);
                
                return $decoded;
            } elseif (!empty($parsed[0]) && !empty($parsed[0]['CorpusId'])){
                // this is a response from getSystemTermCorpora request
                
                $decoded = array();
                foreach($parsed as $termData){
                    $decoded[$termData['CorpusId']] = $termData['Title'];
                }
            }
        } else {
            $decoded = $rawValue;
            // in case of an invalid user id http status 401 is returned.
            // display a more user friendly message
            if (strpos($decoded['error'], 'http status 401') !== false) {
                $decoded['error']['message'] = 'Invalid Client ID.';
            }
        }

        return $decoded;

    }

    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );
        $_config[ 'source' ]  = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ]  = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters = array();
		$parameters['text'] = $_config[ 'segment' ];
                $parameters['appID'] = ""; // not used for now
                $parameters['systemID'] = $this->system_id;
                $parameters['clientID'] = $this->client_id;
                $qeParam = $this->use_qe ? ",qe" : "";
                $parameters['options'] = "termCorpusId=" . $this->terms_id . $qeParam;
		//$parameters['source'] = $_config[ 'source' ];
		//$parameters['target'] = $_config[ 'target' ];

	$this->call( "translate_relative_url", $parameters );

        return $this->result;

    }

    public function set( $_config ) {

        //if engine does not implement SET method, exit
        if ( null == $this->contribute_relative_url ) {
            return true;
        }

        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );

       $parameters = array();
		$parameters['text'] = $_config[ 'segment' ];
                $parameters['appID'] = ""; // not used for now
                $parameters['systemID'] = $this->system_id;
                $parameters['clientID'] = $this->client_id;
                $parameters['options'] = "termCorpusId=" . $this->terms_id;
                $parameters[ 'translation' ] = $_config[ 'translation' ];
                //$parameters[ 'source' ]      = $_config[ 'source' ];
                //$parameters[ 'target' ]      = $_config[ 'target' ];

        $this->call( "contribute_relative_url", $parameters );

        return $this->result;

    }

    public function update( $config ) {
        // TODO: Implement update() method.
        if ( null == $this->update_relative_url ) {
            return true;
        }
    }

    public function delete( $_config ) {
        // TODO: implement delete() method
        //if engine does not implement SET method, exit
        if ( null == $this->delete_relative_url ) {
            return true;
        }
    }
    
    public function getSystemList($_config) {

        $parameters = array();
                $parameters['appID'] = ""; // not used for now
                $parameters['clientID'] = $this->client_id;
                //$parameters['options'] = "termCorpusId=" . $this->terms_id;
		//$parameters['source'] = $_config[ 'source' ];
		//$parameters['target'] = $_config[ 'target' ];

	$this->call( 'system_list_relative_url', $parameters );
        
        if (isset($this->result['error']['code'])) {
            return $this->result;
        }
        $systemList = $this->result;
        
        return $systemList;
        
    }
    
    public function getTermList() {

        $parameters = array();
                $parameters['appID'] = ""; // not used for now
                $parameters['clientID'] = $this->client_id;
                $parameters['systemID'] = $this->system_id;
                //$parameters['options'] = "termCorpusId=" . $this->terms_id;
		//$parameters['source'] = $_config[ 'source' ];
		//$parameters['target'] = $_config[ 'target' ];

	$this->call( 'term_list_relative_url', $parameters );

        $termList = $this->result;
        
        return array('terms' => $termList);
    }
}