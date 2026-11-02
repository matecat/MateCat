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
        // A split segment reaches us as "<id_segment>-<part>". Its parts share one translation row and
        // issues cannot be attached to a single part, so the part number is dropped.
        $idSegment = $this->controller->parseIdSegment((string)$this->request->param('id_segment'))['id_segment'];

        if (!ctype_digit($idSegment)) {
            throw new NotFoundException('Translation not found');
        }

        $this->translation = (new SegmentTranslationDao($this->controller->getDatabase()))->findBySegmentAndJob((int)$idSegment, $this->request->param('id_job'));
        if (!$this->translation) {
            throw new NotFoundException('Translation not found');
        }
    }

    public function getTranslation(): ?SegmentTranslationStruct
    {
        return $this->translation;
    }

}
