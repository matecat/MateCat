<?php

namespace FiltersXliffConfig\Xliff\DTO;

class Xliff20Rule extends AbstractXliffRule
{
    const ALLOWED_STATES = [
        'initial',
        'translated',
        'reviewed',
        'final'
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