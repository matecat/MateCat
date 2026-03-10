<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/06/25
 * Time: 13:58
 *
 */

namespace Controller\Traits;

use LogicException;
use Utils\Tools\CatUtils;

trait APISourcePageGuesserTrait
{

    protected int $id_job;
    protected string $request_password;

    /**
     * @return bool|null
     */
    protected function isRevision(): ?bool
    {
        $controller = $this;

        if (!isset($controller->id_job) || !isset($controller->request_password)) {
            throw new LogicException('id_job and request_password must both be set in the controller');
        }

        $isRevision = CatUtils::isRevisionFromIdJobAndPassword($controller->id_job, $controller->request_password);

        if (!$isRevision) {
            $isRevision = CatUtils::getIsRevisionFromReferer();
        }

        return $isRevision;
    }

}