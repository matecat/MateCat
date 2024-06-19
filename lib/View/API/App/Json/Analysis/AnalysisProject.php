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
     * @var
     */
    protected $status;

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

    public function __construct( $name, $status, $create_date, $subject, AnalysisProjectSummary $summary ) {
        $this->name       = $name;
        $this->status     = $status;
        $this->summary    = $summary;
        $this->subject    = $subject;
        $this->createDate = $create_date;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getAnalyzeLink()
    {
        return $this->analyzeLink;
    }

    /**
     * @param string $analyzeLink
     */
    public function setAnalyzeLink($analyzeLink)
    {
        $this->analyzeLink = $analyzeLink;
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

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
            'name'        => $this->name,
            'status'      => $this->status,
            'create_date' => $this->createDate,
            'subject'     => $this->subject,
            'jobs'        => array_values( $this->jobs ),
            'summary'     => $this->summary,
            'analyze_url' => $this->analyzeLink
        ];
    }
}