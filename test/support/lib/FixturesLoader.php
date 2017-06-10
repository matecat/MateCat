<?php
use Symfony\Component\Yaml\Yaml;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/06/2017
 * Time: 10:58
 */
class FixturesLoader {
    protected $fixtures_map = [] ;

    public function __construct() {


    }

    public function loadFixtures() {
        $this->loadEngines();
        $this->loadProjects();
        $this->loadJobs();
    }

    public function loadJobs() {
        $this->loadFromFile('jobs') ;

        foreach( $this->fixtures_map['jobs'] as $name => $record ) {
            $record = $this->replaceTokens( $record ) ;
            $struct = new Jobs_JobStruct( $record ) ;
            Jobs_JobDao::insertStructWithAutoIncrements( $struct ) ;
        }
    }

    public function loadProjects() {
        $this->loadFromFile('projects') ;

        foreach( $this->fixtures_map['projects'] as $name => $record ) {
            $record = $this->replaceTokens( $record ) ;
            $struct = new Projects_ProjectStruct( $record ) ;
            Projects_ProjectDao::insertStructWithAutoIncrements( $struct ) ;
        }

    }

    public function loadEngines() {
        $this->loadFromFile('engines');

        foreach( $this->fixtures_map['engines'] as $name => $record ) {
            $struct = new EnginesModel_EngineStruct( $record ) ;
            EnginesModel_EngineDAO::insertStruct( $struct ) ;
        }

        $id = $this->fixtures_map[ 'engines' ][ 'engine0' ][ 'id' ] ;
        Database::obtain()->getConnection()->exec("UPDATE engines SET id = 0 WHERE id = $id ");

    }

    public function loadFromFile( $file ) {
        $data = Yaml::parse( file_get_contents( static::path( $file ) ) ) ;
        $this->saveIntoMap( $file, $data );
    }

    static function path( $file ) {
        return INIT::$ROOT . '/test/support/fixtures/' . $file . '.yml' ;
    }

    protected function replaceTokens( $record ) {
        foreach( $record as $key => $value ) {

            if ( $value == '@EVER_INCREMENT' ) {
                $record [ $key ] = round( microtime( true ) * 1000 ) - 1497097000000 ;
            }

            // Assign reference idenfiers
            if ( !empty( $value ) && is_string( $value ) && strpos( $value, '@REF:' ) === 0 ) {
                list( $_, $table, $name, $attr ) = explode(':', $value ) ;

                if (
                        !isset( $this->fixtures_map [ $table ] ) ||
                        !isset( $this->fixtures_map [ $table ] [ $name ] ) ||
                        !isset( $this->fixtures_map [ $table ] [ $name ] [ $attr ] )
                ) {
                    throw new Exception("Missig reference in fixtures: $value " ) ;
                }

                $record [ $key ] = $this->fixtures_map [ $table ] [ $name ] [ $attr ] ;
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

            $this->fixtures_map[ $type ] [ $key ] = $value ;

        }
    }
}