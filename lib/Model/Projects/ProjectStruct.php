<?php

namespace Model\Projects;

use ArrayAccess;
use DomainException;
use Exception;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
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

    /**
     * @param int $ttl
     *
     * @return JobStruct[]
     *
     * @throws DomainException
     */
    public function getJobs(int $ttl = 0): array
    {
        $id = $this->id ?? throw new DomainException("Project ID must not be null");

        return $this->cachable(__METHOD__, function () use ($id, $ttl) {
            return (new JobDao())->getNotDeletedByProjectId($id, $ttl);
        });
    }

    /**
     * @return int
     *
     * @throws DomainException
     */


    /**
     *
     * @return array<string, string>
     *
     * @throws DomainException
     */
    public function getAllMetadataAsKeyValue(): array
    {
        $collection = $this->getAllMetadata();
        $data = [];
        foreach ($collection as $record) {
            $data[$record->key] = $record->value;
        }

        return $data;
    }


    /**
     * @param string $key
     *
     * @return ?string
     *
     * @throws DomainException
     */
    public function getMetadataValue(string $key): mixed
    {
        $id = $this->id ?? throw new DomainException("Project ID must not be null");

        return $this->cachable(__METHOD__ . ":" . $key, function () use ($id, $key) {
            $mDao = new MetadataDao();

            return $mDao->setCacheTTL(60 * 60)->get($id, $key)?->value;
        });
    }

    /**
     * @return MetadataStruct[]
     *
     * @throws DomainException
     */
    public function getAllMetadata(): array
    {
        $id = $this->id ?? throw new DomainException("Project ID must not be null");

        return $this->cachable(__METHOD__, function () use ($id) {
            $mDao = new MetadataDao();

            return $mDao->setCacheTTL(60 * 60)->allByProjectId($id);
        });
    }



    /**
     * @param $feature_code
     *
     * @return bool
     *
     */
    public function isFeatureEnabled(string $feature_code): bool
    {
        return in_array($feature_code, $this->getFeaturesSet()->getCodes());
    }

    /**
     * @return FeatureSet
     */
    public function getFeaturesSet(): FeatureSet
    {
        return $this->cachable(__METHOD__, function () {
            $featureSet = new FeatureSet();
            $featureSet->loadForProject($this);

            return $featureSet;
        });
    }

    /**
     * @param int $ttl
     *
     * @return JobStruct[]
     *
     * @throws DomainException
     */
    public function getChunks(int $ttl = 0): array
    {
        $id = $this->id ?? throw new DomainException("Project ID must not be null");

        return $this->cachable(__METHOD__, function () use ($id, $ttl) {
            $dao = new JobDao();

            return $dao->setCacheTTL($ttl)->getNotDeletedByProjectId($id);
        });
    }


    public function hasFeature(string $feature_code): bool
    {
        return in_array($feature_code, $this->getFeaturesSet()->getCodes());
    }


}
