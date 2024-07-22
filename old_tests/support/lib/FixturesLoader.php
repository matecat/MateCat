<?php

use Features\TranslationVersions\Model\TranslationVersionDao;
use Features\TranslationVersions\Model\TranslationVersionStruct;
use Files\FilesJobDao;
use Files\FilesJobStruct;
use LQA\CategoryDao;
use LQA\CategoryStruct;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use LQA\ModelDao;
use LQA\ModelStruct;
use Symfony\Component\Yaml\Yaml;
use Users\MetadataDao;
use Users\MetadataStruct;

class FixturesLoader {

    protected $fixtures_map = [] ;

    public function __construct() {

    }

    public function loadFixtures() {
        $this->fixtures_map = [] ;

        $this->loadUsers();
        $this->loadUserMetadata();
        $this->loadQaModels();
        $this->loadProjects();
        $this->loadProjectMetadata();
        $this->loadFiles();
        $this->loadJobs();
        $this->loadJobMetadata();
        $this->loadFilesJob();
        $this->loadSegments();
        $this->loadSegmentTranslations();
        $this->loadSegmentTranslationVersions();
        $this->loadQaChunkRevies();
        $this->loadQaCategories();
    }

    public function getFixtures() {
        return $this->fixtures_map ;
    }

    protected function loadUsers() {
        $this->loadFromFile('users') ;

        foreach( $this->fixtures_map['users'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Users_UserStruct( $record ) ;
            Users_UserDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    protected function loadUserMetadata() {
        $this->loadFromFile('user_metadata') ;

        foreach( $this->fixtures_map['user_metadata'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new MetadataStruct( $record ) ;
            MetadataDao::insertStruct( $struct ) ;
        }
    }

    protected function loadQaCategories() {
        $this->loadFromFile('qa_categories') ;

        foreach( $this->fixtures_map['qa_categories'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new CategoryStruct( $record ) ;
            CategoryDao::insertStruct( $struct ) ;
        }
    }

    protected function loadSegments() {
        $this->loadFromFile('segments') ;

        foreach( $this->fixtures_map['segments'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Segments_SegmentStruct( $record ) ;
            Segments_SegmentDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    protected function loadSegmentTranslations() {
        $this->loadFromFile('segment_translations') ;

        foreach( $this->fixtures_map['segment_translations'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Translations_SegmentTranslationStruct( $record ) ;
            Translations_SegmentTranslationDao::insertStruct( $struct ) ;
        }
    }

    protected function loadSegmentTranslationVersions() {
        $this->loadFromFile('segment_translation_versions') ;

        foreach( $this->fixtures_map['segment_translation_versions'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new TranslationVersionStruct( $record ) ;
            $struct->creation_date = Utils::mysqlTimestamp( $record['creation_date'] ) ;
            $insert = TranslationVersionDao::insertStruct( $struct ) ;
        }
    }

    protected function loadQaChunkRevies() {
        $this->loadFromFile('qa_chunk_reviews') ;

        foreach( $this->fixtures_map['qa_chunk_reviews'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new ChunkReviewStruct( $record ) ;
            ChunkReviewDao::insertStruct( $struct ) ;
        }
    }

    public function loadFilesJob() {
        $this->loadFromFile('files_job') ;

        foreach( $this->fixtures_map['files_job'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new FilesJobStruct( $record ) ;
            FilesJobDao::insertStruct( $struct ) ;
        }
    }

    public function loadFiles() {
        $this->loadFromFile('files') ;

        foreach( $this->fixtures_map['files'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Files_FileStruct( $record ) ;
            Files_FileDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    public function loadQaModels() {
        $this->loadFromFile('qa_models') ;

        foreach( $this->fixtures_map['qa_models'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new ModelStruct( $record  ) ;
            ModelDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    public function loadJobs() {
        $this->loadFromFile('jobs') ;

        foreach( $this->fixtures_map['jobs'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Jobs_JobStruct( $record ) ;
            Jobs_JobDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    public function loadProjects() {
        $this->loadFromFile('projects') ;

        foreach( $this->fixtures_map['projects'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Projects_ProjectStruct( $record ) ;
            Projects_ProjectDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }


    public function loadProjectMetadata() {
        $this->loadFromFile('project_metadata') ;

        foreach( $this->fixtures_map['project_metadata'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new Projects_MetadataStruct( $record ) ;
            Projects_MetadataDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    public function loadJobMetadata() {
        $this->loadFromFile('job_metadata') ;

        foreach( $this->fixtures_map['job_metadata'] as $name => &$record ) {
            $this->replaceTokens( $record ) ;
            $struct = new \Jobs\MetadataStruct( $record ) ;
            \Jobs\MetadataDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    public function loadFromFile( $file ) {
        $data = Yaml::parse( file_get_contents( static::path( $file ) ) ) ;
        $this->saveIntoMap( $file, $data );
    }

    static function path( $file ) {
        return INIT::$ROOT . '/test/support/fixtures/' . $file . '.yml' ;
    }

    protected function replaceTokens( &$record ) {
        foreach( $record as $key => &$value ) {

            if ( $value === '@EVER_INCREMENT' ) {
                // generate non conflicting integers using microtime and keep the number small
                $maxInt = 2147483647 ;
                $record[ $key ] = ( round( microtime( true ) * 1000 ) - 1497097000000 ) % $maxInt ;
                Log::doJsonLog( [ $key, $record[ $key ] ] );
            }

            // Assign reference idenfiers
            if ( !empty( $value ) && is_string( $value ) && strpos( $value, '@REF:' ) === 0 ) {
                list( $_, $table, $name, $attr ) = explode(':', $value ) ;

                if (
                        !isset( $this->fixtures_map [ $table ] ) ||
                        !isset( $this->fixtures_map [ $table ] [ $name ] ) ||
                        !isset( $this->fixtures_map [ $table ] [ $name ] [ $attr ] )
                ) {
                    throw new Exception("Missing reference in fixtures: $value " ) ;
                }

                $record[ $key ] = $this->fixtures_map [ $table ] [ $name ] [ $attr ] ;
            }

            if ( !empty( $value ) && is_string( $value ) && strpos( $value, '@CONFIG:' ) === 0 ) {
                list( $_, $file, $namespace, $needle ) = explode(':', $value ) ;
                $file_content = parse_ini_file(INIT::$ROOT . "/inc/$file.ini", true );

                if ( false === $file_content ) {
                    throw new Exception("Missing configuration file in fixtures: $value " ) ;
                }

                $record[ $key ] = $file_content[ $namespace ][ $needle ]  ;
            }
        }

        return $record ;
    }

    private function saveIntoMap( $type, $data ) {

        if ( !isset( $this->fixtures_map[ $type ] ) ) {
            $this->fixtures_map[ $type ] = [] ;
        }

        foreach( $data as $key => $value ) {
            if ( isset( $this->fixtures_map[ $type ] [ $key ] ) ) {
                throw new Exception('fixture with same name already exists ' . $key );
            }

            $this->fixtures_map[ $type ] [ $key ] = $value;
        }
    }
}