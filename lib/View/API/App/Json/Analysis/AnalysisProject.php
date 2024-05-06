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
    /**
     * @var string
     */
    private $analyzeLink;
    /**
     * @var string
     */
    private $createDate;
    /**
     * @var mixed
     */
    protected $subject;

    public function __construct( $name, $create_date, $subject, AnalysisProjectSummary $summary ) {
        $this->name       = $name;
        $this->summary    = $summary;
        $this->subject    = $subject;
        $this->createDate = $create_date;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'name'        => $this->name,
                'create_date' => $this->createDate,
                'subject'     => $this->subject,
                'jobs'        => array_values( $this->jobs ),
                'summary'     => $this->summary,
                'analyze_url' => $this->analyzeLink
        ];
    }

    /**
     * @param string $analyze
     *
     * @return $this
     */
    public function setAnalyzeLink( $analyze ) {
        $this->analyzeLink = $analyze;

        return $this;
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
     * @return AnalysisJob[]
     */
    public function getJobs() {
        return $this->jobs;
    }

    /**
     * @return AnalysisProjectSummary
     */
    public function getSummary() {
        return $this->summary;
    }

    /**
     * @return string|null
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCreateDate() {
        return $this->createDate;
    }

}