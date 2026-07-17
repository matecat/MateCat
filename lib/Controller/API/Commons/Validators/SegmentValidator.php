<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/12/17
 * Time: 17.18
 *
 */

namespace Controller\API\Commons\Validators;

use Controller\API\Commons\Exceptions\NotFoundException;
use Exception;
use Model\Segments\SegmentDao;
use PDOException;
use ReflectionException;

class SegmentValidator extends Base
{

    /**
     * @return void
     * @throws NotFoundException
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    protected function _validate(): void
    {
        // JobPasswordValidator is intentionally not applied here: the segment
        // scope is checked directly against the job below.

        // Ensure chunk is in project
        $dao = new SegmentDao($this->controller->getDatabase());

        $segment = $dao->getByChunkIdAndSegmentId(
            $this->controller->getParams()['id_job'],
            $this->controller->getParams()['password'],
            $this->controller->getParams()['id_segment']
        );

        if (!$segment) {
            throw new NotFoundException("Segment Not Found.", 404);
        }
    }


}