<?php

namespace Webhooks\GDrive  ;

use Bootstrap ; 
use Log;
use API\V2\KleinController ;
use INIT ; 
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use GDrive;
use Constants;

class ManageController extends KleinController {   
    public function listImportedFiles() {
        $response = array();
        
        $fileList = $_SESSION[ GDrive::SESSION_FILE_LIST ];

        foreach ( $fileList as $fileId => $file) {
            $path = $this->getGDriveFilePath( $file );

            $fileName = $file[ GDrive::SESSION_FILE_NAME ];

            if(file_exists($path) !== false) {
                $fileSize = filesize($path);

                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

                $response[ 'files' ][] = array(
                    'fileId' => $fileId,
                    'fileName' => $fileName,
                    'fileSize' => $fileSize,
                    'fileExtension' => $fileExtension
                );
            } else {
                unset( $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] );
            }
        }

        $this->response->json($response);
    }
    
    private function getGDriveFilePath( $file ) {
        $fileName = $file[ GDrive::SESSION_FILE_NAME ];
        
        $cacheFileDir = $this->getCacheFileDir( $file );
        
        $path = $cacheFileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;
        
        return $path;
    }
    
    private function getCacheFileDir( $file, $lang = '' ){
        $sourceLang = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];
        
        if( $lang !== '' ) {
            $sourceLang = $lang;
        }

        $fileHash = $file[ GDrive::SESSION_FILE_HASH ];

        $cacheTreeAr = array(
            'firstLevel'  => $fileHash{0} . $fileHash{1},
            'secondLevel' => $fileHash{2} . $fileHash{3},
            'thirdLevel'  => substr( $fileHash, 4 )
        );

        $cacheTree = implode(DIRECTORY_SEPARATOR, $cacheTreeAr);

        $cacheFileDir = INIT::$CACHE_REPOSITORY . DIRECTORY_SEPARATOR . $cacheTree . "|" . $sourceLang;
        
        return $cacheFileDir;
    }
    
    private function getUploadDir(){
        return INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . filter_input(INPUT_COOKIE, 'upload_session');
    }
    
    public function changeSourceLanguage() {
        $originalSourceLang = $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ];

        $newSourceLang = $this->request->sourceLanguage;
        
        $fileList = $_SESSION[ GDrive::SESSION_FILE_LIST ];
        
        $success = true;
        
        foreach( $fileList as $fileId => $file ) {
            if($success) {
                $fileHash = $file[ GDrive::SESSION_FILE_HASH ];

                if($newSourceLang !== $originalSourceLang) {

                    $originalCacheFileDir = $this->getCacheFileDir( $file, $originalSourceLang );

                    $newCacheFileDir = $this->getCacheFileDir( $file, $newSourceLang );

                    $renameDirSuccess = rename($originalCacheFileDir, $newCacheFileDir);

                    $uploadDir = $this->getUploadDir();

                    $originalUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $originalSourceLang;
                    $newUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $newSourceLang;

                    $renameFileRefSuccess = rename($originalUploadRefFile, $newUploadRefFile);

                    if(!$renameDirSuccess || !$renameFileRefSuccess) {
                        Log::doLog('Error when moving cache file dir to ' . $newCacheFileDir);

                        $success = false;
                    }
                }
            }
        }

        if( $success ) {
            $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $newSourceLang;

            $ckSourceLang = filter_input(INPUT_COOKIE, Constants::COOKIE_SOURCE_LANG);

            if ( $ckSourceLang == null || $ckSourceLang === false || $ckSourceLang === Constants::EMPTY_VAL ) {
                $ckSourceLang = '';
            }

            $newCookieVal = $newSourceLang . '||' . $ckSourceLang;

            setcookie( Constants::COOKIE_SOURCE_LANG, $newCookieVal, time() + ( 86400 * 365 ), '/' );
        } else {
            $_SESSION[ Constants::SESSION_ACTUAL_SOURCE_LANG ] = $originalSourceLang;
        }
        
        $response = array(
            "success" => $success
        );
        
        $this->response->json($response);
    }
    
    private function deleteDirectory($dir) {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir);
    }
    
    public function deleteImportedFile() {
        $fileId = $this->request->fileId;
        
        $success = false;
        
        if( isset( $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] ) ) {
            $file = $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ];
            
            $pathCache = $this->getCacheFileDir( $file );

            $this->deleteDirectory($pathCache);

            unset( $_SESSION[ GDrive::SESSION_FILE_LIST ][ $fileId ] );

            Log::doLog( 'File ' . $fileId . ' removed.' );
            
            $success = true;
        }
        
        $this->response->json( array(
            "success" => $success
        ));
    }

    protected function afterConstruct() {
        Bootstrap::sessionStart();  
    }
}