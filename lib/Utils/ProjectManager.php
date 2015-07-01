<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 22/10/13
 * Time: 17.25
 *
 */
include_once INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

class ProjectManager {

    /**
     * @var ArrayObject|RecursiveArrayObject
     */
    protected $projectStructure;

    protected $mysql_link;

    protected $tmxServiceWrapper;

    protected $checkTMX;
    /*
       flag used to indicate TMX check status: 
       0-not to check, or check passed
       1-still checking, but no useful TM for this project have been found, so far (no one matches this project langpair)
     */

    protected $langService;

    public function __construct( ArrayObject $projectStructure = null ) {

        if ( $projectStructure == null ) {
            $projectStructure = new RecursiveArrayObject(
                    array(
                            'id_project'           => null,
                            'create_date'          => date( "Y-m-d H:i:s" ),
                            'id_customer'          => null,
                            'user_ip'              => null,
                            'project_name'         => null,
                            'result'               => null,
                            'private_tm_key'       => 0,
                            'private_tm_user'      => null,
                            'private_tm_pass'      => null,
                            'uploadToken'          => null,
                            'array_files'          => array(), //list of file names
                            'file_id_list'         => array(),
                            'file_references'      => array(),
                            'source_language'      => null,
                            'target_language'      => null,
                            'job_subject'          => 'general',
                            'mt_engine'            => null,
                            'tms_engine'           => null,
                            'ppassword'            => null,
                            'array_jobs'           => array(
                                    'job_list'     => array(),
                                    'job_pass'     => array(),
                                    'job_segments' => array()
                            ),
                            'job_segments'         => array(), //array of job_id => array( min_seg, max_seg )
                            'segments'             => array(), //array of files_id => segmentsArray()
                            'translations'         => array(),
                            //one translation for every file because translations are files related
                            'query_translations'   => array(),
                            'status'               => Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
                            'job_to_split'         => null,
                            'job_to_split_pass'    => null,
                            'split_result'         => null,
                            'job_to_merge'         => null,
                            'lang_detect_files'    => array(),
                            'tm_keys'              => array(),
                            'userIsLogged'         => false,
                            'uid'                  => null,
                            'skip_lang_validation' => false,
                            'pretranslate_100'     => 0,
                            'dqf_key'              => null
                    ) );
        }

        $this->projectStructure = $projectStructure;

        //get the TMX management component from the factory
        $this->tmxServiceWrapper = new TMSService();

        $this->langService = Langs_Languages::getInstance();

        $this->checkTMX = 0;

        $this->dbHandler = Database::obtain();

    }

    public function getProjectStructure() {
        return $this->projectStructure;
    }


    public function createProject() {

        // project name sanitize
        $oldName                                  = $this->projectStructure[ 'project_name' ];
        $this->projectStructure[ 'project_name' ] = $this->_sanitizeName( $this->projectStructure[ 'project_name' ] );

        if ( $this->projectStructure[ 'project_name' ] == false ) {
            $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                    "code"    => -5,
                    "message" => "Invalid Project Name " . $oldName . ": it should only contain numbers and letters!"
            );

            return false;
        }

        // create project
        $this->projectStructure[ 'ppassword' ]   = $this->_generatePassword();
        $this->projectStructure[ 'user_ip' ]     = Utils::getRealIpAddr();
        $this->projectStructure[ 'id_customer' ] = 'translated_user';

        $this->projectStructure[ 'id_project' ] = insertProject( $this->projectStructure );


