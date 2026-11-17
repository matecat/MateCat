<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 21/01/26
 * Time: 15:40
 *
 */

namespace Model\Jobs;

use Matecat\SubFiltering\Enum\InjectableFiltersTags;

enum JobsMetadataMarshaller: string
{
    case CHARACTER_COUNTER_COUNT_TAGS = 'character_counter_count_tags';
    case CHARACTER_COUNTER_MODE       = 'character_counter_mode';
    case DIALECT_STRICT               = 'dialect_strict';
    case MANDATORY_ISSUES             = 'mandatory_issues';
    case PUBLIC_TM_PENALTY            = 'public_tm_penalty';
    case SUBFILTERING_HANDLERS        = 'subfiltering_handlers';
    case TM_PRIORITIZATION            = 'tm_prioritization';

    /** The modes the character counter knows, as NewController and job_metadata.json accept them. */
    private const array CHARACTER_COUNTER_MODES = ['google_ads', 'exclude_cjk', 'all_one'];

    /** The revision phases an issue can be made mandatory for. */
    private const array MANDATORY_ISSUES_PHASES = ['r1', 'r2'];

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

    /**
     * The opposite of unMarshall(): the string a typed value is stored as, or null when the value is
     * not one this key accepts.
     *
     * The rules are the ones the write paths enforce — job_metadata.json for the editor, the range
     * check in UpdateJobKeysController, the options NewController accepts at creation — so a value
     * that passes here is one that could have been written in the first place.
     */
    public function marshall(mixed $value): ?string
    {
        return match ($this) {
            self::CHARACTER_COUNTER_COUNT_TAGS,
            self::DIALECT_STRICT,
            self::TM_PRIORITIZATION => is_bool($value) ? ($value ? '1' : '0') : null,
            self::PUBLIC_TM_PENALTY => is_int($value) && $value >= 0 && $value <= 100 ? (string)$value : null,
            self::CHARACTER_COUNTER_MODE => is_string($value) && in_array($value, self::CHARACTER_COUNTER_MODES, true) ? $value : null,
            self::MANDATORY_ISSUES => self::marshallList($value, fn(mixed $phase): bool => in_array($phase, self::MANDATORY_ISSUES_PHASES, true)),
            // A JSON null stores "every handler disabled", and '' and 'none' are the two spellings
            // subfiltering_handlers.json lets the editor send for the same thing. They are kept as
            // they are: a reader decodes each of them to null.
            self::SUBFILTERING_HANDLERS => match (true) {
                $value === null => 'null',
                $value === '', $value === 'none' => $value,
                default => self::marshallList($value, fn(mixed $tag): bool => is_string($tag) && InjectableFiltersTags::tryFrom($tag) !== null),
            },
        };
    }

    /**
     * Keep only the rows of a raw `key => value` map that are job metadata, each re-written in its
     * canonical form. This is what a copy goes through, so it carries what the marshaller vouches for
     * and not merely whatever the table holds.
     *
     * Two checks per row. The key has to be a case of this enum: anything else — MMT's mt_context, a
     * key a feature has since stopped reading — is not job configuration and stays where it is. The
     * value has to survive unMarshall() and marshall() back. unMarshall() alone is too lenient for
     * that: its casts turn any string into a boolean or an integer, so the scalar keys are checked on
     * the raw string first.
     *
     * @param array<string, string> $raw
     *
     * @return array<string, string>
     */
    public static function sanitizeRawMap(array $raw): array
    {
        $sanitized = [];

        foreach ($raw as $key => $rawValue) {
            $case = self::tryFrom($key);

            if ($case === null || !$case->acceptsRaw($rawValue)) {
                continue;
            }

            $value = $case->marshall(self::unMarshall(new MetadataStruct(['key' => $key, 'value' => $rawValue])));

            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Whether the stored string is one unMarshall() reads without guessing. A boolean is stored as
     * '1' or '0', or as '' when the editor sent false through a string parameter; a penalty is a
     * plain non-negative integer. The other keys are JSON or a plain word, which marshall() checks
     * once decoded.
     */
    private function acceptsRaw(string $raw): bool
    {
        return match ($this) {
            self::CHARACTER_COUNTER_COUNT_TAGS,
            self::DIALECT_STRICT,
            self::TM_PRIORITIZATION => in_array($raw, ['1', '0', ''], true),
            self::PUBLIC_TM_PENALTY => preg_match('/^\d{1,3}$/', $raw) === 1,
            default => true,
        };
    }

    /**
     * @param callable(mixed): bool $isValidItem
     */
    private static function marshallList(mixed $value, callable $isValidItem): ?string
    {
        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        foreach ($value as $item) {
            if (!$isValidItem($item)) {
                return null;
            }
        }

        $encoded = json_encode($value);

        return $encoded === false ? null : $encoded;
    }

}
