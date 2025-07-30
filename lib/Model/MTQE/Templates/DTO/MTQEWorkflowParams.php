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
use DataAccess\AbstractDaoSilentStruct;
use JsonSerializable;

class MTQEWorkflowParams extends AbstractDaoSilentStruct implements JsonSerializable {

    public bool   $analysis_ignore_100             = false;
    public bool   $analysis_ignore_101             = false;
    public bool   $confirm_best_quality_mt         = true;
    public bool   $lock_best_quality_mt            = false;
    public string $best_quality_mt_analysis_status = Constants_TranslationStatus::STATUS_APPROVED;
    public int    $qe_model_version                = 3; //Purfect version 3 is the new default, but we can change it in the future. Version 2 is the old one, which is still supported for simple MtQE workflows (ICE_MT).

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return (array)$this;
    }

    public function __toString(): string {
        return json_encode( $this->jsonSerialize() );
    }

}