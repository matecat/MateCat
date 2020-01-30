<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:57
 */

namespace API\V2\Json;

use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use Features\ReviewExtended\ReviewUtils;
use LQA\ChunkReviewDao;
use Routes;

class Propagation {

    /**
     * @var array
     */
    private $data;

    /**
     * Propagation constructor.
     *
     * @param array $data
     */
    public function __construct( $data ) {
        $this->data = $data;
    }

    /**
     * @param bool $keyAssoc
     *
     * @return array
     */
    public function render(  ) {

        unset($this->data['segments_for_propagation']['propagated_ids']);
        unset($this->data['propagated_ids']);

        return $this->data;
    }
}