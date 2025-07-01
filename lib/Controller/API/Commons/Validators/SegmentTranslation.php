<?php

namespace Controller\API\Commons\Validators;

use Model\Exceptions\NotFoundException;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use ReflectionException;

class SegmentTranslation extends Base {

    /**
     * @var SegmentTranslationStruct|null
     */
    public ?SegmentTranslationStruct $translation = null;

    /**
     * @return void
     * @throws NotFoundException
     * @throws ReflectionException
     */
    protected function _validate(): void {
        $this->translation = SegmentTranslationDao::findBySegmentAndJob( $this->request->param( 'id_segment' ), $this->request->param( 'id_job' ) );
        if ( !$this->translation ) {
            throw new NotFoundException( 'translation not found' );
        }
    }

    public function getTranslation() {
        return $this->translation;
    }

}
