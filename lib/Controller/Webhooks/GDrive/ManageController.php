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
        $sourceLang = $_SESSION['actualSourceLang'];
        
        $fileName = $_SESSION['pre_loaded_file'];
        $hash = $_SESSION['google_drive_file_sha1'];
                    
        $cacheTreeAr = array(
            'firstLevel'  => $hash{0} . $hash{1},
            'secondLevel' => $hash{2} . $hash{3},
            'thirdLevel'  => substr( $hash, 4 )
        );

        $cacheTree = implode(DIRECTORY_SEPARATOR, $cacheTreeAr);

        $path = INIT::$CACHE_REPOSITORY . DIRECTORY_SEPARATOR . $cacheTree . "|" . $sourceLang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;

        return $path;
    }
    
    protected function afterConstruct() {

    }
}