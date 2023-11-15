<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 14/11/23
 * Time: 12:22
 *
 */

namespace API\App\Json\Analysis;

use JsonSerializable;

class AnalysisProject implements JsonSerializable {

    /**
     * @var string
     */
    protected $name = null;

    /**
     * @var AnalysisJob[]
     */
    protected $jobs = [];
    /**
     * @var AnalysisProjectSummary
     */
    protected $summary = null;

    public function __construct( $name, AnalysisProjectSummary $summary ) {
        $this->name    = $name;
        $this->summary = $summary;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'name'    => $this->name,
                'jobs'    => array_values( $this->jobs ),
                'summary' => $this->summary
        ];
    }

    /**
     * @param AnalysisJob $job
     *
     * @return $this
     */
    public function setJob( AnalysisJob $job ) {
        $this->jobs[ $job->getId() ] = $job;

        return $this;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function hasJob( $id ) {
        return array_key_exists( $id, $this->jobs );
    }

    /**
     * @return AnalysisJob
     */
    public function getJob( $id ) {
        return $this->jobs[ $id ];
    }

    /**
     * @return AnalysisProjectSummary
     */
    public function getSummary() {
        return $this->summary;
    }

}