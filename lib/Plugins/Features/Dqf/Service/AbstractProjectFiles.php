<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 15:13
 */

namespace Features\Dqf\Service;

use Exception;
use Features\Dqf\Service\Struct\CreateProjectResponseStruct;
use Features\Dqf\Service\Struct\MasterFileRequestStruct;
use Features\Dqf\Service\Struct\ProjectRequestStruct;
use Features\Dqf\Service\Struct\Request\FileTargetLanguageRequestStruct;
use Features\Dqf\Service\Struct\Response\FileResponseStruct;
use Features\Dqf\Service\Struct\Response\MaserFileCreationResponseStruct;
use Features\Dqf\Utils\Functions;
use Files_FileStruct;
use INIT;
use LoudArray;


abstract class AbstractProjectFiles {

    /**
     * @var MasterFileRequestStruct[]
     */
    protected $files = [];

    /**
     * @var ISession
     */
    protected $session ;

    /**
     * @var CreateProjectResponseStruct
     */
    protected $remoteProject ;

    /**
     * @var MaserFileCreationResponseStruct[]
     */
    protected $remoteFiles ;

    /**
     * @var array
     */
    protected $_targetLanguages ;

    abstract protected function getFilesPath();

    /**
     * AbstractProjectFiles constructor.
     *
     * @param ISession                    $session
     * @param CreateProjectResponseStruct $remoteProject
     */
    public function __construct( ISession $session, CreateProjectResponseStruct $remoteProject ) {
        $this->session = $session ;
        $this->remoteProject = $remoteProject ;
    }

    public function getFiles() {
        $requestStruct             = new ProjectRequestStruct();
        $requestStruct->projectId  = $this->remoteProject->dqfId ;
        $requestStruct->projectKey = $this->remoteProject->dqfUUID ;

        $requestStruct->sessionId = $this->session->getSessionId() ;
        $requestStruct->apiKey    = INIT::$DQF_API_KEY ;

        $client = new Client();
        $client->setSession( $this->session );

        $request = $client->createResource( $this->getFilesPath(), 'get', [
                'headers'    => $this->session->filterHeaders( $requestStruct ),
                'pathParams' => $requestStruct->getPathParams()
        ] );

        $client->curl()->multiExec();

        $content = json_decode( $client->curl()->getSingleContent( $request ), true );

        if ( $client->curl()->hasError( $request ) ) {
            throw new Exception('Error while fetching files: ' . json_encode( $client->curl()->getAllContents() ) ) ;
        }

        $outputArray = [];
        foreach( $content['modelList'] as $item ) {
            $id = $item['integratorFileMap']['clientValue'];
            $outputArray[ $id ] = new FileResponseStruct( $item ) ;
        }

        return $outputArray ;
    }

    /**
     * @return MaserFileCreationResponseStruct[]
     */
    public function getFilesResponseStructs() {
        return array_map( function( $element ) {
            return new MaserFileCreationResponseStruct([
                    'dqfId' => $element->id
            ]);
        }, $this->getFiles() ) ;
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
     * @return MaserFileCreationResponseStruct[]
     */
    public function submitFiles() {
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
                $requestStruct                     = new FileTargetLanguageRequestStruct() ;
                $requestStruct->targetLanguageCode = $language ;
                $requestStruct->sessionId          = $this->session->getSessionId() ;
                $requestStruct->projectKey         = $this->remoteProject->dqfUUID ;
                $requestStruct->projectId          = $this->remoteProject->dqfId ;
                $requestStruct->fileId             = $file->dqfId ;

                $client->createResource('/project/master/%s/file/%s/targetLang', 'post', [
                        'formData' => $requestStruct->getParams(),
                        'pathParams' => $requestStruct->getPathParams(),
                        'headers' => $this->session->filterHeaders( $requestStruct ),
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
                    'headers'  => $this->session->filterHeaders( $file ),
                    'formData' => $file->getParams()
            ], $file->clientId );
        }

        $client->curl()->multiExec();


        if ( count( $client->curl()->getErrors() ) > 0 ) {
            throw new \Exception('Errors while creating files: ') ;
        }

        foreach( $this->files as $file ) {
            $this->remoteFiles[ $file->clientId ] = new MaserFileCreationResponseStruct(
                    json_decode( $client->curl()->getSingleContent( $file->clientId ), true )
            );
        }
    }

}