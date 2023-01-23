<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 24/07/2018
 * Time: 12:46
 */

use API\V2\Json\QALocalWarning;
use LQA\QA;


class QualityReport_QualityReportSegmentStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {


    public $sid;

    public $target;

    public $segment;

    public $segment_hash;

    public $raw_word_count;

    public $translation;

    public $version;

    public $ice_locked;

    public $status;

    public $time_to_edit;

    public $filename;

    public $id_file;

    public $warning;

    public $suggestion_match;

    public $suggestion_source;

    public $suggestion;

    public $edit_distance;

    public $locked;

    public $match_type;

    public $warnings;

    public $pee;

    public $ice_modified;

    public $secs_per_word;

    public $parsed_time_to_edit;

    public $comments = [];

    public $issues = [];

    public $last_translation;

    public $last_revisions;

    public $pee_translation_revise;

    public $pee_translation_suggestion;

    public $version_number;

    public $source_page ;

    public $is_pre_translated = false;

    public $dataRefMap = [];

    /**
     * @return float
     */
    public function getSecsPerWord() {
        $val = @round( ( $this->time_to_edit / 1000 ) / $this->raw_word_count, 1 );
        return ( $val != INF ? $val : 0 );
    }

    public function isICEModified(){
        return ( $this->getPEE() != 0 && $this->isICE() );
    }

    public function isICE(){
        return ( $this->match_type == 'ICE' && $this->locked );
    }

    /**
     * @return float|int
     */
    public function getPEE() {
        if(empty($this->translation) || empty($this->suggestion) ){
            return 0;
        }
        return self::calculatePEE($this->suggestion, $this->translation, $this->target);
    }

    public function getPEEBwtTranslationSuggestion() {
        if(empty($this->last_translation)){
            return 0;
        }

        return self::calculatePEE($this->suggestion, $this->last_translation, $this->target);
    }

    public function getPEEBwtTranslationRevise() {
        if(empty($this->last_translation) OR empty($this->last_revisions)){
            return 0;
        }

        // TODO refactor
        $last_revision_record = end( $this->last_revisions);
        $last_revision = $last_revision_record['translation'];

        return self::calculatePEE($this->last_translation, $last_revision, $this->target);
    }

    static function calculatePEE($str_1, $str_2, $target){
        $post_editing_effort = round(
                ( 1 - \MyMemory::TMS_MATCH(
                                self::cleanSegmentForPee( $str_1 ),
                                self::cleanSegmentForPee( $str_2 ),
                                $target
                        )
                ) * 100
        );

        if ( $post_editing_effort < 0 ) {
            $post_editing_effort = 0;
        } elseif ( $post_editing_effort > 100 ) {
            $post_editing_effort = 100;
        }

        return $post_editing_effort;
        
    }



    private static function cleanSegmentForPee( $segment ){
        $segment = htmlspecialchars_decode( $segment, ENT_QUOTES);

        return $segment;
    }

    public function getLocalWarning(FeatureSet $featureSet, Chunks_ChunkStruct $chunk){

        $QA = new QA( $this->segment, $this->translation );
        $QA->setSourceSegLang($chunk->source);
        $QA->setTargetSegLang($chunk->target);
        $QA->setChunk($chunk);
        $QA->setFeatureSet($featureSet);
        $QA->performConsistencyCheck();

        $local_warning = new QALocalWarning($QA, $this->sid);

        return $local_warning->render();
    }
}
