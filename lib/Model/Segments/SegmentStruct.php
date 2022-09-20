<?php

use DataAccess\ArrayAccessTrait;

class Segments_SegmentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public $id;
    public $id_file;
    public $id_file_part;
    protected $id_project; //keep private, this is not implemented in Database schema
    public $internal_id;
    public $xliff_mrk_id;
    public $xliff_ext_prec_tags;
    public $xliff_mrk_ext_prec_tags;
    public $segment;
    public $segment_hash;
    public $xliff_mrk_ext_succ_tags;
    public $xliff_ext_succ_tags;
    public $raw_word_count;
    public $show_in_cattool;

    /**
     * @return Segments_SegmentNoteStruct[]
     */
    public function getNotes() {
        return $this->cachable( __function__, $this->id, function ( $id ) {
            return Segments_SegmentNoteDao::getBySegmentId( $id );
        } );
    }

    public function findTranslation( $id_job ) {
        return Translations_SegmentTranslationDao::findBySegmentAndJob(
                $this->id,
                $id_job
        );
    }

}
