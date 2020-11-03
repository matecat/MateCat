<?php

namespace CommandLineTasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Teams\TeamDao;

class CopyProjectTask extends Command {

    /**
     * @return array
     */
    private $enabledLanguages;

    /**
     * @var array
     */
    private $helpLanguageArray;

    /**
     * CopyProjectTask constructor.
     *
     * @param null $name
     */
    public function __construct( $name = null ) {
        parent::__construct( $name );
        $langHandler             = \Langs_Languages::getInstance();
        $this->enabledLanguages  = $langHandler->getEnabledLanguages( 'en' );
        $this->helpLanguageArray = $this->getHelpLanguageArray();
    }

    protected function configure() {
        $this
                ->setName( 'project:copy' )
                ->setDescription( 'Creates n copies of a project.' )
                ->setHelp( "This command allows you to create n copies of a project from a file." )
                ->addArgument( 'file_path', InputArgument::REQUIRED, 'Input file path' )
                ->addArgument( 'user_email', InputArgument::REQUIRED, 'Project\'s owner (email)' )
                ->addArgument( 'counter', InputArgument::OPTIONAL, 'Projects starting counter', 1 )
                ->addArgument( 'copies', InputArgument::OPTIONAL, 'Number of copies of the project', 50 );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {

        // arguments
        $filePath  = $input->getArgument( 'file_path' );
        $userEmail = $input->getArgument( 'user_email' );
        $counter   = $input->getArgument( 'counter' );
        $copies    = $input->getArgument( 'copies' );

        // source and target
        $source = $this->askForLanguage( 'Please enter the source language [default: English]: ', 'English', $input, $output );
        $target = $this->askForLanguage( 'Please enter the target language [default: Italian]: ', 'Italian', $input, $output );

        // validate file_path
        $this->validateFilePath( $filePath );

        // get user
        $this->validateUser( $user );

        // make MC curl
        $numberOfIteration = $counter + $copies - 1;
        for ( $i = $counter; $i <= $numberOfIteration; $i++ ) {
            $this->createProject( $i, $source, $target, $idTeam, $filePath );
        }
    }

    /**
     * @param $question
     * @param $default
     * @param $input
     * @param $output
     *
     * @return mixed|null
     * @throws \Exception
     */
    private function askForLanguage( $question, $default, $input, $output ) {
        $questionHelper = $this->getHelper( 'question' );

        $sourceQuestion = new Question( $question, $default );
        $sourceQuestion->setAutocompleterValues( $this->helpLanguageArray );
        $langName = $questionHelper->ask( $input, $output, $sourceQuestion );

        $langCode = $this->getLanguageCode( $langName );
        if ( !$langCode ) {
            throw new \Exception( $langName . ' is not a supported language.' );
        }

        return $langCode;
    }

    /**
     * @param $lang
     *
     * @return mixed|null
     */
    private function getLanguageCode( $lang ) {
        foreach ( $this->enabledLanguages as $languageArray ) {
            if ( $lang == $languageArray[ 'name' ] ) {
                return $languageArray[ 'code' ];
            }
        }

        return null;
    }

    /**
     * @return array
     */
    private function getHelpLanguageArray() {
        $languageNamesArray = [];

        foreach ( $this->enabledLanguages as $languageArray ) {
            $languageNamesArray[] = $languageArray[ 'name' ];
        }

        return $languageNamesArray;
    }

    /**
     * @param $filePath
     *
     * @throws \Exception
     */
    private function validateFilePath( $filePath ) {
        if ( !file_exists( $filePath ) ) {
            throw new \Exception( 'File [' . $filePath . '] does not exists.' );
        }
    }

    /**
     * @param $idTeam
     *
     * @throws \Exception
     */
    private function validateIdTeam( $idTeam ) {
        $team = ( new TeamDao() )->findById( $idTeam );

        if ( !$team ) {
            throw new \Exception( 'There is not a team with ID ' . $idTeam . '.' );
        }
    }

    /**
     * @param $index
     * @param $source
     * @param $target
     * @param $idTeam
     * @param $filePath
     *
     * @return mixed
     */
    private function createProject( $index, $source, $target, $idTeam, $filePath ) {
        sleep( 2 );

        $fileName     = 'T' . sprintf( "%02d", $index ) . '-' . $target;
        $url          = \INIT::$ROOT . "/api/new";
        $xMatecatKey  = 'ZTY4ODODkzMWOTNmMjOD-YzMzZjMTZiN2NWU2ZDYz';
        $privateTmKey = 'new';

        echo "Creating project: " . $filePath . "\n";

        //set postfields
        $postfields                         = [];
        $postfields[ "project_name" ]       = $fileName;
        $postfields[ "source_lang" ]        = $source;
        $postfields[ "target_lang" ]        = $target;
        $postfields[ "file" ]               = $this->makeCurlFile( $filePath );
        $postfields[ "private_tm_key" ]     = $privateTmKey;
        $postfields[ "get_public_matches" ] = 0;
        $postfields[ "mt_engine" ]          = 1;
        $postfields[ "lexiqa" ]             = 1;
        $postfields[ "id_team" ]            = $idTeam;
        $postfields[ "tag_projection" ]     = 0;
        $postfields[ "subject" ]            = "marketing_advertising_material_public_relations";
        $postfields[ "pretranslate_100" ]   = 0;

        $ch = curl_init();
        //set parameters
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postfields );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 600 ); //timeout in seconds
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'x-matecat-key: ' . $xMatecatKey ] );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

        //upload
        $body = curl_exec( $ch );

        print_r( $body );
        print_r( curl_error( $ch ) );

        //parse response
        //close connection
        curl_close( $ch );

        return json_decode( $body, true );
    }

    /**
     * @param $file
     *
     * @return \CURLFile
     */
    private function makeCurlFile( $file ) {
        $mime   = mime_content_type( $file );
        $info   = pathinfo( $file );
        $name   = $info[ 'basename' ];
        $output = new \CURLFile( $file, $mime, $name );

        return $output;
    }
}
