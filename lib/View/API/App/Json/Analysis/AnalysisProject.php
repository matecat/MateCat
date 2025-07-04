<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 14/11/23
 * Time: 12:22
 *
 */

namespace View\API\App\Json\Analysis;

use JsonSerializable;
use Model\Analysis\Constants\ConstantsInterface;

class AnalysisProject implements JsonSerializable {

    /**
     * @var string
     */
    protected string $name;

    /**
     * @var string
     */
    protected string $status;

    /**
     * @var AnalysisJob[]
     */
    protected array $jobs = [];
    /**
     * @var AnalysisProjectSummary
     */
    protected AnalysisProjectSummary $summary;
    /**
     * @var string
     */
    private string $analyzeLink;
    /**
     * @var string
     */
    private string $createDate;
    /**
     * @var mixed
     */
    protected string $subject;
    /**
     * @var string
     */
    protected string $workflow_type;

    public function __construct( string $name, string $status, string $create_date, string $subject, AnalysisProjectSummary $summary, ConstantsInterface $matchConstantsClass ) {
        $this->name          = $name;
        $this->status        = $status;
        $this->summary       = $summary;
        $this->subject       = $subject;
        $this->createDate    = $create_date;
        $this->workflow_type = $matchConstantsClass::getWorkflowType();
    }

    /**
     * @return string
     */
    public function getStatus(): string {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function setStatus( $status ) {
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getAnalyzeLink() {
        return $this->analyzeLink;
    }

    /**
     * @param string $analyzeLink
     */
    public function setAnalyzeLink( string $analyzeLink ) {
        $this->analyzeLink = $analyzeLink;
    }

    /**
     * @param AnalysisJob $job
     *
     * @return $this
     */
    public function setJob( AnalysisJob $job ): AnalysisProject {
        $this->jobs[ $job->getId() ] = $job;

        return $this;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function hasJob( string $id ): bool {
        return array_key_exists( $id, $this->jobs );
    }

    /**
     * @param $id
     *
     * @return AnalysisJob
     */
    public function getJob( $id ): AnalysisJob {
        return $this->jobs[ $id ];
    }

    /**
     * @return AnalysisJob[]
     */
    public function getJobs(): array {
        return $this->jobs;
    }

    /**
     * @return AnalysisProjectSummary
     */
    public function getSummary(): AnalysisProjectSummary {
        return $this->summary;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getCreateDate(): string {
        return $this->createDate;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'name'          => $this->name,
                'status'        => $this->status,
                'create_date'   => $this->createDate,
                'subject'       => $this->subject,
                'workflow_type' => $this->workflow_type,
                'jobs'          => array_values( $this->jobs ),
                'summary'       => $this->summary,
                'analyze_url'   => $this->analyzeLink
        ];
    }
}