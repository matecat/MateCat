<?php

namespace API\V2\Validators;

use Database;
use Exceptions\NotFoundException;
use Segments_SegmentDao;
use Translations_SegmentTranslationDao;

class SegmentTranslation extends Base {

    /**
     * @var \Translations_SegmentTranslationStruct
     */
    public $translation;

    /**
     * @return mixed|void
     * @throws NotFoundException
     */
    protected function _validate() {
        $this->ensureTranslationExists();
    }

    /**
     *
     * @throws NotFoundException
     */

    private function ensureTranslationExists() {
        $this->translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->request->id_segment, $this->request->id_job );
        if ( !$this->translation ) {
            throw new NotFoundException( 'translation not found' );
        }
    }

    public function getTranslation() {
        return $this->translation;
    }

}
