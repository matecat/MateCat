<?php

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
     * @return float
     */
    public function getSecsPerWord() {
        return round( $this->time_to_edit / 1000 / $this->raw_word_count, 1 );
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
                    $result = array_merge( $result, array_column( $notices, 'debug' ) );
            }

            $tag_mismatch = $QA->getMalformedXmlStructs();
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
}
