<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 15:57
 */

namespace API\V2\Json;

use DataAccess\ShapelessConcreteStruct;
use LQA\ChunkReviewDao;
use Routes ;


class ProjectUrls {

    protected $data ;

    protected $jobs = [];
    protected $files = array();
    protected $chunks = array();

    /*
     * @var array
     */
    private $formatted = array('files' => array(), 'jobs' => array() );

    /**
     * ProjectUrls constructor.
     *
     * @param $data ShapelessConcreteStruct[]
     */
    public function __construct( $data ) {
        $this->data = $data;
    }

    /**
     * @param bool $keyAssoc
     *
     * @return array
     */
    public function render( $keyAssoc = false ) {

        /**
         * @var $record ShapelessConcreteStruct
         */
        foreach( $this->data as $key => $record ) {

            if (!array_key_exists( $record['id_file'], $this->files ) ) {
                $this->files[ $record['id_file'] ] = array(
                    'id' => $record['id_file'],
                    'name' => $record['filename'],
                    'original_download_url' => $this->downloadOriginalUrl( $record ),
                    'translation_download_url' => $this->downloadFileTranslationUrl( $record ),
                    'xliff_download_url' => $this->downloadXliffUrl( $record )
                );
            }

            if ( !array_key_exists( $record['jid'], $this->jobs ) ) {
                $this->jobs[ $record['jid'] ] = array(
                    'id' => $record['jid'],
                    'target_lang' => $record['target'],
                    'original_download_url' => $this->downloadOriginalUrl( $record ),
                    'translation_download_url' => $this->downloadTranslationUrl( $record ),
                    'xliff_download_url' => $this->downloadXliffUrl( $record ),
                    'chunks' => array()
                );
            }

            $this->generateChunkUrls( $record );

        }

        //maintain index association for external array access
        if( !$keyAssoc ){
            $this->formatted['jobs'] = array_values( $this->jobs );
            foreach( $this->formatted['jobs'] as &$chunks ){
                $chunks[ 'chunks' ] = array_values( $chunks[ 'chunks' ] );
            }
            $this->formatted['files'] = array_values( $this->files );
        } else {
            $this->formatted['jobs'] = $this->jobs;
            $this->formatted['files'] = $this->files;
        }

        // start over for jobs

        return $this->formatted;
    }

    protected function generateChunkUrls( $record ){

        if ( !array_key_exists( $record['jpassword'], $this->chunks ) ) {
            $this->chunks[ $record['jpassword'] ] = 1 ;

            $this->jobs[ $record['jid'] ][ 'chunks' ][ $record['jpassword'] ] = array(
                    'password'      => $record['jpassword'],
                    'translate_url' => $this->translateUrl( $record ),
                    'revise_url'    => $this->reviseUrl( $record )
            );
        }

    }

    public function getData(){
        return $this->data;
    }


    protected function downloadOriginalUrl($record) {
        return \Routes::downloadOriginal(
            $record['jid'],
            $record['jpassword'],
            $record['id_file']
        );
    }

    protected function downloadXliffUrl( $record ) {
        return Routes::downloadXliff(
            $record['jid'],
            $record['jpassword'],
            $record['id_file']
        );
    }

    protected function downloadFileTranslationUrl($record ) {
        return Routes::downloadTranslation(
                $record['jid'],
                $record['jpassword'],
                $record['id_file']
        );
    }

    protected function downloadTranslationUrl($record) {
        return Routes::downloadTranslation(
            $record['jid'],
            $record['jpassword'],
            ''
        );
    }

    protected function translateUrl( $record ) {
        return Routes::translate(
            $record['name'],
            $record['jid'],
            $record['jpassword'],
            $record['source'],
            $record['target']
        );
    }

    protected function reviseUrl( $record ) {
        return \Routes::revise(
            $record['name'],
            $record['jid'],
            $record['jpassword'],
            $record['source'],
            $record['target']
        );

    }
}