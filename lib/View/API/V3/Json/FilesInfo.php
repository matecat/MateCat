<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/20
 * Time: 18:08
 *
 */

namespace API\V3\Json;


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
        $result[ 'first_segment' ] = ($job_first_segment) ? (int)$job_first_segment :(int) reset( $filesStructList )->first_segment;
        $result[ 'last_segment' ]  = ($job_last_segment) ? (int)$job_last_segment : (int)end( $filesStructList )->last_segment;

        foreach ( $filesStructList as $fileInfo ) {
            $result[ 'files' ][] = [
                    'id'             => (int)$fileInfo->id_file,
                    'first_segment'  => (int)$fileInfo->first_segment,
                    'last_segment'   => (int)$fileInfo->last_segment,
                    'file_name'      => $fileInfo->file_name,
                    'raw_words'      => floatval($fileInfo->raw_words),
                    'weighted_words' => floatval($fileInfo->weighted_words),
                    'standard_words' => floatval($fileInfo->standard_words),
                    'metadata'       => $fileInfo->metadata,
            ];
        }

        return $result;

    }

}