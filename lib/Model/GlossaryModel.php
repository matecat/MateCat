<?php

/**
 * Class GlossaryModel
 *
 * This class takes a job and a uid as constructor arguments and handles glossary towards
 * the TM engine for that user.
 *
 */

class GlossaryModel {

    const MYMEMORY_ID = 1;
    /**
     * @var Engines_MyMemory
     */
    private $_TMS ;
    
    private $job ;

    private $role ;

    private $uid ;

    public function __construct( Jobs_JobStruct $job ) {
        $this->job = $job ;

        $this->_TMS = Engine::getInstance( self::MYMEMORY_ID );

        $this->role = TmKeyManagement_Filter::ROLE_TRANSLATOR ;
    }

    public function setUid( $uid ) {
        $this->uid = $uid ; 
    }

    public function setRoleRevision() {
        $this->role = TmKeyManagement_Filter::ROLE_REVISOR ;
    }

    /**
     * Only returns matches that are not present in translation.
     *
     * @param $segment
     * @param $translation
     */
    public function getUnmatched( $segment, $translation ) {
        $allMatches = $this->get($segment, $translation) ;

        $unmatched = array() ;

        foreach($allMatches as $match) {
            $quoted = preg_quote( $match['raw_translation'] );
            $found = preg_match_all("/\\b$quoted\\b/", $translation ) ;

            // TODO: consider to change this in favour of a comparison with
            // $match['usage_count']. 
            if ( $found == 0 ) {
                $unmatched[] = $match ;
            }
        }
        return $unmatched ;
    }

    public function get($segment, $translation) {
        $config[ 'segment' ] = CatUtils::view2rawxliff( preg_replace( '#<(?:/?[^>]+/?)>#', "", $segment ) );
        $config[ 'translation' ] = $translation ;

        $config[ 'source' ]      = $this->job->source ;
        $config[ 'target' ]      = $this->job->target ;

        $config[ 'isGlossary' ]  = true;
        $config[ 'get_mt' ]      = null;
        $config[ 'email' ]       = INIT::$MYMEMORY_API_KEY;
        $config[ 'num_result' ]  = 100; //do not want limit the results from glossary: set as a big number

        $config = $this->addTmKeys( $config ) ;

        return $this->_TMS->get( $config )->get_matches_as_array();
    }


    private function addTmKeys( $config ) {
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys(
                $this->job->tm_keys, 'r', 'glos', $this->uid, $this->role
        );

        if ( count( $tm_keys ) ) {
            $config[ 'id_user' ] = array();
            /**
             * @var $tm_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ][ ] = $tm_key->key;
            }
        }
        return $config ;
    }

}