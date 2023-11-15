<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:08
 *
 */

namespace API\App\Json\Analysis;

use JsonSerializable;

class AnalysisChunk implements JsonSerializable {

    /**
     * @var AnalysisFile[]
     */
    protected $files = null;
    /**
     * @var string
     */
    protected $password = null;

    public function __construct( $password ) {
        $this->password = $password;
    }

    /**
     * @param AnalysisFile $file
     *
     * @return $this
     */
    public function setFile( AnalysisFile $file ) {
        $this->files[ $file->getId() ] = $file;

        return $this;
    }

    public function jsonSerialize() {
        return [
                'password' => $this->password,
                'files'    => array_values( $this->files )
        ];
    }

    /**
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function hasFile( $id ) {
        return array_key_exists( $id, $this->files );
    }

}