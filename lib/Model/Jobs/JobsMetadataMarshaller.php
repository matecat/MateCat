<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 21/01/26
 * Time: 15:40
 *
 */

namespace Model\Jobs;

enum JobsMetadataMarshaller: string
{
    case CHARACTER_COUNTER_COUNT_TAGS = 'character_counter_count_tags';
    case DIALECT_STRICT = 'dialect_strict';
    case PUBLIC_TM_PENALTY = 'public_tm_penalty';

    case TM_PRIORITIZATION = 'tm_prioritization';


    public static function unMarshall(MetadataStruct $struct): mixed
    {
        return (match ($struct->key) {
            JobsMetadataMarshaller::CHARACTER_COUNTER_COUNT_TAGS->value,
            JobsMetadataMarshaller::DIALECT_STRICT->value,
            JobsMetadataMarshaller::TM_PRIORITIZATION->value => fn() => (bool)$struct->value,
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value => fn() => (int)$struct->value,
            default => fn() => json_validate((string)$struct->value) ? json_decode((string)$struct->value, true) : (string)$struct->value,
        })();
    }

}
