<?php

namespace SubFiltering\Filters;

use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use SubFiltering\Commons\AbstractHandler;

class DataRefRestore extends AbstractHandler {

    /**
     * @var array
     */
    private $dataRefMap;

    /**
     * DataRefReplace constructor.
     *
     * @param array $dataRefMap
     */
    public function __construct( array $dataRefMap = []) {
        parent::__construct();
        $this->dataRefMap = $dataRefMap;
    }

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {

        if(empty($this->dataRefMap)){
            return $segment;
        }

        $dataRefReplacer = new DataRefReplacer($this->dataRefMap);
        $segment = str_replace('&quot;', '"', $segment);

        return $dataRefReplacer->restore($segment);
    }
}