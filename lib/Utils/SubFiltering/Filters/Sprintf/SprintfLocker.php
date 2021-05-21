<?php

namespace SubFiltering\Filters\Sprintf;

class SprintfLocker {

    /**
     * @var null
     */
    private $source;

    /**
     * @var null
     */
    private $target;

    /**
     * @var array
     */
    private $notAllowedMap = [];

    /**
     * @var array
     */
    private $replacementMap = [];

    /**
     * Analiser constructor.
     *
     * @param null $source
     * @param null $target
     */
    public function __construct($source = null, $target = null) {
        $this->source = $source;
        $this->target = $target;
        $this->notAllowedMap = $this->createNotAllowedMap();
        $this->replacementMap = $this->createReplacementMap();
    }

    /**
     * @return array
     */
    private function createNotAllowedMap() {
        $map = [];

        $all = include __DIR__ . "/language/all/not_allowed.php";
        $map = array_merge($map, $all);

        if($this->source and file_exists(__DIR__ . "/language/".$this->source."/not_allowed.php")){
            $source = include __DIR__ . "/language/".$this->source."/not_allowed.php";
            $map = array_merge($map, $source);
        }

        if($this->target and file_exists(__DIR__ . "/language/".$this->target."/not_allowed.php")){
            $target = include __DIR__ . "/language/".$this->target."/not_allowed.php";
            $map = array_merge($map, $target);
        }

        return $map;
    }

    private function createReplacementMap() {
        $map = [];

        foreach ($this->notAllowedMap as $item){
            $map[] = '_____########'.str_replace(['%','-','_'],'', $item).'########_____';
        }

        return $map;
    }

    /**
     * @return string
     */
    public function lock($segment){
        return str_replace( $this->notAllowedMap, $this->replacementMap, $segment );
    }

    /**
     * @return string
     */
    public function unlock($segment){
        return str_replace( $this->replacementMap, $this->notAllowedMap, $segment );
    }
}