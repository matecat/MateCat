<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:14
 */

namespace CommandLineTasks;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Teams\TeamDao;
use Teams\TeamStruct;


class CreateTeamTask extends Command
{

    protected function configure() {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('teams:create')
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates new team.')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command allows you to create teams...")
            ->addArgument('user_email', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::REQUIRED)
        ;

    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        $userDao = new \Users_UserDao() ;
        $user  = $userDao->getByEmail( $input->getArgument('user_email') ) ;

        $teamDao = new TeamDao() ;

        $teamStruct = new TeamStruct(array(
            'name' => $input->getArgument('name'),
            'created_by' =>  $user->uid ,
            'created_at' => \Utils::mysqlTimestamp( time() )
        )) ;

        $result = TeamDao::insertStruct( $teamStruct  ) ;

        if ( $result ) {
            $output->write(" Team created with ID: " . $result , TRUE ) ;
        }

    }

}