<?php

namespace ConnectedServices\GDrive;

use Google_Logger_Abstract;

class GDriveLogger extends Google_Logger_Abstract {

    /**
     * @param mixed  $level
     * @param string $message
     * @param array  $contextgit
     *
     * @return bool
     * @throws \Exception
     */
    public function log( $level, $message, array $context = [] ) {
        if ( !$this->shouldHandle( $level ) ) {
            return false;
        }

        $date      = ( new \DateTime() )->format( 'd/M/Y:H:i:s O' );
        $levelName = is_int( $level ) ? array_search( $level, self::$levels ) : $level;
        $levelName = strtoupper( $levelName );
        $service   = isset( $context[ 'service' ] ) ? $context[ 'service' ] : '';
        $resource  = isset( $context[ 'resource' ] ) ? $context[ 'resource' ] : '';
        $method    = isset( $context[ 'method' ] ) ? $context[ 'method' ] : '';
        $fileId    = isset( $context[ 'arguments' ][ 'fileId' ][ 'value' ] ) ? $context[ 'arguments' ][ 'fileId' ][ 'value' ] : '';
        $url = \Google_Http_REST::createRequestUri(
                'drive/v2/',
                $this->getMethod($method)['path'],
                $context['arguments']
        );

        $message = "{\"date\": \"{$date}\", \"uri\":\"{$url}\", \"level\":\"{$levelName}\", \"service\":\"{$service}\", \"resource\":\"{$resource}\",\"method\":\"{$method}\",\"fileId\":\"{$fileId}\"}\n";

        $this->write( $message );
    }

    /**
     * @inheritDoc
     */
    protected function write( $message ) {
        $dest = \INIT::$LOG_REPOSITORY . DIRECTORY_SEPARATOR . 'gdrive.log';

        file_put_contents( $dest, $message, FILE_APPEND | LOCK_EX );
    }

    /**
     * @param $name
     *
     * @return mixed|null
     */
    private function getMethod($name) {
        $methods = [
                'copy'        => [
                        'path'       => 'files/{fileId}/copy',
                        'httpMethod' => 'POST',
                        'parameters' =>
                                [
                                        'fileId'             =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                        'convert'            =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocrLanguage'        =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'visibility'         =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'pinned'             =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocr'                =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'timedTextTrackName' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'timedTextLanguage'  =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'delete'      => [
                        'path'       => 'files/{fileId}',
                        'httpMethod' => 'DELETE',
                        'parameters' =>
                                [
                                        'fileId' =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                ],
                ],
                'emptyTrash'  => [
                        'path'       => 'files/trash',
                        'httpMethod' => 'DELETE',
                        'parameters' =>
                                [
                                ],
                ],
                'generateIds' => [
                        'path'       => 'files/generateIds',
                        'httpMethod' => 'GET',
                        'parameters' =>
                                [
                                        'maxResults' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'integer',
                                                ],
                                        'space'      =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'get'         => [
                        'path'       => 'files/{fileId}',
                        'httpMethod' => 'GET',
                        'parameters' =>
                                [
                                        'fileId'           =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                        'acknowledgeAbuse' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'updateViewedDate' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'revisionId'       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'projection'       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'insert'      => [
                        'path'       => 'files',
                        'httpMethod' => 'POST',
                        'parameters' =>
                                [
                                        'convert'                   =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'useContentAsIndexableText' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocrLanguage'               =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'visibility'                =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'pinned'                    =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocr'                       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'timedTextTrackName'        =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'timedTextLanguage'         =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'list'        => [
                        'path'       => 'files',
                        'httpMethod' => 'GET',
                        'parameters' =>
                                [
                                        'orderBy'    =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'projection' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'maxResults' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'integer',
                                                ],
                                        'q'          =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'pageToken'  =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'spaces'     =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'corpus'     =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'patch'       => [
                        'path'       => 'files/{fileId}',
                        'httpMethod' => 'PATCH',
                        'parameters' =>
                                [
                                        'fileId'                    =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                        'addParents'                =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'modifiedDateBehavior'      =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'removeParents'             =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'updateViewedDate'          =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'setModifiedDate'           =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'useContentAsIndexableText' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'convert'                   =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocrLanguage'               =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'pinned'                    =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'newRevision'               =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocr'                       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'timedTextLanguage'         =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'timedTextTrackName'        =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'touch'       => [
                        'path'       => 'files/{fileId}/touch',
                        'httpMethod' => 'POST',
                        'parameters' =>
                                [
                                        'fileId' =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                ],
                ],
                'trash'       => [
                        'path'       => 'files/{fileId}/trash',
                        'httpMethod' => 'POST',
                        'parameters' =>
                                [
                                        'fileId' =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                ],
                ],
                'untrash'     => [
                        'path'       => 'files/{fileId}/untrash',
                        'httpMethod' => 'POST',
                        'parameters' =>
                                [
                                        'fileId' =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                ],
                ],
                'update'      => [
                        'path'       => 'files/{fileId}',
                        'httpMethod' => 'PUT',
                        'parameters' =>
                                [
                                        'fileId'                    =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                        'addParents'                =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'modifiedDateBehavior'      =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'removeParents'             =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'updateViewedDate'          =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'setModifiedDate'           =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'useContentAsIndexableText' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'convert'                   =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocrLanguage'               =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'pinned'                    =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'newRevision'               =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'ocr'                       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'timedTextLanguage'         =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'timedTextTrackName'        =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
                'watch'       => [
                        'path'       => 'files/{fileId}/watch',
                        'httpMethod' => 'POST',
                        'parameters' =>
                                [
                                        'fileId'           =>
                                                [
                                                        'location' => 'path',
                                                        'type'     => 'string',
                                                        'required' => true,
                                                ],
                                        'acknowledgeAbuse' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'updateViewedDate' =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'boolean',
                                                ],
                                        'revisionId'       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                        'projection'       =>
                                                [
                                                        'location' => 'query',
                                                        'type'     => 'string',
                                                ],
                                ],
                ],
        ];

        return (isset($methods[$name])) ? $methods[$name] : null;
    }
}