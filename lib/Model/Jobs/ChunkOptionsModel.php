<?php


namespace Model\Jobs;

use Exception;
use Model\ProjectManager\ProjectOptionsSanitizer;
use Model\Projects\MetadataDao;
use ReflectionException;

class ChunkOptionsModel
{

    private JobStruct $chunk;

    public static array $valid_keys = [
        'speech2text',
        'tag_projection',
        'lexiqa'
    ];

    private array $received_options = [];
    public array $project_metadata = [];

    public function __construct(JobStruct $chunk)
    {
        $this->chunk = $chunk;
        $this->project_metadata = $chunk->getProject()->getMetadataAsKeyValue();
    }

    /**
     * @throws Exception
     */
    public function isEnabled(string $key): int
    {
        $value = $this->getByChunkOrProjectOption($key);

        $sanitizer = new ProjectOptionsSanitizer([$key => $value]);
        $sanitizer->setLanguages($this->chunk->source, [$this->chunk->target]);

        $sanitized = $sanitizer->sanitize();

        return $sanitized[$key];
    }

    /**
     * @throws Exception
     */
    public function setOptions($options): void
    {
        $filtered = array_intersect_key($options, array_flip(self::$valid_keys));

        $sanitizer = new ProjectOptionsSanitizer($filtered);
        $sanitizer->setLanguages($this->chunk->source, [$this->chunk->target]);

        $sanitized = $sanitizer->sanitize();

        $this->received_options = array_merge(
            $filtered,
            $sanitized
        );
    }

    /**
     * @throws ReflectionException
     */
    public function save(): void
    {
        if (empty($this->received_options)) {
            return;
        }

        $dao = new MetadataDao();

        foreach ($this->received_options as $key => $value) {
            $dao->set($this->chunk->id_project, MetadataDao::buildChunkKey($key, $this->chunk), $value);
        }

        $this->project_metadata = $this->chunk->getProject()->getMetadataAsKeyValue();
    }

    /**
     * @throws Exception
     */
    public function toArray(): array
    {
        $out = [];

        foreach (static::$valid_keys as $name) {
            $out[$name] = $this->isEnabled($name);
        }

        return $out;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    private function getByChunkOrProjectOption($key): bool
    {
        $chunk_key = MetadataDao::buildChunkKey($key, $this->chunk);

        if (isset($this->project_metadata[$chunk_key])) {
            return !!$this->project_metadata[$chunk_key];
        } elseif (isset($this->project_metadata[$key])) {
            return !!$this->project_metadata[$key];
        } else {
            return false;
        }
    }

}