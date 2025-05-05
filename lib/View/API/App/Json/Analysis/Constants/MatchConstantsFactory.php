<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 05/05/25
 * Time: 18:20
 *
 */

namespace API\App\Json\Analysis\Constants;

class MatchConstantsFactory {

    /**
     * @param bool|null $mt_we_workflow_enabled
     *
     * @return ConstantsInterface
     */
    public static function getInstance( ?bool $mt_we_workflow_enabled = false ): ConstantsInterface {
        return !$mt_we_workflow_enabled ? new StandardMatchConstants : new MTQEMatchConstants;
    }

}