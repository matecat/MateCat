<?php

namespace Model\Filters\DTO;

/**
 * Normalises the element/key name lists of the filters whose names cannot carry padding.
 *
 * XML and DITA element names, MS Word style and highlight colour names, MS Excel columns and
 * MS PowerPoint slide numbers are all tokens in which a leading or trailing space is never
 * meaningful: the remote filters would simply fail to match them. Users type that padding by
 * accident and cannot see it afterwards, because a pill collapses whitespace when it renders.
 *
 * JSON object keys and YAML mapping keys are arbitrary strings, so " label " is a legitimate
 * key distinct from "label". Json and Yaml deliberately do not use this trait.
 *
 * The extraction parameters schema declares every list as a bare array with no `items`
 * constraint, so a member may arrive as a number as well as a string.
 */
trait NormalizesNamesTrait
{

    /**
     * Trim every name and drop the ones left empty.
     *
     * @param array<array-key, mixed> $names
     *
     * @return list<string>
     */
    private function normalizeNames(array $names): array
    {
        $normalized = [];

        foreach ($names as $name) {
            if (!is_scalar($name)) {
                continue;
            }

            $trimmed = trim((string)$name);

            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }

}
