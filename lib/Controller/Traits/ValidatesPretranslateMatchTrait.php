<?php

namespace Controller\Traits;

use InvalidArgumentException;
use Utils\Constants\TranslationStatus;

trait ValidatesPretranslateMatchTrait
{
    /**
     * The options a request leaves out: a 101% and a 100% match are both approved and locked.
     */
    private const array PRETRANSLATE_MATCH_DEFAULTS = [
        '101' => ['lock' => 1, 'status' => TranslationStatus::STATUS_APPROVED],
        '100' => ['lock' => 1, 'status' => TranslationStatus::STATUS_APPROVED],
    ];

    /**
     * Validate the `pretranslate_<match>_lock` and `pretranslate_<match>_status` options of one match type.
     *
     * @param '100'|'101' $match
     * @param mixed       $lock   raw request value, null when the request leaves it out
     * @param mixed       $status raw request value, null when the request leaves it out
     *
     * @return array{lock: int, status: string}
     *
     * @throws InvalidArgumentException when an option is not valid
     */
    private function validatePretranslateMatchParams(string $match, mixed $lock, mixed $status): array
    {
        $defaults = self::PRETRANSLATE_MATCH_DEFAULTS[$match];

        $lock = filter_var($lock ?? $defaults['lock'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1],
            'flags' => FILTER_NULL_ON_FAILURE,
        ]);
        if ($lock === null) {
            throw new InvalidArgumentException("Invalid pretranslate_{$match}_lock value", -6);
        }

        $status = TranslationStatus::preTranslateStatus($status ?? $defaults['status'])
            ?? throw new InvalidArgumentException("Invalid pretranslate_{$match}_status value", -6);

        return ['lock' => $lock, 'status' => $status];
    }
}