        //create user (Massidda 2013-01-24)
        //check if all the keys are valid MyMemory keys
        if ( !empty( $this->projectStructure[ 'private_tm_key' ] ) ) {

            foreach ( $this->projectStructure[ 'private_tm_key' ] as $i => $_tmKey ) {

                $this->tmxServiceWrapper->setTmKey( $_tmKey[ 'key' ] );

                try {

                    $keyExists = $this->tmxServiceWrapper->checkCorrectKey();

                    if ( !isset( $keyExists ) || $keyExists === false ) {
                        Log::doLog( __METHOD__ . " -> TM key is not valid." );
                        throw new Exception( "TM key is not valid.", -4 );
                    }

                } catch ( Exception $e ) {

                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => $e->getCode(), "message" => $e->getMessage()
                    );

                    return false;
                }

                //set the first key as primary
                $this->tmxServiceWrapper->setTmKey( $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ] );

            }


            //check if the MyMemory keys provided by the user are already associated to him.
            if ( $this->projectStructure[ 'userIsLogged' ] ) {

                $mkDao = new TmKeyManagement_MemoryKeyDao( $this->dbHandler );

                $searchMemoryKey      = new TmKeyManagement_MemoryKeyStruct();
                $searchMemoryKey->uid = $this->projectStructure[ 'uid' ];

                $userMemoryKeys = $mkDao->read( $searchMemoryKey );

                $userTmKeys             = array();
                $memoryKeysToBeInserted = array();

                //extract user tm keys
                foreach ( $userMemoryKeys as $_memoKey ) {
                    /**
                     * @var $_memoKey TmKeyManagement_MemoryKeyStruct
                     */

                    $userTmKeys[ ] = $_memoKey->tm_key->key;
                }

                foreach ( $this->projectStructure[ 'private_tm_key' ] as $_tmKey ) {

                    if ( !in_array( $_tmKey[ 'key' ], $userTmKeys ) ) {
                        $newMemoryKey   = new TmKeyManagement_MemoryKeyStruct();
                        $newTmKey       = new TmKeyManagement_TmKeyStruct();
                        $newTmKey->key  = $_tmKey[ 'key' ];
                        $newTmKey->tm   = true;
                        $newTmKey->glos = true;
                        //TODO: take this from input
                        $newTmKey->name = $_tmKey[ 'name' ];

                        $newMemoryKey->tm_key = $newTmKey;
                        $newMemoryKey->uid    = $this->projectStructure[ 'uid' ];

                        $memoryKeysToBeInserted[ ] = $newMemoryKey;
                    } else {
                        Log::doLog( 'skip insertion' );
                    }

                }
                try {
                    $mkDao->createList( $memoryKeysToBeInserted );
                } catch ( Exception $e ) {
                    Log::doLog( $e->getMessage() );

                    # Here we handle the error, displaying HTML, logging, ...
                    $output = "<pre>\n";
                    $output .= $e->getMessage() . "\n\t";
                    $output .= "</pre>";
                    Utils::sendErrMailReport( $output );

                }

            }


            //the base case is when the user clicks on "generate private TM" button:
            //a (user, pass, key) tuple is generated and can be inserted
            //if it comes with it's own key without querying the creation API, create a (key,key,key) user
            if ( empty( $this->projectStructure[ 'private_tm_user' ] ) ) {
                $this->projectStructure[ 'private_tm_user' ] = $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ];
                $this->projectStructure[ 'private_tm_pass' ] = $this->projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ];
            }

            insertTranslator( $this->projectStructure );

        }


        //sort files in order to process TMX first
        $sortedFiles = array();
        foreach ( $this->projectStructure[ 'array_files' ] as $fileName ) {
            if ( 'tmx' == pathinfo( $fileName, PATHINFO_EXTENSION ) ) {
                //found TMX, enable language checking routines
                $this->checkTMX = 1;
                array_unshift( $sortedFiles, $fileName );
            } else {
                array_push( $sortedFiles, $fileName );
            }

        }
        $this->projectStructure[ 'array_files' ] = $sortedFiles;
        unset( $sortedFiles );


        $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure[ 'uploadToken' ];

        //we are going to access the storage, get model object to manipulate it
        $fs = new FilesStorage();
        $linkFiles= $fs->getHashesFromDir( $uploadDir );

        foreach( $linkFiles[ 'zipHashes' ] as $zipHash ){

            $result = $fs->linkZipToProject(
                    $this->projectStructure['create_date'],
                    $zipHash,
                    $this->projectStructure['id_project']
            );

            if( !$result ){
                Log::doLog( "Failed to store the Zip file $zipHash - \n" );
                throw new Exception( "Failed to store the original Zip $zipHash ", -10 );
            }

        }

        /*
            loop through all input files to
            1)upload TMX
            2)convert, in case, non standard XLIFF files to a format that Matecat understands

            Note that XLIFF that don't need conversion are moved anyway as they are to cache in order not to alter the workflow
         */
        foreach ( $this->projectStructure[ 'array_files' ] as $fileName ) {

            //if TMX,
            if ( 'tmx' == pathinfo( $fileName, PATHINFO_EXTENSION ) ) {
                //load it into MyMemory; we'll check later on how it went
                $file            = new stdClass();
                $file->file_path = "$uploadDir/$fileName";
                $this->tmxServiceWrapper->setName( $fileName );
                $this->tmxServiceWrapper->setFile( array( $file ) );

                try {
                    $this->tmxServiceWrapper->addTmxInMyMemory();
                } catch ( Exception $e ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => $e->getCode(), "message" => $e->getMessage()
                    );

                    return false;
                }

                //in any case, skip the rest of the loop, go to the next file
                continue;
            }

            /*
               Conversion Enforce
               Checking Extension is no more sufficient, we want check content if this is an idiom xlf file type, conversion are enforced
               $enforcedConversion = true; //( if conversion is enabled )
             */
            $isAnXliffToConvert = $this->isConversionToEnforce( $fileName );

            //if it's one of the listed formats or conversion is not enabled in first place
            if ( !$isAnXliffToConvert ) {
                /*
                   filename is already an xliff and it's in upload directory
                   we have to make a cache package from it to avoid altering the original path
                 */
                //get file
                $filePathName = "$uploadDir/$fileName";

                //calculate hash + add the fileName, if i load 3 equal files with the same content
                // they will be squashed to the last one
                $sha1 = sha1( file_get_contents( $filePathName ) . $filePathName );

                //make a cache package (with work/ only, emtpy orig/)
                $fs->makeCachePackage( $sha1, $this->projectStructure[ 'source_language' ], false, $filePathName );

                //put reference to cache in upload dir to link cache to session
                $fs->linkSessionToCache( $sha1, $this->projectStructure[ 'source_language' ], $this->projectStructure[ 'uploadToken' ] );

                //add newly created link to list
                $linkFiles[ 'conversionHashes' ][ ] = $sha1 . "|" . $this->projectStructure[ 'source_language' ];

                unset( $sha1 );
            }
        }

        //now, upload dir contains only hash-links
        //we start copying files to "file" dir, inserting metadata in db and extracting segments
        foreach ( $linkFiles[ 'conversionHashes' ] as $linkFile ) {
            //converted file is inside cache directory
            //get hash from file name inside UUID dir
            $hashFile = basename( $linkFile );
            $hashFile = explode( '|', $hashFile );

            //use hash and lang to fetch file from package
            $xliffFilePathName = $fs->getXliffFromCache( $hashFile[ 0 ], $hashFile[ 1 ] );

            //get sha
            $sha1_original = $hashFile[ 0 ];

            //get original file name
            $originalFilePathName = $fs->getOriginalFromCache( $hashFile[ 0 ], $hashFile[ 1 ] );
            $fileName             = basename( $originalFilePathName );

            unset( $hashFile );

            if ( !file_exists( $xliffFilePathName ) ) {
                $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                        "code" => -6, "message" => "File not found on server after upload."
                );
            }

            try {

                $info = pathinfo( $xliffFilePathName );

                if ( !in_array( $info[ 'extension' ], array( 'xliff', 'sdlxliff', 'xlf' ) ) ) {
                    throw new Exception( "Failed to find Xliff - no segments found", -3 );
                }
                $mimeType = pathinfo( $fileName, PATHINFO_EXTENSION );

                $yearMonthPath = date_create( $this->projectStructure[ 'date_create' ] )->format( 'Ymd' );
                $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;
                $fid = insertFile( $this->projectStructure, $fileName, $mimeType, $fileDateSha1Path );

                //move the file in the right directory from the packages to the file dir
                $fs->moveFromCacheToFileDir( $fileDateSha1Path, $this->projectStructure[ 'source_language' ], $fid );

                $this->projectStructure[ 'file_id_list' ]->append( $fid );

                $this->_extractSegments( file_get_contents( $xliffFilePathName ), $fid );

            }
            catch ( Exception $e ) {

                if ( $e->getCode() == -1 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -1, "message" => "No text to translate in the file $fileName."
                    );
                    $fs->deleteHashFromUploadDir( $uploadDir, $linkFile );
                } elseif ( $e->getCode() == -2 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -7, "message" => "Failed to store segments in database for $fileName"
                    );
                } elseif ( $e->getCode() == -3 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code"    => -7,
                            "message" => "File $fileName not found. Failed to save XLIFF conversion on disk"
                    );
                } elseif ( $e->getCode() == -4 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -7, "message" => "Internal Error. Xliff Import: Error parsing. ( $fileName )"
                    );
                } elseif ( $e->getCode() == -11 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -7, "message" => "Failed to store reference files on disk. Permission denied"
                    );
                } elseif ( $e->getCode() == -12 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -7, "message" => "Failed to store reference files in database"
                    );
                } elseif ( $e->getCode() == -13 ) {
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -13, "message" => $e->getMessage()
                    );
                    Log::doLog( $e->getMessage() );
                    return null; // SEVERE EXCEPTION we can not write to disk!! Break project creation
                } else {
                    //mysql insert Blob Error
                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code" => -7, "message" => "Failed to create project. Database Error on $fileName. Please try again."
                    );
                }

                Log::doLog( $e->getMessage() );

            }
        }//end of hash-link loop

        //check if the files language equals the source language. If not, set an error message.
        if ( !$this->projectStructure[ 'skip_lang_validation' ] ) {
            $this->validateFilesLanguages();
        }

        /****************/
        //loop again through files to check to check for TMX loading
        foreach ( $this->projectStructure[ 'array_files' ] as $fileName ) {

            //if TMX,
            if ( 'tmx' == pathinfo( $fileName, PATHINFO_EXTENSION ) ) {

                $this->tmxServiceWrapper->setName( $fileName );

                $result = array();

                //is the TM loaded?
                //wait until current TMX is loaded
                while ( true ) {

                    try {

                        $result = $this->tmxServiceWrapper->tmxUploadStatus();

                        if ( $result[ 'completed' ] ) {

                            //"$fileName" has been loaded into MyMemory"
                            //exit the loop
                            break;

                        }

                        //"waiting for "$fileName" to be loaded into MyMemory"
                        sleep( 3 );

                    } catch ( Exception $e ) {

                        $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                                "code" => $e->getCode(), "message" => $e->getMessage()
                        );

                        Log::doLog( $e->getMessage() . "\n" . $e->getTraceAsString() );

                        //exit project creation
                        return false;

                    }

                }

                //once the language is loaded, check if language is compliant (unless something useful has already been found)
                if ( 1 == $this->checkTMX ) {

                    //get localized target languages of TM (in case it's a multilingual TM)
                    $tmTargets = explode( ';', $result[ 'data' ][ 'target_lang' ] );

                    //indicates if something has been found for current memory
                    $found = false;

                    //compare localized target languages array (in case it's a multilingual project) to the TM supplied
                    //if nothing matches, then the TM supplied can't have matches for this project

                    //create an empty var and add the source language too
                    $project_languages = array_merge( (array)$this->projectStructure[ 'target_language' ], (array)$this->projectStructure[ 'source_language' ] );
                    foreach ( $project_languages as $projectTarget ) {
                        if ( in_array( $this->langService->getLocalizedName( $projectTarget ), $tmTargets ) ) {
                            $found = true;
                            break;
                        }
                    }

                    //if this TM matches the project lagpair and something has been found
                    if ( $found and $result[ 'data' ][ 'source_lang' ] == $this->langService->getLocalizedName( $this->projectStructure[ 'source_language' ] ) ) {

                        //the TMX is good to go
                        $this->checkTMX = 0;

                    } elseif ( $found and $result[ 'data' ][ 'target_lang' ] == $this->langService->getLocalizedName( $this->projectStructure[ 'source_language' ] ) ) {

                        /*
                         * This means that the TMX has a srclang as specification in the header. Warn the user.
                         * Ex:
                         * <header creationtool="SDL Language Platform"
                         *      creationtoolversion="8.0"
                         *      datatype="rtf"
                         *      segtype="sentence"
                         *      adminlang="DE-DE"
                         *      srclang="DE-DE" />
                         */
                        $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                                "code"    => -16,
                                "message" => "The TMX you provided explicitly specifies {$result['data']['source_lang']} as source language. Check that the specified language source in the TMX file match the language source of your project or remove that specification in TMX file."
                        );

                        $this->checkTMX = 0;

                        Log::doLog( $this->projectStructure[ 'result' ] );
                    }

                }

            }

        }

        if ( 1 == $this->checkTMX ) {
            //this means that noone of uploaded TMX were usable for this project. Warn the user.
            $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                    "code"    => -16,
                    "message" => "The TMX did not contain any usable segment. Check that the languages in the TMX file match the languages of your project."
            );

            Log::doLog( $this->projectStructure[ 'result' ] );

            return false;
        }

        if ( !empty( $this->projectStructure[ 'result' ][ 'errors' ] ) ) {
            Log::doLog( "Project Creation Failed. Sent to Output all errors." );
            Log::doLog( $this->projectStructure[ 'result' ][ 'errors' ] );

            return false;
        }

        //Log::doLog( array_pop( array_chunk( $SegmentTranslations[$fid], 25, true ) ) );
        //create job

        if ( isset( $_SESSION[ 'cid' ] ) and !empty( $_SESSION[ 'cid' ] ) ) {
            $owner = $_SESSION[ 'cid' ];
        } else {
            $_SESSION[ '_anonym_pid' ] = $this->projectStructure[ 'id_project' ];
            //default user
            $owner = '';
        }


        $isEmptyProject = false;
        //Throws exception
        try {
            $this->_createJobs( $this->projectStructure, $owner );

            //FIXME for project with pre translation this query is not enough,
            //we need compare the number of segments with translations, but take an eye to the opensource

            $query_visible_segments = "SELECT count(*) as cattool_segments
				FROM segments WHERE id_file IN ( %s ) and show_in_cattool = 1";

            $string_file_list       = implode( ",", $this->projectStructure[ 'file_id_list' ]->getArrayCopy() );
            $query_visible_segments = sprintf( $query_visible_segments, $string_file_list );

            $rows = $this->dbHandler->fetch_array( $query_visible_segments );

            if ( !$rows ) {
                Log::doLog( "Segment Search: Failed Retrieve min_segment/max_segment for files ( $string_file_list ) - DB Error: " . var_export( $this->dbHandler->get_error(), true ) . " - \n" );
                throw new Exception( "Segment Search: Failed Retrieve min_segment/max_segment for job", -5 );
            }

            if ( $rows[ 0 ][ 'cattool_segments' ] == 0 ) {
                Log::doLog( "Segment Search: No segments in this project - \n" );
                $isEmptyProject = true;
            }

            foreach( $linkFiles[ 'zipHashes' ] as $zipHash ){

                $result = $fs->linkZipToProject(
                        $this->projectStructure['create_date'],
                        $zipHash,
                        $this->projectStructure['id_project']
                );

                if( !$result ){
                    Log::doLog( "Failed to store the original Zip $zipHash - \n" );
                    throw new Exception( "Failed to store the original Zip $zipHash ", -10 );
                }

            }


        } catch ( Exception $ex ) {
            $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                    "code" => -9, "message" => "Fail to create Job. ( {$ex->getMessage()} )"
            );

            return false;
        }

        try {

            Utils::deleteDir( $uploadDir );
            if ( is_dir( $uploadDir . '_converted' ) ) {
                Utils::deleteDir( $uploadDir . '_converted' );
            }

        } catch ( Exception $e ) {

            $output = "<pre>\n";
            $output .= " - Exception: " . print_r( $e->getMessage(), true ) . "\n";
            $output .= " - REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
            $output .= " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $output .= " - Trace: \n" . print_r( $e->getTraceAsString(), true ) . "\n";
            $output .= "\n\t";
            $output .= "Aborting...\n";
            $output .= "</pre>";

            Log::doLog( $output );

            Utils::sendErrMailReport( $output, $e->getMessage() );

        }

        $this->projectStructure[ 'status' ] = ( INIT::$VOLUME_ANALYSIS_ENABLED ) ? Constants_ProjectStatus::STATUS_NEW : Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE;
        if ( $isEmptyProject ) {
            $this->projectStructure[ 'status' ] = Constants_ProjectStatus::STATUS_EMPTY;
        }


        $this->projectStructure[ 'result' ][ 'code' ]            = 1;
        $this->projectStructure[ 'result' ][ 'data' ]            = "OK";
        $this->projectStructure[ 'result' ][ 'ppassword' ]       = $this->projectStructure[ 'ppassword' ];
        $this->projectStructure[ 'result' ][ 'password' ]        = $this->projectStructure[ 'array_jobs' ][ 'job_pass' ];
        $this->projectStructure[ 'result' ][ 'id_job' ]          = $this->projectStructure[ 'array_jobs' ][ 'job_list' ];
        $this->projectStructure[ 'result' ][ 'job_segments' ]    = $this->projectStructure[ 'array_jobs' ][ 'job_segments' ];
        $this->projectStructure[ 'result' ][ 'id_project' ]      = $this->projectStructure[ 'id_project' ];
        $this->projectStructure[ 'result' ][ 'project_name' ]    = $this->projectStructure[ 'project_name' ];
        $this->projectStructure[ 'result' ][ 'source_language' ] = $this->projectStructure[ 'source_language' ];
        $this->projectStructure[ 'result' ][ 'target_language' ] = $this->projectStructure[ 'target_language' ];
        $this->projectStructure[ 'result' ][ 'status' ]          = $this->projectStructure[ 'status' ];
        $this->projectStructure[ 'result' ][ 'lang_detect' ]     = $this->projectStructure[ 'lang_detect_files' ];


        $query_project_summary = "
            SELECT
                 COUNT( s.id ) AS project_segments,
                 SUM( IF( IFNULL( st.eq_word_count, -1 ) = -1, s.raw_word_count, st.eq_word_count ) ) AS project_raw_wordcount
            FROM segments s
            INNER JOIN files_job fj ON fj.id_file = s.id_file
            INNER JOIN jobs j ON j.id= fj.id_job
            LEFT JOIN segment_translations st ON s.id = st.id_segment
            WHERE j.id_project = %u
        ";

        $query_project_summary = sprintf( $query_project_summary, $this->projectStructure[ 'id_project' ] );

        $project_summary = $this->dbHandler->fetch_array( $query_project_summary );

        $update_project_count = "
            UPDATE projects
              SET
                standard_analysis_wc = %.2F,
                status_analysis = '%s'
            WHERE id = %u
        ";

        $update_project_count = sprintf(
                $update_project_count,
                $project_summary[ 0 ][ 'project_raw_wordcount' ],
                $this->projectStructure[ 'status' ],
                $this->projectStructure[ 'id_project' ]
        );

        $this->dbHandler->query( $update_project_count );
