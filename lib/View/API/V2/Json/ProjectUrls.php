<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:57
 */

namespace API\V2\Json;

use Routes ;


class ProjectUrls {

    private $data ;

    /*
     * @var array
     */
    private $formatted = array('files' => array(), 'jobs' => array() );

    public function __construct( $data ) {
        $this->data = $data;


    }

    public function render() {

        $files = array();

        $jobs = array();
        $chunks = array();

        foreach($this->data as $key => $record ) {

            if (!array_key_exists( $record['id_file'], $files ) ) {
                $files[ $record['id_file'] ] = array(
                    'id' => $record['id_file'],
                    'name' => $record['filename'],
                    'original_download_url' => $this->downloadOriginalUrl( $record ),
                    'translation_download_url' => $this->downloadTranslationUrl( $record ),
                    'xliff_download_url' => $this->downloadXliffUrl( $record )
                );
            }

            if ( !array_key_exists( $record['jid'], $jobs ) ) {
                $jobs[ $record['jid'] ] = array(
                    'id' => $record['jid'],
                    'target_lang' => $record['target'],
                    'chunks' => array()
                );
            }

            if ( !array_key_exists( $record['jpassword'], $chunks ) ) {
                $chunks[ $record['jpassword'] ] = 1 ;

                $jobs[ $record['jid'] ][ 'chunks' ][] = array(
                    'password'      => $record['jpassword'],
                    'translate_url' => $this->translateUrl( $record ),
                    'revise_url'    => $this->reviseUrl( $record )
                );
            }

        }

        $this->formatted['jobs'] = array_values( $jobs );
        $this->formatted['files'] = array_values( $files );

        // start over for jobs

        // return array('urls' => $this->data );

        return array('urls' => $this->formatted );
    }


    private function downloadOriginalUrl($record) {
        return \Routes::downloadOriginal(
            $record['jid'],
            $record['jpassword'],
            $record['id_file']
        );
    }

    private function downloadXliffUrl( $record ) {
        return Routes::downloadXliff(
            $record['jid'],
            $record['jpassword'],
            $record['id_file']
        );
    }

    private function downloadTranslationUrl($record) {
        return Routes::downloadTranslation(
            $record['jid'],
            $record['jpassword'],
            $record['id_file']
        );
    }

    private function translateUrl( $record ) {
        return Routes::translate(
            $record['name'],
            $record['jid'],
            $record['jpassword'],
            $record['source'],
            $record['target']
        );
    }

    private function reviseUrl( $record ) {
        return \Routes::revise(
            $record['name'],
            $record['jid'],
            $record['jpassword'],
            $record['source'],
            $record['target']
        );


    }
}