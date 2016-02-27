<?php

namespace API\V2\Validators;

use Exceptions\NotFoundError ;

class SegmentTranslation extends Base {

    /**
     * @var \Segments_SegmentStruct
     */
    public $segment;

    /**
     * @var \Translations_SegmentTranslationStruct
     */
    public $translation ;

    public function validate() {
        $this->ensureSegmentExists();
        $this->ensureTranslationExists();
    }

    /**
     *
     * @throws NotFoundError
     */

    private function ensureTranslationExists() {
        $this->translation = \Translations_SegmentTranslationDao::
            findBySegmentAndJob( $this->request->id_segment, $this->request->id_job  );

        if ( !$this->translation ) {
            throw new NotFoundError('translation not found');
        }
    }

    private function ensureSegmentExists() {
        $dao = new \Segments_SegmentDao( \Database::obtain() );

        $this->segment = $dao->getByChunkIdAndSegmentId(
            $this->request->id_job,
            $this->request->password,
            $this->request->id_segment
        );

        if (!$this->segment) throw new NotFoundError('segment not found');
    }

}
