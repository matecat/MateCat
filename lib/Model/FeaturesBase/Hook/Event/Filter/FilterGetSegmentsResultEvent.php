<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;
use Model\Jobs\JobStruct;

/**
 * @see \Controller\API\App\GetSegmentsController::segments() — dispatch site
 */
final class FilterGetSegmentsResultEvent extends FilterEvent
{
    public static function hookName(): string
    {
        return 'filterGetSegmentsResult';
    }
    public function __construct(
        private array $data,
        private readonly JobStruct $chunk,
    ) {
    }
    public function getData(): array
    {
        return $this->data;
    }
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getChunk(): JobStruct
    {
        return $this->chunk;
    }
}
