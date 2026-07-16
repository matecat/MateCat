<?php

namespace Model\Projects;

use ArrayAccess;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;
use Utils\Constants\ProjectStatus;

/**
 * @implements ArrayAccess<string, mixed>
 */
class ProjectStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess
{

    use ArrayAccessTrait;

    public ?int $id = null;
    public string $password;
    public string $name;
    public string $id_customer;
    public string $create_date;
    public ?int $id_engine_tm = null;
    public ?int $id_engine_mt = null;
    public string $status_analysis;
    public ?float $fast_analysis_wc = 0;
    public ?float $standard_analysis_wc = 0;
    public ?float $tm_analysis_wc = 0;
    public string $remote_ip_address;
    public ?int $instance_id = 0;
    public ?int $pretranslate_100 = 0;
    public ?int $id_qa_model = null;
    public ?int $id_team = null;
    public ?int $id_assignee = null;
    public ?string $due_date = null;

    /**
     * @return bool
     */
    public function analysisComplete(): bool
    {
        return
            $this->status_analysis == ProjectStatus::STATUS_DONE ||
            $this->status_analysis == ProjectStatus::STATUS_NOT_TO_ANALYZE;
    }

}
