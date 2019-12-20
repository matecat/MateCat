<?php

namespace CommandLineTasks;

use FilesStorage\S3FilesStorage;
use Matecat\SimpleS3\Components\Cache\RedisCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class FindElementInS3CacheTask extends Command {

    /**
     * @var RedisCache
     */
    private $redisCache;

    /**
     * @var S3FilesStorage
     */
    private $fs;

    /**
     * @var HelperSet
     */
    private $question;

    public function __construct( $defaultName = null ) {
        parent::__construct();

        $this->fs         = new S3FilesStorage();
        $this->redisCache = new RedisCache( ( new \RedisHandler() )->getConnection() );
    }

    protected function configure() {
        $this
                ->setName( 's3:find-element-in-cache' )
                ->setDescription( 'Find an element stored in S3 from the cache.' )
                ->setHelp( "This command allows you to find an element stored in S3 from the local redis cache." )
                ->addArgument( 'hash', InputArgument::REQUIRED )
                ->addArgument( 'lang', InputArgument::REQUIRED );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {

        error_reporting( E_ERROR | E_WARNING | E_PARSE );

        // arguments
        $hash = $input->getArgument( 'hash' );
        $lang = $input->getArgument( 'lang' );

        // hashes
        $prefix         = $this->fs->getCachePackageHashFolder( $hash, $lang );
        $bucketName     = \INIT::$AWS_STORAGE_BASE_BUCKET;
        $origKeyPath    = $prefix . '/orig/';
        $workKeyPath    = $prefix . '/work/';
        $hashedOrigPath = hash( RedisCache::HASH_ALGORITHM, $bucketName . RedisCache::HASH_SAFE_SEPARATOR . $origKeyPath );
        $hashedWorkPath = hash( RedisCache::HASH_ALGORITHM, $bucketName . RedisCache::HASH_SAFE_SEPARATOR . $workKeyPath );

        // check if hashes already are in cache
        $origPathKeysArray = $this->redisCache->search( $bucketName, $origKeyPath );
        $workPathKeysArray = $this->redisCache->search( $bucketName, $workKeyPath );

        // render table
        $table = new Table( $output );
        $table
                ->setHeaders( [ 'Generated Path', 'Calculated hash', 'Key(s) in cache' ] )
                ->setRows( [
                        [ $origKeyPath, $hashedOrigPath, implode( '\\n', $origPathKeysArray ) ],
                        [ $workKeyPath, $hashedWorkPath, implode( '\\n', $workPathKeysArray ) ],
                ] );
        $table->render();

        // ask for displaying and then deleting the keys
        $this->askForDisplayingAndThenDeletingTheKeys( $origPathKeysArray, $origKeyPath, $input, $output, $bucketName );
        $this->askForDisplayingAndThenDeletingTheKeys( $workPathKeysArray, $workKeyPath, $input, $output, $bucketName );
    }

    /**
     * @param array           $keysInPath
     * @param string          $keyPath
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $bucketName
     *
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    private function askForDisplayingAndThenDeletingTheKeys( array $keysInPath, $keyPath, InputInterface $input, OutputInterface $output, $bucketName ) {
        if ( false === empty( $keysInPath ) ) {

            $io     = new SymfonyStyle( $input, $output );
            $helper = $this->getHelper( 'question' );

            // ask for deleting keys from cache
            $question = new ConfirmationQuestion( 'Do you want to delete key(s) present in <fg=yellow>' . $keyPath . '</> path from redis cache AND from S3?', false );
            if ( false === $helper->ask( $input, $output, $question ) ) {
                return;
            }

            $s3Client = S3FilesStorage::getStaticS3Client();

            // remove all keys from the path
            foreach ( $keysInPath as $key ) {

                $s3Client->deleteItem([
                    'bucket' => $bucketName,
                    'key' => $key,
                ]);

                $this->redisCache->remove( $bucketName, $key );
            }

            $io->success( $keyPath . ' was successfully emptied.' );
        }
    }
}