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

trait SourcePageGuesser {

    protected int     $id_job;
    protected ?string $received_password = null;

    /**
     * @return bool|null
     */
    protected function isRevision(): ?bool {
        $controller = $this;

        if ( isset( $controller->id_job ) and isset( $controller->received_password ) ) {
            $jid        = $controller->id_job;
            $password   = $controller->received_password;
            $isRevision = CatUtils::isRevisionFromIdJobAndPassword( $jid, $password );

            if ( !$isRevision ) {
                $isRevision = CatUtils::getIsRevisionFromReferer();
            }

            return $isRevision;
        }

        return CatUtils::getIsRevisionFromReferer();
    }

}