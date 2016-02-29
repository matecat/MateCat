<?php

namespace Webhooks\GDrive  ;

use Bootstrap ; 
use Log;
use API\V2\KleinController ;
use INIT ; 

class ManageController extends KleinController {
    public function listImportedFiles() {
        Bootstrap::sessionStart();
        
        $path = $this->getGDriveFilePath();
        $fileName = $_SESSION['pre_loaded_file'];
        
        if(file_exists($path) !== false) {
            $fileSize = filesize($path);

            $response = array(
                'fileName' => $fileName,
                'fileSize' => $fileSize
            );

            echo json_encode($response);
        }
    }
    
    private function getGDriveFilePath() {
        $fileName = $_SESSION['pre_loaded_file'];
        
        $cacheFileDir = $this->getCacheFileDir();
        
        $path = $cacheFileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;
        
        return $path;
    }
    
    private function getCacheFileDir(){
        $sourceLang = $_SESSION['actualSourceLang'];
        
        $fileHash = $_SESSION['google_drive_file_sha1'];
                    
        $cacheTreeAr = array(
            'firstLevel'  => $fileHash{0} . $fileHash{1},
            'secondLevel' => $fileHash{2} . $fileHash{3},
            'thirdLevel'  => substr( $fileHash, 4 )
        );

        $cacheTree = implode(DIRECTORY_SEPARATOR, $cacheTreeAr);

        $cacheFileDir = INIT::$CACHE_REPOSITORY . DIRECTORY_SEPARATOR . $cacheTree . "|" . $sourceLang;
        
        return $cacheFileDir;
    }
    
    public function changeSourceLanguage() {
        Bootstrap::sessionStart();
        
        $originalSourceLang = $_SESSION['actualSourceLang'];
        
        $fileHash = $_SESSION['google_drive_file_sha1'];
        
        $newSourceLang = $this->request->sourceLanguage;
        
        $renameSuccess = false;
        
        if($newSourceLang !== $originalSourceLang) {
        
            $originalCacheFileDir = $this->getCacheFileDir();

            $_SESSION['actualSourceLang'] = $newSourceLang;

            $newCacheFileDir = $this->getCacheFileDir();
        
            $renameDirSuccess = rename($originalCacheFileDir, $newCacheFileDir);

            $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . filter_input(INPUT_COOKIE, 'upload_session');

            $originalUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $originalSourceLang;
            $newUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $newSourceLang;

            $renameFileRefSuccess = rename($originalUploadRefFile, $newUploadRefFile);

            if($renameDirSuccess && $renameFileRefSuccess) {
                $ckSourceLang = filter_input(INPUT_COOKIE, 'sourceLang');

                if ( $ckSourceLang == null || $ckSourceLang === false || $ckSourceLang === "_EMPTY_" ) {
                    $ckSourceLang = '';
                }

                $newCookieVal = $newSourceLang . '||' . $ckSourceLang;

                setcookie( "sourceLang", $newCookieVal, time() + ( 86400 * 365 ), '/' );
            } else {
                Log::doLog('Error when moving cache file dir to ' . $newCacheFileDir);

                $_SESSION['actualSourceLang'] = $originalSourceLang;
            }
        }
        
        $response = array(
            "success" => ($renameDirSuccess && $renameFileRefSuccess)
        );
        
        echo json_encode($response);
    }
    
    protected function afterConstruct() {

    }
}