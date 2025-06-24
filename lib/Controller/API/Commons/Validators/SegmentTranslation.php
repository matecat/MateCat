<?php

namespace Controller\API\Commons\Validators;

use Exceptions\NotFoundException;
use ReflectionException;
use Translations_SegmentTranslationDao;
use Translations_SegmentTranslationStruct;

class SegmentTranslation extends Base {

    /**
     * @var Translations_SegmentTranslationStruct|null
     */
    public ?Translations_SegmentTranslationStruct $translation = null;

    /**
     * @return void
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function _validate(): void {
        $this->translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->request->param( 'id_segment' ), $this->request->param( 'id_job' ) );
        if ( !$this->translation ) {
            throw new NotFoundException( 'translation not found' );
        }
    }

    public function getTranslation() {
        return $this->translation;
    }

}
