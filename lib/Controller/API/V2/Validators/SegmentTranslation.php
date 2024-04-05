<?php

namespace API\V2\Validators;

use Exceptions\NotFoundException;
use Translations_SegmentTranslationDao;
use Translations_SegmentTranslationStruct;

class SegmentTranslation extends Base {

    /**
     * @var Translations_SegmentTranslationStruct
     */
    public $translation;

    /**
     * @return void
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
