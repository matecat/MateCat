<?php
/**
 * Created by PhpStorm.
 * This class represents the breakdown of payable rates in the MTQE system.
 * It extends the DataAccess_AbstractDaoObjectStruct and implements JsonSerializable for JSON serialization.
 *
 *  JSON Object @see https://jsongrid.com?data=9bd1dfea-8a98-4acc-b943-0897d8f51cdb
 *
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:50
 *
 */

namespace MTQE\PayableRate\DTO;

use DataAccess_AbstractDaoObjectStruct;
use JsonSerializable;

class MTQEPayableRateBreakdowns extends DataAccess_AbstractDaoObjectStruct implements JsonSerializable {

    /** @var int $ice The rate for ICE matches. Default is 0. */
    public int $ice = 0;

    /** @var int $tm_100 The rate for 100% TM matches. Default is 30. */
    public int $tm_100 = 30;

    /** @var int $tm_100_public The rate for 100% public TM matches. Default is 30. */
    public int $tm_100_public = 30;

    /** @var int $repetitions The rate for repetitions. Default is 30. */
    public int $repetitions = 30;

    /** @var int $ice_mt The rate for ICE matches with MT. Default is 0. */
    public int $ice_mt = 0;

    /** @var int $top_quality_mt The rate for top-quality MT matches. Default is 10. */
    public int $top_quality_mt = 10;

    /** @var int $higher_quality_mt The rate for higher-quality MT matches. Default is 30. */
    public int $higher_quality_mt = 30;

    /** @var int $standard_quality_mt The rate for standard-quality MT matches. Default is 62. */
    public int $standard_quality_mt = 62;

    /**
     * Serializes the object to a JSON-compatible array.
     *
     * @return array The object properties as an associative array.
     */
    public function jsonSerialize(): array {
        return (array)$this;
    }

    /**
     * Converts the object to a JSON string representation.
     *
     * @return string The JSON-encoded string of the object.
     */
    public function __toString(): string {
        return json_encode( $this->jsonSerialize() );
    }

}