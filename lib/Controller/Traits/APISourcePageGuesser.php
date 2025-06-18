<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/06/25
 * Time: 13:58
 *
 */

namespace Traits;

use CatUtils;

trait APISourcePageGuesser {

    protected int     $id_job;
    protected ?string $request_password = null;

    /**
     * @return bool|null
     */
    protected function isRevision(): ?bool {
        $controller = $this;

        if ( isset( $controller->id_job ) and isset( $controller->request_password ) ) {
            $isRevision = CatUtils::isRevisionFromIdJobAndPassword( $controller->id_job, $controller->request_password );

            if ( !$isRevision ) {
                $isRevision = CatUtils::getIsRevisionFromReferer();
            }

            return $isRevision;
        }

        return CatUtils::getIsRevisionFromReferer();
    }

}