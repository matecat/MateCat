<?php

namespace API\V2\Validators;

use Database;
use Exceptions\NotFoundException;
use Segments_SegmentDao;
use Translations_SegmentTranslationDao;

class SegmentTranslation extends Base {

    /**
     * @var \Segments_SegmentStruct
     */
    public $segment;

    /**
     * @var \Translations_SegmentTranslationStruct
     */
    public $translation ;

    protected $password ;

    public function setPassword( $password ) {
        $this->password = $password  ;
        return $this;
    }

    /**
     * @return mixed|void
     * @throws NotFoundException
     */
    protected function _validate() {
        $this->ensureSegmentExists();
        $this->ensureTranslationExists();
    }

    /**
     *
     * @throws NotFoundException
     */

    private function ensureTranslationExists() {
        $this->translation = Translations_SegmentTranslationDao::
            findBySegmentAndJob( $this->request->id_segment, $this->request->id_job  );

        if ( !$this->translation ) {
            throw new NotFoundException('translation not found');
        }
    }

    private function ensureSegmentExists() {
        $dao = new Segments_SegmentDao( Database::obtain() );

        $this->segment = $dao->getByChunkIdAndSegmentId(
            $this->request->id_job,
            $this->password,
            $this->request->id_segment
        );

        if (!$this->segment) throw new NotFoundException('segment not found');
    }

    public function getTranslation() {
        return $this->translation;
    }

    public function getSegment() {
        return $this->segment ;
    }

}
