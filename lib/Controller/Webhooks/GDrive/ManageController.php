<?php

namespace Webhooks\GDrive  ;

use Bootstrap ; 
use Log;
use API\V2\KleinController ;
use INIT ; 

class ManageController extends KleinController {
    public function listImportedFiles() {
        Bootstrap::sessionStart(); 
        
        $sourceLang = 'en-US';
        
        $ckSourceLang = filter_input(INPUT_COOKIE, 'sourceLang');
        
        if ( $ckSourceLang != null && $ckSourceLang != false ) {
            
            if( $ckSourceLang != "_EMPTY_" ) {
                $sourceLangHistory   = $ckSourceLang;
                $sourceLangAr        = explode( '||', urldecode( $sourceLangHistory ) );
                
                if(count( $sourceLangAr ) > 0) {
                    $sourceLang = $sourceLangAr[0];
                }
            }
        }
        
        $fileName = $_SESSION['pre_loaded_file'];
        $hash = $_SESSION['google_drive_file_sha1'];
                    
        $cacheTreeAr = array(
            'firstLevel'  => $hash{0} . $hash{1},
            'secondLevel' => $hash{2} . $hash{3},
            'thirdLevel'  => substr( $hash, 4 )
        );

        $cacheTree = implode(DIRECTORY_SEPARATOR, $cacheTreeAr);

        $path = INIT::$CACHE_REPOSITORY . DIRECTORY_SEPARATOR . $cacheTree . "|" . $sourceLang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;

        if(file_exists($path) !== false) {
            $fileSize = filesize($path);

            $response = array(
                'fileName' => $fileName,
                'fileSize' => $fileSize
            );

            echo json_encode($response);
        }
        
    }
    
    protected function afterConstruct() {

    }
}