<?php

namespace Controller\API\Commons\Validators;

use Exception;
use Model\Exceptions\NotFoundException;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use PDOException;
use ReflectionException;

class SegmentTranslation extends Base
{

    /**
     * @var SegmentTranslationStruct|null
     */
    public ?SegmentTranslationStruct $translation = null;

    /**
     * @return void
     * @throws Exception
     * @throws NotFoundException
     * @throws PDOException
     * @throws ReflectionException
     */
    protected function _validate(): void
    {
        $this->translation = (new SegmentTranslationDao($this->controller->getDatabase()))->findBySegmentAndJob($this->request->param('id_segment'), $this->request->param('id_job'));
        if (!$this->translation) {
            throw new NotFoundException('translation not found');
        }
    }

    public function getTranslation(): ?SegmentTranslationStruct
    {
        return $this->translation;
    }

}
