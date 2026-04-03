<?php

namespace Model\Segments;

class ContextUrlResolver
{
    public static function resolve(
        SegmentMetadataCollection $segmentMetadata,
        ?string $fileContextUrl,
        ?string $projectContextUrl
    ): ?string {
        return $segmentMetadata->find(SegmentMetadataMarshaller::CONTEXT_URL)
            ?? $fileContextUrl
            ?? $projectContextUrl;
    }
}
