<?php

namespace Model\Segments;

enum SegmentMetadataMarshaller: string
{
    case ID_REQUEST     = 'id_request';
    case ID_CONTENT     = 'id_content';
    case ID_ORDER       = 'id_order';
    case ID_ORDER_GROUP = 'id_order_group';
    case SCREENSHOT     = 'screenshot';
    case SIZE_RESTRICTION = 'sizeRestriction';

    public static function isAllowed(string $key): bool
    {
        return self::tryFrom($key) !== null;
    }

    public function marshall(mixed $value): ?string
    {
        return match ($this) {
            self::SIZE_RESTRICTION => ((int)$value > 0) ? (string)(int)$value : null,
            default => (string)$value,
        };
    }

    /**
     * Convert a stored string value back to its typed PHP representation.
     *
     * This is the reverse of {@see marshall()}.
     */
    public function unmarshall(string $storedValue): mixed
    {
        return match ($this) {
            self::SIZE_RESTRICTION => (int)$storedValue,
            default => $storedValue,
        };
    }
}
