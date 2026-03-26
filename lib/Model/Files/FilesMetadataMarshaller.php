<?php

namespace Model\Files;

enum FilesMetadataMarshaller: string
{
    case INSTRUCTIONS = 'instructions';
    case PDF_ANALYSIS = 'pdfAnalysis';
    case CONTEXT_URL  = 'context-url';

    public static function isAllowed(string $key): bool
    {
        return self::tryFrom($key) !== null;
    }

    public function marshall(mixed $value): ?string
    {
        return match ($this) {
            self::PDF_ANALYSIS => is_string($value) ? $value : json_encode($value),
            default            => (string)$value,
        };
    }

    public static function unMarshall(MetadataStruct $struct): mixed
    {
        return match ($struct->key) {
            self::PDF_ANALYSIS->value => json_validate($struct->value)
                ? json_decode($struct->value, true)
                : $struct->value,
            default => $struct->value,
        };
    }
}
