<?php

namespace FiltersXliffConfig\Xliff\DTO;

use DomainException;
use JsonSerializable;

abstract class AbstractXliffRule implements XliffRuleInterface, JsonSerializable
{
    const ALLOWED_STATES = [];

    const ALLOWED_ANALYSIS = [
        "pre-translated",
        "new"
    ];

    const ALLOWED_EDITOR = [
        "translated",
        "approved",
        "approved2",
        "ignore-target-content",
        "keep-target-content",
    ];

    protected $states;
    protected $analysis;
    protected $editor;

    /**
     * AbstractXliffRule constructor.
     * @param array $states
     * @param $analysis
     * @param $editor
     */
    public function __construct(array $states, $analysis, $editor)
    {
        $this->setStates($states);
        $this->setAnalysis($analysis);
        $this->setEditor($editor);
        $this->validateAnalysisAndEditor($analysis, $editor);
    }

    /**
     * @param $analysis
     * @param $editor
     */
    protected function validateAnalysisAndEditor($analysis, $editor)
    {
        $validationMap = [
            'new' => [
                "ignore-target-content",
                "keep-target-content"
            ],
            'pre-translated' => [
                "translated",
                "approved",
                "approved2",
            ],
        ];

        if(!isset($validationMap[$analysis])){
            throw new DomainException("Wrong analysis value");
        }

        if(!in_array($editor, $validationMap[$analysis])){
            throw new DomainException("Wrong analysis/editor combination");
        }
    }

    /**
     * @param array $states
     */
    protected function setStates(array $states)
    {
        if(!is_array($states)){
            throw new DomainException("Wrong states value");
        }

        foreach ($states as $state){
            if(!in_array($state, static::ALLOWED_STATES)){
                throw new DomainException("Wrong state value");
            }
        }

        $this->states = $states;
    }

    /**
     * @param $analysis
     */
    protected function setAnalysis($analysis)
    {
        if(!in_array($analysis, static::ALLOWED_ANALYSIS)){
            throw new DomainException("Wrong analysis value");
        }

        $this->analysis = $analysis;
    }

    /**
     * @param $editor
     */
    protected function setEditor($editor)
    {
        if(!in_array($editor, static::ALLOWED_EDITOR)){
            throw new DomainException("Wrong editor value");
        }

        $this->editor = $editor;
    }

    /**
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return [
            'states' => $this->states,
            'analysis' => $this->analysis,
            'editor' => $this->editor,
        ];
    }
}