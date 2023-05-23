<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 02/05/23
 * Time: 17:58
 *
 */

namespace TMS;

use stdClass;

class TMSFile extends stdClass {

    private $file_path;
    private $tm_key;
    private $name;
    private $position;
    private $uuid;

    /**
     * @param $file_path
     * @param $tm_key
     * @param $name
     * @param $position
     */
    public function __construct( $file_path, $tm_key, $name, $position = 0 ) {
        $this->file_path = $file_path;
        $this->tm_key    = $tm_key;
        $this->name      = $name;
        $this->position  = $position;
    }

    /**
     * @return mixed
     */
    public function getFilePath() {
        return $this->file_path;
    }

    /**
     * @return mixed
     */
    public function getTmKey() {
        return $this->tm_key;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getPosition() {
        return $this->position;
    }

    /**
     * @return mixed
     */
    public function getUuid() {
        return $this->uuid;
    }

    /**
     * @param mixed $uuid
     *
     * @return $this
     */
    public function setUuid( $uuid ) {
        $this->uuid = $uuid;

        return $this;
    }

}