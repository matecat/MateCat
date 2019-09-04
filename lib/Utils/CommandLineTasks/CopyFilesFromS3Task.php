<?php

namespace CommandLineTasks;

use FilesStorage\FilesStorageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CopyFilesFromS3Task extends Command {

    /**
     * @var \DataAccess_AbstractDao
     */
    private $dao;

    protected function configure() {
        $this
                ->setName( 's3:copy-files' )
                ->setDescription( 'Copy files from S3 to filesystem.' )
                ->setHelp( "This command allows you to copy files from S3 to filesystem." )
                ->addArgument( 'type', InputArgument::REQUIRED )
                ->addArgument( 'id', InputArgument::REQUIRED );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {

        // SymfonyStyle
        $io = new SymfonyStyle( $input, $output );
        $io->title('Copying files from S3 to your filesystem');

        // arguments
        $type = $input->getArgument( 'type' );
        $id   = $input->getArgument( 'id' );

        // check type
        $allowedTypes = [ 'project', 'job' ];

        if ( false === in_array( $type, $allowedTypes ) ) {
            throw new \InvalidArgumentException( sprintf( '%s is not a valid type. [project, job] are allowed.', $type ) );
        }

        // set Dao
        $this->dao = $this->getDao( $type );

        // check if project or job exists
        if ( false === $this->checkIfProjectOrJobExists( $type, $id ) ) {
            throw new \InvalidArgumentException( sprintf( 'The %s with id %d cannot be found.', $type, $id ) );
        }

        $fs = FilesStorageFactory::create();

        // get job ids
        $jobIds = $this->getJobIds( $type, $id );

        $io->writeln('Starting copying files...');
        $io->newLine();

        $errorsCount = 0;

        if($type === 'project'){
            $io->writeln('The project with ID <fg=cyan>#' . $id . '</> has <fg=yellow>' . count($jobIds) . '</> jobs');
            $io->newLine();
        }

        foreach ( $jobIds as $jid ) {

            $files = $fs->getFilesForJob( $jid );

            $io->writeln('The job with ID <fg=cyan>#' . $jid . '</> has <fg=yellow>' . count($files) . '</> files');
            $io->newLine();

            foreach ($files as $file){

                $source = 's3://'. \INIT::$AWS_STORAGE_BASE_BUCKET . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $this->getFileCreationDate($file) . DIRECTORY_SEPARATOR . $file['id_file'];
                $destination = \INIT::$FILES_REPOSITORY . DIRECTORY_SEPARATOR . $this->getFileCreationDate($file) . DIRECTORY_SEPARATOR . $file['id_file'];

                $io->write('- Copying <fg=cyan>'.$source.'</> from S3 to <fg=yellow>' . $destination . '</> ... ');

                try {
                    // try to copy to fs
                    $fs->transferFiles($source, $destination);
                    $io->write('<fg=green>✓</>');
                } catch (\Exception $e){
                    $io->write('<fg=red>✗</>');
                    $errorsCount++;
                }

                $io->newLine();
            }
        }

        $io->newLine();
        $io->writeln('Finished with ' . $errorsCount . ' errors.');
        $io->newLine();
    }

    /**
     * @param $type
     *
     * @return \Jobs_JobDao|\Projects_ProjectDao
     */
    private function getDao( $type ) {
        if ( $type === 'project' ) {
            return new \Projects_ProjectDao();
        }

        return $this->dao = new \Jobs_JobDao();
    }

    /**
     * @param $type
     * @param $id
     *
     * @return bool
     */
    private function checkIfProjectOrJobExists( $type, $id ) {
        if ( $type === 'project' ) {
            return !empty( $this->dao->findById( $id ) );
        }

        return !empty( $this->dao->getById( $id ) );
    }

    /**
     * @param $type
     * @param $id
     *
     * @return array
     */
    private function getJobIds( $type, $id ) {
        $jobIds = [];

        if ( $type === 'project' ) {
            /** @var \Projects_ProjectDao $dao */
            $dao = $this->dao;
            foreach ( $dao->getJobIds( $id ) as $jid ) {
                $jobIds[] = $jid[ 'id' ];
            }

            return $jobIds;
        }

        $jobIds[] = $id;

        return $jobIds;
    }

    private function getFileCreationDate($file)
    {
        return explode(DIRECTORY_SEPARATOR, $file['sha1_original_file'])[0];
    }
}

//array(1) {
//    [0] =>
//  array(8) {
//        'id_file' =>
//    string(1) "4"
//    'filename' =>
//    string(8) "os12.odt"
//    'id_project' =>
//    string(1) "2"
//    'source' =>
//    string(5) "it-IT"
//    'mime_type' =>
//    string(3) "odt"
//    'sha1_original_file' =>
//    string(49) "20190729/bc76cbb7759327421d2948c8f9336665064aacbe"
//    'originalFilePath' =>
//    NULL
//    'xliffFilePath' =>
//    NULL
//  }
//}
