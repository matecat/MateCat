<?php

use LQA\QA;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/10/15
 * Time: 11.33
 */
class EditLog_EditLogSegmentStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $internal_id;

    /**
     * @var string
     */
    public $translation;

    /**
     * @var int
     */
    public $time_to_edit;

    /**
     * @var string
     */
    public $suggestion;

    /**
     * @var string
     */
    public $suggestions_array;

    /**
     * @var string
     */
    public $suggestion_source;

    /**
     * @var int
     */
    public $suggestion_match;

    /**
     * @var int
     */
    public $suggestion_position;

    /**
     * @var string
     */
    public $segment_hash;

    /**
     * @var float
     */
    public $mt_qe;

    public $id_translator;

    /**
     * @var int
     */
    public $job_id;

    /**
     * @var string
     */
    public $job_source;

    /**
     * @var string
     */
    public $job_target;

    /**
     * @var int
     */
    public $raw_word_count;

    /**
     * @var string
     */
    public $proj_name;

    /**
     * @var float
     */
    public $secs_per_word;

    /**
     * @var string
     */
    public $warnings;

    /**
     * @var string
     */
    public $match_type;

    /**
     * @var bool
     */
    public $locked;

    /**
     * @var string
     */
    public $uid ;

    /**
     * @var string
     */
    public $email ;

    /**
     * @return float
     */
    public function getSecsPerWord() {
        $val = @round( ( $this->time_to_edit / 1000 ) / $this->raw_word_count, 1 );
        return ( $val != INF ? $val : 0 );
    }

    /**
     * Returns true if the number of seconds per word
     * @return bool
     */
    public function isValidForEditLog() {
        $secsPerWord = $this->getSecsPerWord();

        return ( $secsPerWord  > EditLog_EditLogModel::EDIT_TIME_FAST_CUT ) &&
                ( $secsPerWord  < EditLog_EditLogModel::EDIT_TIME_SLOW_CUT );
    }

    public function isValidForPeeTable(){

        //Do not consider ice matches
        if( $this->match_type == 'ICE' ) return false;

        $secsPerWord = $this->getSecsPerWord();

        return ( $secsPerWord  > EditLog_EditLogModel::EDIT_TIME_FAST_CUT );
    }

    /**
     * @return array
     */
    public function getWarning() {
        $result = array();

        $QA = new QA( $this->source, $this->translation );
        $QA->performConsistencyCheck();

        if ( $QA->thereAreNotices() ) {
            $notices = $QA->getNoticesJSON();
            $notices = json_decode( $notices, true );

            //the outer if it's here because $notices can be
            //an empty string and json_decode will fail into null value
            if ( !empty( $notices ) ) {
                $result = array_merge( $result, Utils::array_column( $notices, 'debug' ) );
            }

            $tag_mismatch       = $QA->getMalformedXmlStructs();
            $tag_order_mismatch = $QA->getTargetTagPositionError();
            if ( count( $tag_mismatch ) > 0 ) {
                $result[] = sprintf(
                        "Tag Mismatch ( %d )",
                        count( $tag_mismatch )
                );
            }
            if ( count( $tag_order_mismatch ) > 0 ) {
                $result[] = sprintf(
                        "Tag order mismatch ( %d )",
                        count( $tag_order_mismatch )
                );
            }
        }
        $this->warnings = $result;
    }

    /**
     * @return float|int
     */
    public function getPEE() {

        $post_editing_effort = round(
                ( 1 - MyMemory::TMS_MATCH(
                                self::cleanSegmentForPee( $this->suggestion ),
                                self::cleanSegmentForPee( $this->translation ),
                                $this->job_target
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
}
