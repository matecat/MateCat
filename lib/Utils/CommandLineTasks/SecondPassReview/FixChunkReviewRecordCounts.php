<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/12/2016
 * Time: 10:47
 */

namespace CommandLineTasks\SecondPassReview;

use Features\SecondPassReview\Model\ChunkReviewModel;
use LQA\ChunkReviewDao;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixChunkReviewRecordCounts extends Command
{
    protected function configure() {
        $this
                ->setName('2ndpass:recount')
                ->setDescription('Fixes counts for second pass review chunk records')
                ->setHelp('')
                ->addArgument( 'id_job', InputArgument::REQUIRED, 'Job id')
                ->addArgument( 'password', InputArgument::REQUIRED, 'Job password' ) ;
    }

    public function execute( InputInterface $input, OutputInterface $output ) {
        $allChunkReviews = ( new ChunkReviewDao() )->findAllChunkReviewsByChunkIds([
                [ $input->getArgument('id_job'), $input->getArgument('password') ]
        ]) ;

        foreach ( $allChunkReviews as $chunkReview ) {
            $model = new ChunkReviewModel($chunkReview);
            $model->recountAndUpdatePassFailResult() ;
        }
    }

}