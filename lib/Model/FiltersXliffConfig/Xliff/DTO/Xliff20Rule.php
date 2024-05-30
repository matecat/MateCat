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
}