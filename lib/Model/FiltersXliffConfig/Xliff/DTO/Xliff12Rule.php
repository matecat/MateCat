<?php

namespace FiltersXliffConfig\Xliff\DTO;

class Xliff12Rule extends AbstractXliffRule
{
    /**
     * @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html
     */
    const ALLOWED_STATES = [
        'final',
        'needs-adaptation',
        'needs-l10n',
        'needs-review-adaptation',
        'needs-review-l10n',
        'needs-review-translation',
        'needs-translation',
        'new',
        'signed-off',
        'translated',
        'exact-match',
        'fuzzy-match',
        'id-match',
        'leveraged-glossary',
        'leveraged-inherited',
        'leveraged-mt',
        'leveraged-repository',
        'leveraged-tm',
        'mt-suggestion',
        'rejected-grammar',
        'rejected-inaccurate',
        'rejected-length',
        'rejected-spelling',
        'tm-suggestion'
    ];

    const ALLOWED_ANALYSIS = [
        "pre-translated",
        "new"
    ];

    const ALLOWED_EDITOR = [
        "translated,approved,approved2",
        "ignore-target-content,keep-target-content"
    ];
}