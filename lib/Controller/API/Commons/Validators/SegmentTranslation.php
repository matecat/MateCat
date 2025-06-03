<?php

namespace API\Commons\Validators;

use Exceptions\NotFoundException;
use ReflectionException;
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
    protected function _validate(): void {
        $this->ensureTranslationExists();
    }

    /**
     *
     * @throws NotFoundException
     * @throws ReflectionException
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
