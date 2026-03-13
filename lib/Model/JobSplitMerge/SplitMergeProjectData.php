<?php

namespace Model\JobSplitMerge;

use ArrayObject;

/**
 * Canonical typed DTO for the project-creation pipeline.
 *
 * It centralizes all project state exchanged across controllers and
 * ProjectCreation services, including validated input, runtime pipeline data,
 * per-file transient processing data, and final output/result payloads.
 *
 * By extending {@see AbstractDaoObjectStruct}, it enforces a closed schema:
 * only declared public properties are allowed, and unknown property access
 * fails fast, preventing silent key drift and typo-based bugs.
 *
 * Implements {@see JsonSerializable} to provide a stable array representation
 * for queue transport, persistence, and API responses.
 */
class SplitMergeProjectData
{
    // ── Identity (read-only after construction) ─────────────────────

    public readonly int $idProject;
    public readonly ?string $idCustomer;

    // ── Mutable input ───────────────────────────────────────────────

    public ?int $uid = null;
    public ?int $jobToSplit = null;
    public ?string $jobToSplitPass = null;
    public ?int $jobToMerge = null;

    // ── Mutable output ──────────────────────────────────────────────

    /**
     * Result of getSplitData(): chunk boundaries and word counts.
     * Null until getSplitData() populates it.
     *
     * @var ArrayObject<string, mixed>|null
     */
    public ?ArrayObject $splitResult = null;

    /**
     * Job IDs created/retained after split. Appended during splitJob().
     * @var ArrayObject<int, int>
     */
    public ArrayObject $jobList;

    /**
     * Passwords for each job chunk. Appended during splitJob().
     * @var ArrayObject<int, string>
     */
    public ArrayObject $jobPass;

    /**
     * Segment ranges keyed by "jobId-password".
     * @var ArrayObject<string, mixed>
     */
    public ArrayObject $jobSegments;

    public function __construct(int $idProject, ?string $idCustomer = null)
    {
        $this->idProject  = $idProject;
        $this->idCustomer = $idCustomer;

        $this->jobList     = new ArrayObject();
        $this->jobPass     = new ArrayObject();
        $this->jobSegments = new ArrayObject();
    }

    /**
     * Convert to an ArrayObject for backward-compatible FeatureSet hooks.
     *
     * Plugins receiving `postJobSplitted` / `postJobMerged` expect an
     * ArrayObject with keys like 'id_project', 'array_jobs', etc.
     * This method reconstructs that shape from typed properties.
     *
     * @return ArrayObject<string, mixed>
     */
    public function toArrayObject(): ArrayObject
    {
        return new ArrayObject([
            'id_project'        => $this->idProject,
            'id_customer'       => $this->idCustomer,
            'uid'               => $this->uid,
            'job_to_split'      => $this->jobToSplit,
            'job_to_split_pass' => $this->jobToSplitPass,
            'job_to_merge'      => $this->jobToMerge,
            'split_result'      => $this->splitResult,
            'array_jobs'        => new ArrayObject([
                'job_list'     => $this->jobList,
                'job_pass'     => $this->jobPass,
                'job_segments' => $this->jobSegments,
            ]),
        ]);
    }
}
