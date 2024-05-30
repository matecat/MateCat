<?php

namespace FiltersXliffConfig\Xliff\DTO;

use DomainException;
use JsonSerializable;

abstract class AbstractXliffRule implements JsonSerializable
{
    const ALLOWED_STATES = [];
    const ALLOWED_ANALYSIS = [];
    const ALLOWED_EDITOR = [];

    protected $state;
    protected $analysis;
    protected $editor;

    /**
     * Xliff12Rule constructor.
     * @param $state
     * @param $analysis
     * @param $editor
     */
    public function __construct(array $state, $analysis, $editor)
    {
        $this->setState($state);
        $this->setAnalysis($analysis);
        $this->setEditor($editor);
    }

    /**
     * @param array $state
     */
    protected function setState(array $state)
    {
        if(!is_array($state)){
            throw new DomainException("Wrong state value");
        }

        foreach ($state as $s){
            if(!in_array($s, static::ALLOWED_STATES)){
                throw new DomainException("Wrong state value");
            }
        }

        $this->state = $state;
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
            'state' => $this->state,
            'analysis' => $this->analysis,
            'editor' => $this->editor,
        ];
    }
}