<?php

namespace CommandLineTasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateProjectTask extends Command {

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
                ->setName( 'project:create' )
                ->setDescription( 'Creates n copies of a project.' )
                ->setHelp( "This command allows you to create n copies of a project from a file." )
                ->addArgument( 'file_path', InputArgument::REQUIRED, 'Input file path' )
                ->addArgument( 'user_email', InputArgument::REQUIRED, 'Project\'s owner (email)' )
                ->addArgument( 'counter', InputArgument::OPTIONAL, 'Projects starting counter', 1 )
                ->addArgument( 'copies', InputArgument::OPTIONAL, 'Number of copies of the project', 50 );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {

        // IO Helper
        $io = new SymfonyStyle($input, $output);

        // arguments
        $filePath  = $input->getArgument( 'file_path' );
        $userEmail = $input->getArgument( 'user_email' );
        $counter   = $input->getArgument( 'counter' );
        $copies    = $input->getArgument( 'copies' );

        // source and target
        $source = $this->askForLanguage( 'Please enter the source language [default: English]: ', 'English', $input, $output );
        $target = $this->askForLanguage( 'Please enter the target language [default: Italian]: ', 'Italian', $input, $output );

        // check if file exists
        $this->checkFilePath( $filePath );

        // get user
        $user = $this->getUser( $userEmail );

        // get idTeam
        $idTeam = $user->getUserTeams()[0]->id;

        // get apiKey
        $apiKey = $this->getApiKey( $user->getUid() );

        // make MC curl
        $numberOfIteration = $counter + $copies - 1;
        for ( $i = $counter; $i <= $numberOfIteration; $i++ ) {
            $io->title("Creating project from file: " . $filePath  . " (".$i."/".$numberOfIteration.")");
            $project = $this->createProject( $i, $source, $target, $apiKey, $idTeam, $filePath );
            $response = $project['response'];

            if( $response['status'] === 'OK' and $response['message'] === 'Success' ){
                $io->success('Project was created with success. Analyze URL: ' . $response['analyze_url']);
            } else {
                $io->error('An error occurred during the creation of the project.');
            }
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
    private function checkFilePath( $filePath ) {
        if ( !file_exists( $filePath ) ) {
            throw new \Exception( 'File [' . $filePath . '] does not exists.' );
        }
    }

    /**
     * @param $userEmail
     *
     * @return \Users_UserStruct
     * @throws \Exception
     */
    private function getUser( $userEmail ) {
        $user = (new \Users_UserDao())->getByEmail($userEmail);

        if ( !$user ) {
            throw new \Exception( 'There is not a user associated with email [' . $userEmail . '].' );
        }

        if(!isset($user->getUserTeams()[0])){
            throw new \Exception( 'There is not a group associated with user [' . $user->fullName() . '].' );
        }

        return $user;
    }

    /**
     * @param $uid
     *
     * @return string
     * @throws \Exception
     */
    private function getApiKey( $uid ) {
        $apiKey = (new \ApiKeys_ApiKeyDao())->getByUid($uid);

        if ( !$apiKey ) {
            throw new \Exception( 'There is not a valid API Key associated with User ID [' . $uid . '].' );
        }

        return $apiKey->api_key.'-'.$apiKey->api_secret;
    }

    /**
     * @param $index
     * @param $source
     * @param $target
     * @param $apiKey
     * @param $idTeam
     * @param $filePath
     *
     * @return array
     */
    private function createProject( $index, $source, $target, $apiKey, $idTeam, $filePath ) {
        sleep( 2 );

        $fileName     = 'T' . sprintf( "%02d", $index ) . '-' . $target;
        $url          = \INIT::$CLI_HTTP_HOST . "/api/v1/new";
        $privateTmKey = 'new';

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
        curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'x-matecat-key: ' . $apiKey ] );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );

        $body = curl_exec( $ch );
        $error = curl_error( $ch );

        curl_close( $ch );

        return [
            'url'      => $url,
            'error'    => $error,
            'request'  => $postfields,
            'response' => json_decode( $body, true )
        ];
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
