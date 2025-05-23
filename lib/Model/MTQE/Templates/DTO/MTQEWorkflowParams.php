<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:50
 *
 */

namespace MTQE\Templates\DTO;

use Constants_TranslationStatus;
use \DataAccess\AbstractDaoSilentStruct;
use JsonSerializable;

class MTQEWorkflowParams extends AbstractDaoSilentStruct implements JsonSerializable {

    public bool   $analysis_ignore_100             = false;
    public bool   $analysis_ignore_101             = false;
    public bool   $confirm_best_quality_mt         = true;
    public bool   $lock_best_quality_mt            = false;
    public string $best_quality_mt_analysis_status = Constants_TranslationStatus::STATUS_APPROVED;
    public string $qe_model_type                   = "default";

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return $this->getArrayCopy();
    }

    public function __toString(): string {
        return json_encode( $this->jsonSerialize() );
    }

}