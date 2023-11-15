<?php

namespace LQA;

use Exceptions\ValidationError;

class EntryStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $uid;
    public $id_segment;
    public $id_job;
    public $id_category;
    public $severity;
    public $translation_version;
    public $start_node;
    public $start_offset;
    public $end_node;
    public $end_offset;
    public $is_full_segment;
    public $penalty_points;
    public $comment;
    public $create_date;
    public $target_text;
    public $rebutted_at;
    public $source_page;
    public $deleted_at;

    protected $_comments;
    protected $_diff;

    /**
     * @var EntryValidator
     */
    private $validator;

    public function __construct( array $array_params = [] ) {
        parent::__construct( $array_params );
        $this->validator = new EntryValidator( $this );
    }

    public function ensureValid() {
        $this->validator->ensureValid();
    }

    public function addComments( $comments ) {
        $this->_comments = $comments;
    }

    /**
     * @return mixed
     */
    public function getComments() {
        return $this->_comments;
    }

    /**
     * @return mixed
     */
    public function getDiff() {
        return $this->_diff;
    }

    /**
     * @param mixed $diff
     */
    public function setDiff( $diff ) {
        $this->_diff = $diff;

        return $this;
    }

    /**
     * @throws ValidationError
     */
    public function setDefaults() {

        $this->validator->ensureValid();

        // set the translation reading the version number on the
        // segment translation
        $translation               = \Translations_SegmentTranslationDao::findBySegmentAndJob( $this->id_segment, $this->id_job );
        $this->translation_version = $translation->version_number;

        $this->penalty_points = $this->getPenaltyPoints();
        $this->id_category    = $this->validator->category->id;
    }

    private function getPenaltyPoints() {
        $severities = $this->validator->category->getJsonSeverities();

        foreach ( $severities as $severity ) {
            if ( $severity[ 'label' ] == $this->severity ) {
                return $severity[ 'penalty' ];
            }
        }
    }


}
