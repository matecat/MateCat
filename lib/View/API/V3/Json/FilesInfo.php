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
     * @param      $filesStructList
     * @param null $job_first_segment
     * @param null $job_last_segment
     *
     * @return array
     */
    public function render( $filesStructList, $job_first_segment = null, $job_last_segment = null ) {

        $result            = [];
        $result[ 'files' ] = [];

        $result[ 'first_segment' ] = ($job_first_segment) ? $job_first_segment : reset( $filesStructList )->first_segment;
        $result[ 'last_segment' ]  = ($job_last_segment) ? $job_last_segment : end( $filesStructList )->last_segment;

        foreach ( $filesStructList as $fileInfo ) {
            $result[ 'files' ][] = [
                    'id'             => $fileInfo->id_file,
                    'first_segment'  => $fileInfo->first_segment,
                    'last_segment'   => $fileInfo->last_segment,
                    'file_name'      => $fileInfo->file_name,
                    'raw_words'      => $fileInfo->raw_words,
                    'weighted_words' => $fileInfo->weighted_words,
                    'metadata'       => $fileInfo->metadata,
            ];
        }

        return $result;

    }

}