<?php

namespace Model\Projects;

use ArrayAccess;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\ChunkDao;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use Model\RemoteFiles\RemoteFileServiceNameStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use ReflectionException;
use Utils\Constants\ProjectStatus;

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
     */
    public function getJobs(int $ttl = 0): array
    {
        return $this->cachable(__METHOD__, function () use ($ttl) {
            return JobDao::getByProjectId($this->id, $ttl);
        });
    }

    /**
     * @return int
     */
    public function getJobsCount(): int
    {
        if (empty($this->getJobs())) {
            return 0;
        }

        return count($this->getJobs());
    }

    /**
     * Proxy to set metadata for the current project
     *
     * @param string $key
     * @param string $value
     *
     * @return bool
     * @throws ReflectionException
     */
    public function setMetadata(string $key, string $value): bool
    {
        $dao = new MetadataDao(Database::obtain());

        return $dao->set($this->id, $key, $value);
    }

    /**
     *
     * @return array
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
     */
    public function getMetadataValue(string $key): ?string
    {
        return $this->cachable(__METHOD__ . ":" . $key, function () use ($key) {
            $mDao = new MetadataDao();

            return $mDao->setCacheTTL(60 * 60)->get($this->id, $key)?->value;
        });
    }

    /**
     * @return MetadataStruct[]
     */
    public function getAllMetadata(): array
    {
        return $this->cachable(__METHOD__, function () {
            $mDao = new MetadataDao();

            return $mDao->setCacheTTL(60 * 60)->allByProjectId($this->id);
        });
    }

    /**
     * @return ?RemoteFileServiceNameStruct
     */
    public function getRemoteFileServiceName(): ?RemoteFileServiceNameStruct
    {
        return $this->cachable(__METHOD__, function () {
            $dao = new ProjectDao();

            /** @var RemoteFileServiceNameStruct[] */
            return $dao->setCacheTTL(60 * 60 * 24 * 7)->getRemoteFileServiceName([$this->id])[0] ?? null;
        });
    }

    /**
     * @return null|TeamStruct
     * @throws ReflectionException
     */
    public function getTeam(): ?TeamStruct
    {
        if (is_null($this->id_team)) {
            return null;
        }
        $dao = new TeamDao();

        return $dao->findById($this->id_team);
    }

    /**
     * @param $feature_code
     *
     * @return bool
     *
     */
    public function isFeatureEnabled($feature_code): bool
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
     */
    public function getChunks(int $ttl = 0): array
    {
        return $this->cachable(__METHOD__, function () use ($ttl) {
            $dao = new ChunkDao(Database::obtain());

            return $dao->setCacheTTL($ttl)->getByProjectID($this->id);
        });
    }

    /**
     * @return string
     */
    public function getWordCountType(): string
    {
        //this method is already cached internally by the MetadataDao.
        // we can avoid using the cachable method here
        $type = $this->getMetadataValue(MetadataDao::WORD_COUNT_TYPE_KEY);

        if ($type == null) {
            return MetadataDao::WORD_COUNT_EQUIVALENT;
        } else {
            return $type;
        }
    }

    public function hasFeature($feature_code): bool
    {
        return in_array($feature_code, $this->getFeaturesSet()->getCodes());
    }

    /**
     * @param int $ttl
     *
     * @return ?ModelStruct
     */
    public function getLqaModel(int $ttl = 86400): ?ModelStruct
    {
        return $this->cachable(__METHOD__, function () use ($ttl) {
            if ($this->id_qa_model === null) {
                return null;
            }

            return ModelDao::findById($this->id_qa_model, $ttl);
        });
    }


}
