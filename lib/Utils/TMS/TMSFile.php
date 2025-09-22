<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 02/05/23
 * Time: 17:58
 *
 */

namespace Utils\TMS;

use stdClass;

class TMSFile extends stdClass {

    private string  $file_path;
    private string  $tm_key;
    private string  $name;
    private int     $position;
    private ?string $uuid = null;

    /**
     * @param string $file_path
     * @param string $tm_key
     * @param string $name
     * @param int    $position
     */
    public function __construct( string $file_path, string $tm_key, string $name, int $position = 0 ) {
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
     * @return string
     */
    public function getTmKey(): string {
        return $this->tm_key;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getPosition(): int {
        return $this->position;
    }

    /**
     * @return string|null
     */
    public function getUuid(): ?string {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     *
     * @return $this
     */
    public function setUuid( string $uuid ): TMSFile {
        $this->uuid = $uuid;

        return $this;
    }

}