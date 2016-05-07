<?php

class Segments_SegmentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    public $id;
    public $id_file;
    public $id_file_part;
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

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet( $offset ) {
        return $this->$offset;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        $this->$offset = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        $this->$offset = null;
    }


}
