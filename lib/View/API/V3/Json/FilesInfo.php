<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/20
 * Time: 18:08
 *
 */

namespace API\V3\Json;


use DataAccess_IDaoStruct;

class FilesInfo {

    /**
     * @param DataAccess_IDaoStruct[] $filesStructList
     *
     * @return array
     */
    public function render( $filesStructList ) {

        $result            = [];
        $result[ 'files' ] = [];

        $result[ 'first_segment' ] = reset( $filesStructList )->first_segment;
        $result[ 'last_segment' ]  = end( $filesStructList )->last_segment;

        foreach ( $filesStructList as $fileInfo ) {
            $result[ 'files' ][] = [
                    'id'             => $fileInfo->id_file,
                    'first_segment'  => $fileInfo->first_segment,
                    'last_segment'   => $fileInfo->last_segment,
                    'file_name'      => $fileInfo->file_name,
                    'raw_words'      => $fileInfo->raw_words,
                    'weighted_words' => $fileInfo->weighted_words
            ];
        }

        return $result;

    }

}