<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:50
 *
 */

namespace MTQE\PayableRate\DTO;

use DataAccess_AbstractDaoObjectStruct;
use JsonSerializable;

class MTQEPayableRateBreakdowns extends DataAccess_AbstractDaoObjectStruct implements JsonSerializable {

    public int $ice                 = 0;
    public int $tm_100              = 30;
    public int $tm_100_public       = 30;
    public int $repetitions         = 30;
    public int $ice_mt              = 0;
    public int $top_quality_mt      = 10;
    public int $higher_quality_mt   = 30;
    public int $standard_quality_mt = 62;

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