<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:08
 *
 */

namespace View\API\App\Json\Analysis;

use Exception;
use JsonSerializable;
use Model\Analysis\Constants\ConstantsInterface;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use Utils\Engines\EnginesFactory;
use Utils\TmKeyManagement\Filter;
use Utils\Url\JobUrlBuilder;

class AnalysisChunk implements JsonSerializable
{

    /**
     * @var AnalysisJobSummary
     */
    protected AnalysisJobSummary $summary;

    /**
     * @var AnalysisFile[]
     */
    protected array $files = [];
    /**
     * @var JobStruct
     */
    protected JobStruct $chunkStruct;
    /**
     * @var string
     */
    protected string $projectName;
    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    /**
     * @var int
     */
    protected int $total_raw = 0;
    /**
     * @var float
     */
    protected float $total_equivalent = 0;
    /**
     * @var float
     */
    protected float $total_industry = 0;

    public function __construct(JobStruct $chunkStruct, $projectName, UserStruct $user, ConstantsInterface $matchConstantsClass)
    {
        $this->chunkStruct = $chunkStruct;
        $this->projectName = $projectName;
        $this->user = $user;
        $this->summary = new AnalysisJobSummary($matchConstantsClass);
    }

    /**
     * @param AnalysisFile $file
     *
     * @return $this
     */
    public function setFile(AnalysisFile $file): AnalysisChunk
    {
        $this->files[$file->getId()] = $file;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function jsonSerialize(): array
    {
        return [
            'password' => $this->chunkStruct->password,
            'status' => $this->chunkStruct->status,
            'engines' => $this->getEngines(),
            'memory_keys' => $this->getMemoryKeys(),
            'urls' => JobUrlBuilder::createFromJobStructAndProjectName($this->chunkStruct, $this->projectName)->getUrls(),
            'files' => array_values($this->files),
            'summary' => $this->summary,
            'total_raw' => $this->total_raw,
            'total_equivalent' => round($this->total_equivalent),
            'total_industry' => max(round($this->total_industry), round($this->total_equivalent)),
        ];
    }

    /**
     * @return JobStruct
     */
    public function getChunkStruct(): JobStruct
    {
        return $this->chunkStruct;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->chunkStruct->password;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function hasFile($id): bool
    {
        return array_key_exists($id, $this->files);
    }

    /**
     * @return AnalysisFile[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @throws Exception
     */
    private function getEngines(): array
    {
        // this can happen even when fast analysis is not completed
        if (!is_numeric($this->chunkStruct->id_tms) || !is_numeric($this->chunkStruct->id_mt_engine)) {
            return [];
        }

        try {
            $tmEngine = EnginesFactory::getInstance($this->chunkStruct->id_tms);
        } catch (Exception) {
            $tmEngine = null;
        }

        try {
            $mtEngine = EnginesFactory::getInstance($this->chunkStruct->id_mt_engine);
        } catch (Exception) {
            $mtEngine = null;
        }

        return [
            'tm' => $tmEngine?->getEngineRecord()->arrayRepresentation(),
            'mt' => $mtEngine?->getEngineRecord()->arrayRepresentation(),
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getMemoryKeys(): array
    {
        $tmKeys = [];

        // this can happen even when fast analysis is not completed
        if (empty($this->chunkStruct->tm_keys)) {
            return $tmKeys;
        }

        $jobKeys = $this->chunkStruct->getClientKeys($this->user, Filter::OWNER)['job_keys'];

        foreach ($jobKeys as $tmKey) {
            $tmKeys[][trim($tmKey->name)] = trim($tmKey->key);
        }

        return $tmKeys;
    }

    /**
     * @return AnalysisJobSummary
     */
    public function getSummary(): AnalysisJobSummary
    {
        return $this->summary;
    }

    /**
     * @param int $raw
     *
     * @return void
     */
    public function incrementRaw(int $raw): void
    {
        $this->total_raw += $raw;
    }

    /**
     * @param float $equivalent
     *
     * @return void
     */
    public function incrementEquivalent(float $equivalent): void
    {
        $this->total_equivalent += $equivalent;
    }

    /**
     * @param float $industry
     *
     * @return void
     */
    public function incrementIndustry(float $industry): void
    {
        $this->total_industry += $industry;
    }

}