//        Log::doLog( $this->projectStructure );
        //create Project into DQF queue
        if ( INIT::$DQF_ENABLED && !empty($this->projectStructure[ 'dqf_key' ]) ) {

            $dqfProjectStruct                  = DQF_DqfProjectStruct::getStruct();
            $dqfProjectStruct->api_key         = $this->projectStructure[ 'dqf_key' ];
            $dqfProjectStruct->project_id      = $this->projectStructure[ 'id_project' ];
            $dqfProjectStruct->name            = $this->projectStructure[ 'project_name' ];
            $dqfProjectStruct->source_language = $this->projectStructure[ 'source_language' ];

            $dqfQueue = new Analysis_DqfQueueHandler();

            try {
                $dqfQueue->createProject( $dqfProjectStruct );

                //for each job, push a task into AMQ's DQF queue
                foreach ( $this->projectStructure[ 'array_jobs' ][ 'job_list' ] as $i => $jobID ) {
                    /**
                     * @var $dqfTaskStruct DQF_DqfTaskStruct
                     */
                    $dqfTaskStruct                  = DQF_DqfTaskStruct::getStruct();
                    $dqfTaskStruct->api_key         = $this->projectStructure[ 'dqf_key' ];
                    $dqfTaskStruct->project_id      = $this->projectStructure[ 'id_project' ];
                    $dqfTaskStruct->task_id         = $jobID;
                    $dqfTaskStruct->target_language = $this->projectStructure[ 'target_language' ][ $i ];
                    $dqfTaskStruct->file_name       = uniqid('',true) . $this->projectStructure[ 'project_name' ];

                    $dqfQueue->createTask( $dqfTaskStruct );

                }
            } catch ( Exception $exn ) {
                $output = __METHOD__ . " (code " . $exn->getCode() . " ) - " . $exn->getMessage();
                Log::doLog( $output );

                Utils::sendErrMailReport( $output, $exn->getMessage() );
            }


        }


    }

    protected function _createJobs( ArrayObject $projectStructure, $owner ) {

        foreach ( $projectStructure[ 'target_language' ] as $target ) {

            //shorten languages and get payable rates
            $shortSourceLang = substr( $projectStructure[ 'source_language' ], 0, 2 );
            $shortTargetLang = substr( $target, 0, 2 );

            //get payable rates
            $projectStructure[ 'payable_rates' ] = Analysis_PayableRates::getPayableRates( $shortSourceLang, $shortTargetLang );

            $query_min_max = "SELECT MIN( id ) AS job_first_segment , MAX( id ) AS job_last_segment
				FROM segments WHERE id_file IN ( %s )";

            $string_file_list    = implode( ",", $projectStructure[ 'file_id_list' ]->getArrayCopy() );
            $last_segments_query = sprintf( $query_min_max, $string_file_list );

            $rows = $this->dbHandler->fetch_array( $last_segments_query );

            if ( !$rows || count( $rows ) == 0 ) {
                Log::doLog( "Segment Search: Failed Retrieve min_segment/max_segment for files ( $string_file_list ) - DB Error: " . var_export( $this->dbHandler->get_error(), true ) . " - \n" );
                throw new Exception( "Files not found.", -5 );
            }

            //IT IS EVERY TIME ONLY A LINE!! don't worry about a cycle
            $job_segments = $rows[ 0 ];

            $password = $this->_generatePassword();

            $tm_key = array();

            if ( !empty( $projectStructure[ 'private_tm_key' ] ) ) {
                foreach ( $projectStructure[ 'private_tm_key' ] as $tmKeyObj ) {
                    $newTmKey = TmKeyManagement_TmKeyManagement::getTmKeyStructure();

                    $newTmKey->tm    = true;
                    $newTmKey->glos  = true;
                    $newTmKey->owner = true;
                    $newTmKey->name  = $tmKeyObj[ 'name' ];
                    $newTmKey->key   = $tmKeyObj[ 'key' ];
                    $newTmKey->r     = $tmKeyObj[ 'r' ];
                    $newTmKey->w     = $tmKeyObj[ 'w' ];

                    $tm_key[ ] = $newTmKey;
                }

                //TODO: change this: private tm key field should not be used
                //set private tm key string to the first tm_key for retro-compatibility

                Log::doLog( $projectStructure[ 'private_tm_key' ] );
                
            }

            $projectStructure[ 'tm_keys' ] = json_encode( $tm_key );

            $jid = insertJob( $projectStructure, $password, $target, $job_segments, $owner );

            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $jid );
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $password );
            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $jid . "-" . $password, $job_segments );

            foreach ( $projectStructure[ 'file_id_list' ] as $fid ) {

                try {
                    //prepare pre-translated segments queries
                    if ( !empty( $projectStructure[ 'translations' ] ) ) {
                        $this->_insertPreTranslations( $jid );
                    }
                } catch ( Exception $e ) {
                    $msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export( $e->getMessage(), true );
                    Utils::sendErrMailReport( $msg );
                }

                insertFilesJob( $jid, $fid );

            }

        }

    }

    /**
     * This function executes a language detection call to mymemory for an array of segments,
     * located in projectStructure
     */
    private function validateFilesLanguages() {
        /**
         * @var $filesSegments RecursiveArrayObject
         */
        $filesSegments = $this->projectStructure[ 'segments' ];

        /**
         * This is a map <file_name, check_result>, where check_result is one
         * of these status strings:<br/>
         * - ok         --> the language detected for this file is the same of source language<br/>
         * - warning    --> the language detected for this file is different from the source language
         *
         * @var $filename2SourceLangCheck array
         */
        $filename2SourceLangCheck = array();

        //istantiate MyMemory analyzer and detect languages for each file uploaded
        $mma = Engine::getInstance( 1 /* MyMemory */ );
        $res = $mma->detectLanguage( $filesSegments, $this->projectStructure[ 'lang_detect_files' ] );

        //for each language detected, check if it's not equal to the source language
        $langsDetected = $res[ 'responseData' ][ 'translatedText' ];
        Log::dolog( __CLASS__ . " - DETECT LANG RES:", $langsDetected );
        if ( $res !== null &&
                is_array( $langsDetected ) &&
                count( $langsDetected ) == count( $this->projectStructure[ 'array_files' ] )
        ) {

            $counter = 0;
            foreach ( $langsDetected as $fileLang ) {

                $currFileName = $this->projectStructure[ 'array_files' ][ $counter ];

                //get language code
                if ( strpos( $fileLang, "-" ) === false ) {
                    //PHP Strict: Only variables should be passed by reference
                    $_tmp       = explode( "-", $this->projectStructure[ 'source_language' ] );
                    $sourceLang = array_shift( $_tmp );
                } else {
                    $sourceLang = $this->projectStructure[ 'source_language' ];
                }

                Log::dolog( __CLASS__ . " - DETECT LANG COMPARISON:", "$fileLang@@$sourceLang" );
                //get extended language name using google language code
                $languageExtendedName = Langs_GoogleLanguageMapper::getLanguageCode( $fileLang );

                //get extended language name using standard language code
                $langClass                  = Langs_Languages::getInstance();
                $sourceLanguageExtendedName = strtolower( $langClass->getLocalizedName( $sourceLang ) );
                Log::dolog( __CLASS__ . " - DETECT LANG NAME COMPARISON:", "$sourceLanguageExtendedName@@$languageExtendedName" );

                //Check job's detected language. In case of undefined language, mark it as valid
                if ( $fileLang !== 'und' &&
                        $fileLang != $sourceLang &&
                        $sourceLanguageExtendedName != $languageExtendedName
                ) {

                    $filename2SourceLangCheck[ $currFileName ] = 'warning';

                    $languageExtendedName = ucfirst( $languageExtendedName );

                    $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                            "code"    => -17,
                            "message" => "The source language you selected seems " .
                                    "to be different from the source language in \"$currFileName\". Please check."
                    );
                } else {
                    $filename2SourceLangCheck[ $currFileName ] = 'ok';
                }

                $counter++;
            }

            if ( in_array( "warning", array_values( $filename2SourceLangCheck ) ) ) {
                $this->projectStructure[ 'result' ][ 'lang_detect' ] = $filename2SourceLangCheck;
            }
        } else {
            //There are errors while parsing JSON.
            //Noop
        }
    }

    /**
     *
     * Build a job split structure, minimum split value are 2 chunks
     *
     * @param ArrayObject $projectStructure
     * @param int         $num_split
     * @param array       $requestedWordsPerSplit Matecat Equivalent Words ( Only valid for Pro Version )
     *
     * @return RecursiveArrayObject
     *
     * @throws Exception
     */
    public function getSplitData( ArrayObject $projectStructure, $num_split = 2, $requestedWordsPerSplit = array() ) {

        $num_split = (int)$num_split;

        if ( $num_split < 2 ) {
            throw new Exception( 'Minimum Chunk number for split is 2.', -2 );
        }

        if ( !empty( $requestedWordsPerSplit ) && count( $requestedWordsPerSplit ) != $num_split ) {
            throw new Exception( "Requested words per chunk and Number of chunks not consistent.", -3 );
        }

        if ( !empty( $requestedWordsPerSplit ) && !INIT::$VOLUME_ANALYSIS_ENABLED ) {
            throw new Exception( "Requested words per chunk available only for Matecat PRO version", -4 );
        }

        /**
         * Select all rows raw_word_count and eq_word_count
         * and their totals ( ROLLUP )
         * reserve also two columns for job_first_segment and job_last_segment
         *
         * +----------------+-------------------+---------+-------------------+------------------+
         * | raw_word_count | eq_word_count     | id      | job_first_segment | job_last_segment |
         * +----------------+-------------------+---------+-------------------+------------------+
         * |          26.00 |             22.10 | 2390662 |           2390418 |          2390665 |
         * |          30.00 |             25.50 | 2390663 |           2390418 |          2390665 |
         * |          48.00 |             40.80 | 2390664 |           2390418 |          2390665 |
         * |          45.00 |             38.25 | 2390665 |           2390418 |          2390665 |
         * |        3196.00 |           2697.25 |    NULL |           2390418 |          2390665 |  -- ROLLUP ROW
         * +----------------+-------------------+---------+-------------------+------------------+
         *
         */
        $query = "SELECT
            SUM( raw_word_count ) AS raw_word_count,
            SUM(eq_word_count) AS eq_word_count,
            job_first_segment, job_last_segment, s.id, s.show_in_cattool
                FROM segments s
                LEFT  JOIN segment_translations st ON st.id_segment = s.id
                INNER JOIN jobs j ON j.id = st.id_job
                WHERE s.id BETWEEN j.job_first_segment AND j.job_last_segment
                AND j.id = %u
                AND j.password = '%s'
                GROUP BY s.id WITH ROLLUP";

        $query = sprintf( $query,
                $projectStructure[ 'job_to_split' ],
                $this->dbHandler->escape( $projectStructure[ 'job_to_split_pass' ] )
        );

        $rows = $this->dbHandler->fetch_array( $query );

        if ( empty( $rows ) ) {
            throw new Exception( 'No segments found for job ' . $projectStructure[ 'job_to_split' ], -5 );
        }

        $row_totals = array_pop( $rows ); //get the last row ( ROLLUP )
        unset( $row_totals[ 'id' ] );

        if ( empty( $row_totals[ 'job_first_segment' ] ) || empty( $row_totals[ 'job_last_segment' ] ) ) {
            throw new Exception( 'Wrong job id or password. Job segment range not found.', -6 );
        }

        //if fast analysis with equivalent word count is present
        $count_type  = ( !empty( $row_totals[ 'eq_word_count' ] ) ? 'eq_word_count' : 'raw_word_count' );
        $total_words = $row_totals[ $count_type ];

        if ( empty( $requestedWordsPerSplit ) ) {
            /*
             * Simple Split with pretty equivalent number of words per chunk
             */
            $words_per_job = array_fill( 0, $num_split, round( $total_words / $num_split, 0 ) );
        } else {
            /*
             * User defined words per chunk, needs some checks and control structures
             */
            $words_per_job = $requestedWordsPerSplit;
        }

        $counter = array();
        $chunk   = 0;

        $reverse_count = array( 'eq_word_count' => 0, 'raw_word_count' => 0 );

        foreach ( $rows as $row ) {

            if ( !array_key_exists( $chunk, $counter ) ) {
                $counter[ $chunk ] = array(
                        'eq_word_count'       => 0,
                        'raw_word_count'      => 0,
                        'segment_start'       => $row[ 'id' ],
                        'segment_end'         => 0,
                        'last_opened_segment' => 0,
                );
            }

            $counter[ $chunk ][ 'eq_word_count' ] += $row[ 'eq_word_count' ];
            $counter[ $chunk ][ 'raw_word_count' ] += $row[ 'raw_word_count' ];
            $counter[ $chunk ][ 'segment_end' ] = $row[ 'id' ];

            //if last_opened segment is not set and if that segment can be showed in cattool
            //set that segment as the default last visited
            ( $counter[ $chunk ][ 'last_opened_segment' ] == 0 && $row[ 'show_in_cattool' ] == 1 ? $counter[ $chunk ][ 'last_opened_segment' ] = $row[ 'id' ] : null );

            //check for wanted words per job.
            //create a chunk when we reach the requested number of words
            //and we are below the requested number of splits.
            //in this manner, we add to the last chunk all rests
            if ( $counter[ $chunk ][ $count_type ] >= $words_per_job[ $chunk ] && $chunk < $num_split - 1 /* chunk is zero based */ ) {
                $counter[ $chunk ][ 'eq_word_count' ]  = (int)$counter[ $chunk ][ 'eq_word_count' ];
                $counter[ $chunk ][ 'raw_word_count' ] = (int)$counter[ $chunk ][ 'raw_word_count' ];

                $reverse_count[ 'eq_word_count' ] += (int)$counter[ $chunk ][ 'eq_word_count' ];
                $reverse_count[ 'raw_word_count' ] += (int)$counter[ $chunk ][ 'raw_word_count' ];

                $chunk++;
            }

        }

        if ( $total_words > $reverse_count[ $count_type ] ) {
            $counter[ $chunk ][ 'eq_word_count' ]  = round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
            $counter[ $chunk ][ 'raw_word_count' ] = round( $row_totals[ 'raw_word_count' ] - $reverse_count[ 'raw_word_count' ] );
        }

        if ( count( $counter ) < 2 ) {
            throw new Exception( 'The requested number of words for the first chunk is too large. I cannot create 2 chunks.', -7 );
        }

        $result = array_merge( $row_totals, array( 'chunks' => $counter ) );

        $projectStructure[ 'split_result' ] = new ArrayObject( $result );

        return $projectStructure[ 'split_result' ];

    }

    /**
     * Do the split based on previous getSplitData analysis
     * It clone the original job in the right number of chunks and fill these rows with:
     * first/last segments of every chunk, last opened segment as first segment of new job
     * and the timestamp of creation
     *
     * @param ArrayObject $projectStructure
     *
     * @throws Exception
     */
    protected function _splitJob( ArrayObject $projectStructure ) {

        $query_job = "SELECT * FROM jobs WHERE id = %u AND password = '%s'";
        $query_job = sprintf( $query_job, $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );
        //$projectStructure[ 'job_to_split' ]

        $jobInfo = $this->dbHandler->fetch_array( $query_job );
        $jobInfo = $jobInfo[ 0 ];

        $data = array();
        $jobs = array();

        foreach ( $projectStructure[ 'split_result' ][ 'chunks' ] as $chunk => $contents ) {

            //            Log::doLog( $projectStructure['split_result']['chunks'] );

            //IF THIS IS NOT the original job, DELETE relevant fields
            if ( $contents[ 'segment_start' ] != $projectStructure[ 'split_result' ][ 'job_first_segment' ] ) {
                //next insert
                $jobInfo[ 'password' ]    = $this->_generatePassword();
                $jobInfo[ 'create_date' ] = date( 'Y-m-d H:i:s' );
            }

            $jobInfo[ 'last_opened_segment' ] = $contents[ 'last_opened_segment' ];
            $jobInfo[ 'job_first_segment' ]   = $contents[ 'segment_start' ];
            $jobInfo[ 'job_last_segment' ]    = $contents[ 'segment_end' ];

            $query = "INSERT INTO jobs ( " . implode( ", ", array_keys( $jobInfo ) ) . " )
                VALUES ( '" . implode( "', '", array_values( $jobInfo ) ) . "' )
                ON DUPLICATE KEY UPDATE
                last_opened_segment = {$jobInfo['last_opened_segment']},
                job_first_segment = '{$jobInfo['job_first_segment']}',
                job_last_segment = '{$jobInfo['job_last_segment']}'";


            //add here job id to list
            $projectStructure[ 'array_jobs' ][ 'job_list' ]->append( $projectStructure[ 'job_to_split' ] );
            //add here passwords to list
            $projectStructure[ 'array_jobs' ][ 'job_pass' ]->append( $jobInfo[ 'password' ] );

            $projectStructure[ 'array_jobs' ][ 'job_segments' ]->offsetSet( $projectStructure[ 'job_to_split' ] . "-" . $jobInfo[ 'password' ], new ArrayObject( array(
                    $contents[ 'segment_start' ], $contents[ 'segment_end' ]
            ) ) );

            $data[ ] = $query;
            $jobs[ ] = $jobInfo;
        }

        foreach ( $data as $position => $query ) {

            $res = $this->dbHandler->query( $query );

            $wCountManager = new WordCount_Counter();
            $wCountManager->initializeJobWordCount( $jobs[ $position ][ 'id' ], $jobs[ $position ][ 'password' ] );

            if ( $res !== true ) {
                $msg = "Failed to split job into " . count( $projectStructure[ 'split_result' ][ 'chunks' ] ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r( $data, true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
                Utils::sendErrMailReport( $msg );
                throw new Exception( 'Failed to insert job chunk, project damaged.', -8 );
            }
        }

    }

    /**
     * Apply new structure of job
     *
     * @param ArrayObject $projectStructure
     */
    public function applySplit( ArrayObject $projectStructure ) {
        $this->_splitJob( $projectStructure );
        Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();
    }

    public function mergeALL( ArrayObject $projectStructure, $renewPassword = false ) {

        $query_job = "SELECT *
            FROM jobs
            WHERE id = %u
            ORDER BY job_first_segment";

        $query_job = sprintf( $query_job, $projectStructure[ 'job_to_merge' ] );
        //$projectStructure[ 'job_to_split' ]

        $rows = $this->dbHandler->fetch_array( $query_job );

        //get the min and
        $first_job         = reset( $rows );
        $job_first_segment = $first_job[ 'job_first_segment' ];

        //the max segment from job list
        $last_job         = end( $rows );
        $job_last_segment = $last_job[ 'job_last_segment' ];

        //change values of first job
        $first_job[ 'job_first_segment' ] = $job_first_segment; // redundant
        $first_job[ 'job_last_segment' ]  = $job_last_segment;

        //merge TM keys: preserve only owner's keys
        $tm_keys = array();
        foreach ( $rows as $chunk_info ) {
            $tm_keys[ ] = $chunk_info[ 'tm_keys' ];
        }

        try {
            $owner_tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( $tm_keys );

            /**
             * @var $owner_key TmKeyManagement_TmKeyStruct
             */
            foreach ( $owner_tm_keys as $i => $owner_key ) {
                $owner_tm_keys[ $i ] = $owner_key->toArray();
            }

            $first_job[ 'tm_keys' ] = json_encode( $owner_tm_keys );
        } catch ( Exception $e ) {
            Log::doLog( __METHOD__ . " -> Merge Jobs error - TM key problem: " . $e->getMessage() );
        }

        $oldPassword = $first_job[ 'password' ];
        if ( $renewPassword ) {
            $first_job[ 'password' ] = self::_generatePassword();
        }

        $_data = array();
        foreach ( $first_job as $field => $value ) {
            $_data[ ] = "`$field`='$value'";
        }

        //----------------------------------------------------

        $queries = array();

        $queries[ ] = "UPDATE jobs SET " . implode( ", \n", $_data ) .
                " WHERE id = {$first_job['id']} AND password = '{$oldPassword}'"; //ose old password

        //delete all old jobs
        $queries[ ] = "DELETE FROM jobs WHERE id = {$first_job['id']} AND password != '{$first_job['password']}' "; //use new password


        foreach ( $queries as $query ) {
            $res = $this->dbHandler->query( $query );
            if ( $res !== true ) {
                $msg = "Failed to merge job  " . $rows[ 0 ][ 'id' ] . " from " . count( $rows ) . " chunks\n";
                $msg .= "Tried to perform SQL: \n" . print_r( $queries, true ) . " \n\n";
                $msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
                $msg .= "Original Status for rebuild job and project was: \n" . print_r( $rows, true ) . "\n";
                Utils::sendErrMailReport( $msg );
                throw new Exception( 'Failed to merge jobs, project damaged. Contact Matecat Support to rebuild project.', -8 );
            }
        }

        $wCountManager = new WordCount_Counter();
        $wCountManager->initializeJobWordCount( $first_job[ 'id' ], $first_job[ 'password' ] );

        Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();

    }

    /**
     * Extract sources and pre-translations from sdlxliff file and put them in Database
     *
     * @param $xliff_file_content
     * @param $fid
     *
     * @throws Exception
     */
    protected function _extractSegments( $xliff_file_content, $fid ) {

        //create Structure fro multiple files
        $this->projectStructure[ 'segments' ]->offsetSet( $fid, new ArrayObject( array() ) );

        $xliff_obj = new Xliff_Parser();
        $xliff     = $xliff_obj->Xliff2Array( $xliff_file_content );

        // Checking that parsing went well
        if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
            Log::doLog( "Xliff Import: Error parsing. " . join( "\n", $xliff[ 'parser-errors' ] ) );
            throw new Exception( "Xliff Import: Error parsing. Check Log file.", -4 );
        }

        //needed to check if a file has only one segment
        //for correctness: we could have more tag files in the xliff
        $fileCounter_Show_In_Cattool = 0;

        // Creating the Query
        foreach ( $xliff[ 'files' ] as $xliff_file ) {

            if ( !array_key_exists( 'trans-units', $xliff_file ) ) {
                continue;
            }

            //extract internal reference base64 files and store their index in $this->projectStructure
            $this->_extractFileReferences( $fid, $xliff_file );

            foreach ( $xliff_file[ 'trans-units' ] as $xliff_trans_unit ) {

                //initialize flag
                $show_in_cattool = 1;

                if ( !isset( $xliff_trans_unit[ 'attr' ][ 'translate' ] ) ) {
                    $xliff_trans_unit[ 'attr' ][ 'translate' ] = 'yes';
                }

                if ( $xliff_trans_unit[ 'attr' ][ 'translate' ] == "no" ) {
                    //No segments to translate
                    //don't increment global counter '$fileCounter_Show_In_Cattool'
                    $show_in_cattool = 0;
                } else {

                    // If the XLIFF is already segmented (has <seg-source>)
                    if ( isset( $xliff_trans_unit[ 'seg-source' ] ) ) {

                        foreach ( $xliff_trans_unit[ 'seg-source' ] as $position => $seg_source ) {

                            $tempSeg = strip_tags( $seg_source[ 'raw-content' ] );
                            $tempSeg = preg_replace( '#\p{P}+#u', "", $tempSeg );
                            $tempSeg = trim( $tempSeg );

                            //init tags
                            $seg_source[ 'mrk-ext-prec-tags' ] = '';
                            $seg_source[ 'mrk-ext-succ-tags' ] = '';

                            if ( is_null( $tempSeg ) || $tempSeg === '' ) {
                                $show_in_cattool = 0;
                            } else {
                                $extract_external                  = $this->_strip_external( $seg_source[ 'raw-content' ] );
                                $seg_source[ 'mrk-ext-prec-tags' ] = $extract_external[ 'prec' ];
                                $seg_source[ 'mrk-ext-succ-tags' ] = $extract_external[ 'succ' ];
                                $seg_source[ 'raw-content' ]       = $extract_external[ 'seg' ];

                                if ( isset( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] ) ) {
                                    $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'seg-target' ][ $position ][ 'raw-content' ] );

                                    //we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                    //AND IF IT IS ONLY A CHAR? like "*" ?
                                    //we can't distinguish if it is translated or not
                                    //this means that we lose the tags id inside the target if different from source
                                    $src = strip_tags( html_entity_decode( $extract_external[ 'seg' ], ENT_QUOTES, 'UTF-8' ) );
                                    $trg = strip_tags( html_entity_decode( $target_extract_external[ 'seg' ], ENT_QUOTES, 'UTF-8' ) );

                                    if ( $src != $trg && !is_numeric( $src ) ) { //treat 0,1,2.. as translated content!

                                        $target_extract_external[ 'seg' ] = CatUtils::raw2DatabaseXliff( $target_extract_external[ 'seg' ] );
                                        $target                           = $this->dbHandler->escape( $target_extract_external[ 'seg' ] );

                                        //add an empty string to avoid casting to int: 0001 -> 1
                                        //useful for idiom internal xliff id
                                        $this->projectStructure[ 'translations' ]->offsetSet( "" . $xliff_trans_unit[ 'attr' ][ 'id' ], new ArrayObject( array( 2 => $target ) ) );

                                        //seg-source and target translation can have different mrk id
                                        //override the seg-source surrounding mrk-id with them of target
                                        $seg_source[ 'mrk-ext-prec-tags' ] = $target_extract_external[ 'prec' ];
                                        $seg_source[ 'mrk-ext-succ-tags' ] = $target_extract_external[ 'succ' ];

                                    }

                                }

                            }

                            //Log::doLog( $xliff_trans_unit ); die();

//                            $seg_source[ 'raw-content' ] = CatUtils::placeholdnbsp( $seg_source[ 'raw-content' ] );

                            $mid               = $this->dbHandler->escape( $seg_source[ 'mid' ] );
                            $ext_tags          = $this->dbHandler->escape( $seg_source[ 'ext-prec-tags' ] );
                            $source            = $this->dbHandler->escape( CatUtils::raw2DatabaseXliff( $seg_source[ 'raw-content' ] ) );
                            $source_hash       = $this->dbHandler->escape( md5( $seg_source[ 'raw-content' ] ) );
                            $ext_succ_tags     = $this->dbHandler->escape( $seg_source[ 'ext-succ-tags' ] );
                            $num_words         = CatUtils::segment_raw_wordcount( $seg_source[ 'raw-content' ], $xliff_file[ 'attr' ][ 'source-language' ] );
                            $trans_unit_id     = $this->dbHandler->escape( $xliff_trans_unit[ 'attr' ][ 'id' ] );
                            $mrk_ext_prec_tags = $this->dbHandler->escape( $seg_source[ 'mrk-ext-prec-tags' ] );
                            $mrk_ext_succ_tags = $this->dbHandler->escape( $seg_source[ 'mrk-ext-succ-tags' ] );

                            if ( $this->projectStructure[ 'file_references' ]->offsetExists( $fid ) ) {
                                $file_reference = (int)$this->projectStructure[ 'file_references' ][ $fid ];
                            } else {
                                $file_reference = 'NULL';
                            }

                            $this->projectStructure[ 'segments' ][ $fid ]->append( "('$trans_unit_id',$fid,$file_reference,'$source','$source_hash',$num_words,'$mid','$ext_tags','$ext_succ_tags',$show_in_cattool,'$mrk_ext_prec_tags','$mrk_ext_succ_tags')" );

                        }

                    } else {

                        $tempSeg = strip_tags( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
                        $tempSeg = preg_replace( '#\p{P}+#u', "", $tempSeg );
                        $tempSeg = trim( $tempSeg );
                        $prec_tags = null;
                        $succ_tags = null;
                        if ( is_null( $tempSeg ) || $tempSeg === '' ) { //|| $tempSeg == NBSPPLACEHOLDER ) { //@see CatUtils.php, ( DEFINE NBSPPLACEHOLDER ) don't show <x id=\"nbsp\"/>
                            $show_in_cattool = 0;
                        } else {
                            $extract_external                              = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
                            $prec_tags                                     = empty( $extract_external[ 'prec' ] ) ? null : $extract_external[ 'prec' ];
                            $succ_tags                                     = empty( $extract_external[ 'succ' ] ) ? null : $extract_external[ 'succ' ];
                            $xliff_trans_unit[ 'source' ][ 'raw-content' ] = $extract_external[ 'seg' ];

                            if ( isset( $xliff_trans_unit[ 'target' ][ 'raw-content' ] ) ) {

                                $target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

                                if ( $xliff_trans_unit[ 'source' ][ 'raw-content' ] != $target_extract_external[ 'seg' ] ) {

                                    $target = CatUtils::raw2DatabaseXliff( $target_extract_external[ 'seg' ] );
                                    $target = $this->dbHandler->escape( $target );

                                    //add an empty string to avoid casting to int: 0001 -> 1
                                    //useful for idiom internal xliff id
                                    $this->projectStructure[ 'translations' ]->offsetSet( "" . $xliff_trans_unit[ 'attr' ][ 'id' ], new ArrayObject( array( 2 => $target ) ) );

                                }

                            }
                        }

                        $source = $xliff_trans_unit[ 'source' ][ 'raw-content' ];

                        //we do the word count after the place-holding with <x id="nbsp"/>
                        //so &nbsp; are now not recognized as word and not counted as payable
                        $num_words = CatUtils::segment_raw_wordcount( $source, $xliff_file[ 'attr' ][ 'source-language' ] );

                        //applying escaping after raw count
                        $source      = $this->dbHandler->escape( CatUtils::raw2DatabaseXliff( $source ) );
                        $source_hash = $this->dbHandler->escape( md5( $source ) );

                        $trans_unit_id = $this->dbHandler->escape( $xliff_trans_unit[ 'attr' ][ 'id' ] );

                        if ( !is_null( $prec_tags ) ) {
                            $prec_tags = $this->dbHandler->escape( $prec_tags );
                        }
                        if ( !is_null( $succ_tags ) ) {
                            $succ_tags = $this->dbHandler->escape( $succ_tags );
                        }

                        if ( $this->projectStructure[ 'file_references' ]->offsetExists( $fid ) ) {
                            $file_reference = (int)$this->projectStructure[ 'file_references' ][ $fid ];
                        } else {
                            $file_reference = 'NULL';
                        }

                        $this->projectStructure[ 'segments' ][ $fid ]->append( "('$trans_unit_id',$fid, $file_reference,'$source','$source_hash',$num_words,NULL,'$prec_tags','$succ_tags',$show_in_cattool,NULL,NULL)" );

                    }
                }

                //increment the counter for not empty segments
                $fileCounter_Show_In_Cattool += $show_in_cattool;

            }
        }

        // *NOTE*: PHP>=5.3 throws UnexpectedValueException, but PHP 5.2 throws ErrorException
        //use generic
        if ( empty( $this->projectStructure[ 'segments' ][ $fid ] ) || $fileCounter_Show_In_Cattool == 0 ) {
            Log::doLog( "Segment import - no segments found\n" );
            throw new Exception( "Segment import - no segments found", -1 );
        }

        $baseQuery = "INSERT INTO segments ( internal_id, id_file, id_file_part, segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values ";

        Log::doLog( "Segments: Total Rows to insert: " . count( $this->projectStructure[ 'segments' ][ $fid ] ) );
        //split the query in to chunks if there are too much segments
        $this->projectStructure[ 'segments' ][ $fid ]->exchangeArray( array_chunk( $this->projectStructure[ 'segments' ][ $fid ]->getArrayCopy(), 200 ) );

        Log::doLog( "Segments: Total Queries to execute: " . count( $this->projectStructure[ 'segments' ][ $fid ] ) );


        foreach ( $this->projectStructure[ 'segments' ][ $fid ] as $i => $chunk ) {

            $this->dbHandler->query( $baseQuery . join( ",\n", $chunk ) );

            Log::doLog( "Segments: Executed Query " . ( $i + 1 ) );
            if ( $this->dbHandler->get_error_number() ) {
                Log::doLog( "Segment import - DB Error: " . mysql_error() . " - \n" );
                throw new Exception( "Segment import - DB Error: " . mysql_error() . " - $chunk", -2 );
            }

        }

        //Log::doLog( $this->projectStructure );

        if ( !empty( $this->projectStructure[ 'translations' ] ) ) {

            $last_segments_query = "SELECT id, internal_id, segment_hash from segments WHERE id_file = %u";
            $last_segments_query = sprintf( $last_segments_query, $fid );

            $_last_segments = $this->dbHandler->fetch_array( $last_segments_query );
            foreach ( $_last_segments as $row ) {

                if ( $this->projectStructure[ 'translations' ]->offsetExists( "" . $row[ 'internal_id' ] ) ) {
                    $this->projectStructure[ 'translations' ][ "" . $row[ 'internal_id' ] ]->offsetSet( 0, $row[ 'id' ] );
                    $this->projectStructure[ 'translations' ][ "" . $row[ 'internal_id' ] ]->offsetSet( 1, $row[ 'internal_id' ] );
                    //WARNING offset 2 are the target translations
                    $this->projectStructure[ 'translations' ][ "" . $row[ 'internal_id' ] ]->offsetSet( 3, $row[ 'segment_hash' ] );
                }

            }

        }
    }

    protected function _insertPreTranslations( $jid ) {

        //    Log::doLog( array_shift( array_chunk( $SegmentTranslations, 5, true ) ) );

        foreach ( $this->projectStructure[ 'translations' ] as $internal_id => $struct ) {

            if ( empty( $struct ) ) {
                //            Log::doLog( $internal_id . " : " . var_export( $struct, true ) );
                continue;
            }

            //id_segment, id_job, segment_hash, status, translation, translation_date, tm_analysis_status, locked
            $this->projectStructure[ 'query_translations' ]->append( "( '{$struct[0]}', $jid, '{$struct[3]}', 'TRANSLATED', '{$struct[2]}', NOW(), 'DONE', 1, 'ICE' )" );

        }

        // Executing the Query
        if ( !empty( $this->projectStructure[ 'query_translations' ] ) ) {

            $baseQuery = "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, translation, translation_date, tm_analysis_status, locked, match_type )
				values ";

            Log::doLog( "Pre-Translations: Total Rows to insert: " . count( $this->projectStructure[ 'query_translations' ] ) );
            //split the query in to chunks if there are too much segments
            $this->projectStructure[ 'query_translations' ]->exchangeArray( array_chunk( $this->projectStructure[ 'query_translations' ]->getArrayCopy(), 200 ) );

            Log::doLog( "Pre-Translations: Total Queries to execute: " . count( $this->projectStructure[ 'query_translations' ] ) );

//            Log::doLog( print_r( $this->projectStructure['translations'],true ) );

            foreach ( $this->projectStructure[ 'query_translations' ] as $i => $chunk ) {

                $this->dbHandler->query( $baseQuery . join( ",\n", $chunk ) );

                Log::doLog( "Pre-Translations: Executed Query " . ( $i + 1 ) );
                if ( $this->dbHandler->get_error_number() ) {
                    Log::doLog( "Segment import - DB Error: " . mysql_error() . " - \n" );
                    throw new Exception( "Translations Segment import - DB Error: " . mysql_error() . " - $chunk", -2 );
                }

            }

        }

        //clean translations and queries
        $this->projectStructure[ 'query_translations' ]->exchangeArray( array() );
        $this->projectStructure[ 'translations' ]->exchangeArray( array() );

    }

    protected function _strip_external( $a ) {
        $a               = str_replace( "\n", " NL ", $a );
        $pattern_x_start = '/^(\s*<x .*?\/>)(.*)/mis';
        $pattern_x_end   = '/(.*)(<x .*?\/>\s*)$/mis';

        //TODO:
        //What happens here? this regexp fails for
        //<g id="pt1497"><g id="pt1498"><x id="nbsp"/></g></g>
        //And this
        /* $pattern_g       = '/^(\s*<g [^>]*?>)(.*?)(<\/g>\s*)$/mis'; */
        //break document consistency in project Manager
        //where is the bug? there or in extract segments?

        $pattern_g = '/^(\s*<g [^>]*?>)([^<]*?)(<\/g>\s*)$/mis';
        $found     = false;
        $prec      = "";
        $succ      = "";

        $c = 0;

        do {
            $c += 1;
            $found = false;

            do {
                $r = preg_match_all( $pattern_x_start, $a, $res );
                if ( isset( $res[ 1 ][ 0 ] ) ) {
                    $prec .= $res[ 1 ][ 0 ];
                    $a     = $res[ 2 ][ 0 ];
                    $found = true;
                }
            } while ( isset( $res[ 1 ][ 0 ] ) );

            do {
                $r = preg_match_all( $pattern_x_end, $a, $res );
                if ( isset( $res[ 2 ][ 0 ] ) ) {
                    $succ  = $res[ 2 ][ 0 ] . $succ;
                    $a     = $res[ 1 ][ 0 ];
                    $found = true;
                }
            } while ( isset( $res[ 2 ][ 0 ] ) );

            do {
                $r = preg_match_all( $pattern_g, $a, $res );
                if ( isset( $res[ 1 ][ 0 ] ) ) {
                    $prec .= $res[ 1 ][ 0 ];
                    $succ  = $res[ 3 ][ 0 ] . $succ;
                    $a     = $res[ 2 ][ 0 ];
                    $found = true;
                }
            } while ( isset( $res[ 1 ][ 0 ] ) );

        } while ( $found );
        $prec = str_replace( " NL ", "\n", $prec );
        $succ = str_replace( " NL ", "\n", $succ );
        $a    = str_replace( " NL ", "\n", $a );
        $r    = array( 'prec' => $prec, 'seg' => $a, 'succ' => $succ );

        return $r;
    }

    public static function getExtensionFromMimeType( $mime_type ) {

        $reference = include( 'mime2extension.inc.php' );
        if ( array_key_exists( $mime_type, $reference ) ) {
            if ( array_key_exists( 'default', $reference[ $mime_type ] ) ) {
                return $reference[ $mime_type ][ 'default' ];
            }

            return $reference[ $mime_type ][ array_rand( $reference[ $mime_type ] ) ]; // rand :D
        }

        return null;

    }


    /**
     * Extract internal reference base64 files
     * and store their index in $this->projectStructure
     *
     * @param $project_file_id
     * @param $xliff_file_array
     *
     * @return null|int $file_reference_id
     *
     * @throws Exception
     */
    protected function _extractFileReferences( $project_file_id, $xliff_file_array ) {

        $fName = $this->_sanitizeName( $xliff_file_array[ 'attr' ][ 'original' ] );

        if ( $fName != false ) {
            $fName = $this->dbHandler->escape( $fName );
        } else {
            $fName = '';
        }

        $serialized_reference_meta     = array();
        $serialized_reference_binaries = array();

        /* Fix: PHP Warning:  Invalid argument supplied for foreach() */
        if ( !isset( $xliff_file_array[ 'reference' ] ) ) {
            return null;
        }

        foreach ( $xliff_file_array[ 'reference' ] as $pos => $ref ) {

            $found_ref = true;

            $_ext = self::getExtensionFromMimeType( $ref[ 'form-type' ] );
            if ( $_ext !== null ) {

                //insert in database if exists extension
                //and add the id_file_part to the segments insert statement

                $refName = $this->projectStructure[ 'id_project' ] . "-" . $pos . "-" . $fName . "." . $_ext;

                $serialized_reference_meta[ $pos ][ 'filename' ]   = $refName;
                $serialized_reference_meta[ $pos ][ 'mime_type' ]  = $this->dbHandler->escape( $ref[ 'form-type' ] );
                $serialized_reference_binaries[ $pos ][ 'base64' ] = $ref[ 'base64' ];

                if ( !is_dir( INIT::$REFERENCE_REPOSITORY ) ) {
                    mkdir( INIT::$REFERENCE_REPOSITORY, 0755 );
                }

                $wBytes = file_put_contents( INIT::$REFERENCE_REPOSITORY . "/$refName", base64_decode( $ref[ 'base64' ] ) );

                if ( !$wBytes ) {
                    throw new Exception ( "Failed to import references. $wBytes Bytes written.", -11 );
                }

            }

        }

        if ( isset( $found_ref ) && !empty( $serialized_reference_meta ) ) {

            $serialized_reference_meta     = serialize( $serialized_reference_meta );
            $serialized_reference_binaries = serialize( $serialized_reference_binaries );
            $queries                       = "INSERT INTO file_references ( id_project, id_file, part_filename, serialized_reference_meta, serialized_reference_binaries ) VALUES ( " . $this->projectStructure[ 'id_project' ] . ", $project_file_id, '$fName', '$serialized_reference_meta', '$serialized_reference_binaries' )";

            $this->dbHandler->query( $queries );

            $affected = $this->dbHandler->affected_rows;
            $last_id  = "SELECT LAST_INSERT_ID() as fpID";
            $result   = $this->dbHandler->query_first( $last_id );
            $result   = $result[ 0 ];

            //last Insert id
            $file_reference_id = $result[ 'fpID' ];
            $this->projectStructure[ 'file_references' ]->offsetSet( $project_file_id, $file_reference_id );

            if ( !$affected || !$file_reference_id ) {
                throw new Exception ( "Failed to import references.", -12 );
            }

            return $file_reference_id;
        }
    }

    protected function _sanitizeName( $nameString ) {

        $nameString = preg_replace( '/[^\p{L}0-9a-zA-Z_\.\-]/u', "_", $nameString );
        $nameString = preg_replace( '/[_]{2,}/', "_", $nameString );
        $nameString = str_replace( '_.', ".", $nameString );

        // project name validation
        $pattern = '/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/u';

        if ( !preg_match( $pattern, $nameString, $rr ) ) {
            return false;
        }

        return $nameString;

    }

    protected function _generatePassword( $length = 12 ) {
        return CatUtils::generate_password( $length );
    }

    private function sortByStrLenAsc( $a, $b ) {
        return strlen( $a ) >= strlen( $b );
    }

    private function isConversionToEnforce( $fileName ) {
        $isAConvertedFile = true;

        $fullPath = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure[ 'uploadToken' ] . DIRECTORY_SEPARATOR . $fileName;
        try {
            $isAConvertedFile = DetectProprietaryXliff::isConversionToEnforce( $fullPath );

            if ( -1 === $isAConvertedFile ) {
                $this->projectStructure[ 'result' ][ 'errors' ][ ] = array(
                        "code"    => -8,
                        "message" => "Proprietary xlf format detected. Not able to import this XLIFF file. ($fileName)"
                );
                setcookie( "upload_session", "", time() - 10000 );
            }

        } catch ( Exception $e ) {
            Log::doLog( $e->getMessage() );
        }

        return $isAConvertedFile;
    }

}
