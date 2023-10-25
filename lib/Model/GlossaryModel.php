<?php

/**
 * Class GlossaryModel
 *
 * This class takes a job and a uid as constructor arguments and handles glossary towards
 * the TM engine for that user.
 *
 */

use Matecat\SubFiltering\MateCatFilter;

class GlossaryModel {

    const MYMEMORY_ID = 1;
    /**
     * @var Engines_MyMemory
     */
    private $_TMS;

    private $job;

    private $role;

    private $uid;

    private $featureSet;

    public function __construct( Jobs_JobStruct $job ) {
        $this->job = $job;

        $this->featureSet = new FeatureSet;
        $this->featureSet->loadForProject( $this->job->getProject() );

        $this->_TMS = Engine::getInstance( self::MYMEMORY_ID );

        $this->role = TmKeyManagement_Filter::ROLE_TRANSLATOR;
    }

    public function setUid( $uid ) {
        $this->uid = $uid;
    }

    public function setRoleRevision() {
        $this->role = TmKeyManagement_Filter::ROLE_REVISOR;
    }

    private function __matchWithWordBoundary( $what, $where ) {
        $quoted             = preg_quote( $what );
        $preg_match_pattern = $this->featureSet->filter( 'glossaryMatchPattern', "/\\b$quoted\\b/u" );

        return preg_match_all( $preg_match_pattern, $where );
    }

    /**
     * Only returns matches that are not present in translation.
     *
     * @param $segment
     * @param $translation
     */
    public function getUnmatched( $segment, $translation ) {
        $allMatches = $this->get( $segment, $translation );

        $unmatched = [];

        foreach ( $allMatches as $match ) {
            if (
                    $this->__matchWithWordBoundary( $match[ 'raw_segment' ], $segment ) > 0 &&
                    $this->__matchWithWordBoundary( $match[ 'raw_translation' ], $translation ) === 0
            ) {
                $unmatched[] = $match;
            }
        }

        return $unmatched;
    }

    public function get( $segment, $translation ) {

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new \FeatureSet();
        $Filter     = MateCatFilter::getInstance( $featureSet, $this->job->source, $this->job->target );

        $config[ 'segment' ]     = $Filter->fromLayer2ToLayer0( preg_replace( '#<(?:/?[^>]+/?)>#', "", $segment ) );
        $config[ 'translation' ] = $translation;

        $config[ 'source' ] = $this->job->source;
        $config[ 'target' ] = $this->job->target;

        $config[ 'isGlossary' ] = true;
        $config[ 'get_mt' ]     = null;
        $config[ 'email' ]      = INIT::$MYMEMORY_API_KEY;
        $config[ 'num_result' ] = 100; //do not want limit the results from glossary: set as a big number

        $config = $this->addTmKeys( $config );

        $engine = $this->_TMS->get( $config );

        if($engine instanceof Engines_EngineInterface){
            return $engine->get_matches_as_array();
        }

        return [];
    }


    private function addTmKeys( $config ) {
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys(
                $this->job->tm_keys, 'r', 'glos', $this->uid, $this->role
        );

        if ( count( $tm_keys ) ) {
            $config[ 'id_user' ] = [];
            /**
             * @var $tm_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $tm_keys as $tm_key ) {
                $config[ 'id_user' ][] = $tm_key->key;
            }
        }

        return $config;
    }

}