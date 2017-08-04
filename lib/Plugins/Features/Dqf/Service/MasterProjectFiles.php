<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 15:13
 */

namespace Features\Dqf\Service;

use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\MasterFileRequestStruct;
use Features\Dqf\Service\Struct\Request\FileTargetLanguageRequestStruct;
use Features\Dqf\Service\Struct\Response\MasterFileResponseStruct;
use Features\Dqf\Utils\Functions;
use Files_FileStruct;
use Symfony\Component\Config\Definition\Exception\Exception;


class MasterProjectFiles {

    /**
     * @var MasterFileRequestStruct[]
     */
    protected $files = [];

    /**
     * @var Session
     */
    protected $session ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;

    /**
     * @var MasterFileResponseStruct[]
     */
    protected $remoteFiles ;

    /**
     * @var array
     */
    protected $_targetLanguages ;

    public function __construct( Session $session, CreateProjectResponseStruct $remoteProject ) {
        $this->session = $session ;
        $this->remoteProject = $remoteProject ;
    }

    public function setFile( Files_FileStruct $file, $numberOfSegments ) {
        $fileRequestStruct = new MasterFileRequestStruct();

        $fileRequestStruct->sessionId   = $this->session->getSessionId();
        $fileRequestStruct->projectKey  = $this->remoteProject->dqfUUID ;

        $fileRequestStruct->name             = $file->filename ;
        $fileRequestStruct->clientId         = Functions::scopeId( $file->id );
        $fileRequestStruct->numberOfSegments = $numberOfSegments ;

        $this->files[] = $fileRequestStruct ;
    }

    /**
     * @return MasterFileResponseStruct[]
     */
    public function getRemoteFiles() {
        $this->_submitFiles();
        $this->_submitTargetLanguages();

        return $this->remoteFiles ;
    }

    public function setTargetLanguages( $languages ) {
        $this->_targetLanguages = $languages ;
    }

    protected function _submitTargetLanguages() {
        $client = new Client();
        $client->setSession( $this->session );

        foreach( $this->remoteFiles as $file ) {
            foreach( $this->_targetLanguages as $language ) {
                $requestStruct = new FileTargetLanguageRequestStruct() ;
                $requestStruct->targetLanguageCode = $language ;
                $requestStruct->sessonId = $this->session->getSessionId() ;
                $requestStruct->projectKey = $this->remoteProject->dqfUUID ;
                $requestStruct->projectId = $this->remoteProject->dqfId ;
                $requestStruct->fileId = $file->dqfId ;

                $client->createResource('/project/master/%s/file/%s/targetLang', 'post', [
                        'formData' => $requestStruct->getParams(),
                        'pathParams' => $requestStruct->getPathParams(),
                        'headers' => $requestStruct->getHeaders()
                ] );
            }
        }

        $client->curl()->multiExec();

        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new \Exception('Errors setting files target languages: ' .
            implode(', ', $client->curl()->getAllContents() )) ;
        }

        return true ;
    }

    protected function _submitFiles() {
        $client = new Client();
        $client->setSession( $this->session );
        $url = sprintf( '/project/master/%s/file', $this->remoteProject->dqfId ) ;

        foreach( $this->files as $file ) {
            $client->createResource( $url, 'post', [
                    'headers'  => $file->getHeaders(),
                    'formData' => $file->getParams()
            ], $file->clientId );
        }

        $client->curl()->multiExec();


        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new \Exception('Errors while creating files: ') ;
        }

        foreach( $this->files as $file ) {
            $this->remoteFiles[ $file->clientId ] = new MasterFileResponseStruct(
                    json_decode( $client->curl()->getSingleContent( $file->clientId ), true )
            );
        }
    }

}