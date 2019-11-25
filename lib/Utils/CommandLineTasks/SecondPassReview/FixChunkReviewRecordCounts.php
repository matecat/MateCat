<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/12/2016
 * Time: 10:47
 */

namespace CommandLineTasks\SecondPassReview;

use Chunks_ChunkStruct;
use LQA\ChunkReviewDao;
use RevisionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixChunkReviewRecordCounts extends Command
{
    protected function configure() {
        $this
                ->setName('revision:recount')
                ->setDescription('Fixes counts for second pass review chunk records')
                ->setHelp('')
                ->addArgument( 'id_job', InputArgument::REQUIRED, 'Job id')
                ->addArgument( 'password', InputArgument::REQUIRED, 'Job password' ) ;
    }

    public function execute( InputInterface $input, OutputInterface $output ) {

        $allChunkReviews = ( new ChunkReviewDao() )->findChunkReviews(
                new Chunks_ChunkStruct( [ 'id' => $input->getArgument('id_job'), 'password' => $input->getArgument('password') ] )
        ) ;

        $project = $allChunkReviews[0]->getChunk()->getProject() ;
        $revisionFactory = RevisionFactory::initFromProject( $project );

        foreach ( $allChunkReviews as $chunkReview ) {
            $model = $revisionFactory->getChunkReviewModel( $chunkReview ) ;
            $start = microtime(true) ;
            $model->recountAndUpdatePassFailResult( $project ) ;
            echo microtime(true ) - $start . "\n" ;
        }
    }

}