<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:14
 */

namespace CommandLineTasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Teams\TeamDao;


class CreateTeamTask extends Command {

    protected function configure() {
        $this
                // the name of the command (the part after "bin/console")
                ->setName( 'teams:create' )
                // the short description shown while running "php bin/console list"
                ->setDescription( 'Creates new team.' )
                // the full command description shown when running the command with
                // the "--help" option
                ->setHelp( "This command allows you to create teams..." )
                ->addArgument( 'user_email', InputArgument::REQUIRED )
                ->addArgument( 'name', InputArgument::REQUIRED )
                ->addArgument( 'type', InputArgument::OPTIONAL );

    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        $userDao = new \Users_UserDao();
        $user    = $userDao->getByEmail( $input->getArgument( 'user_email' ) );
        $type    = $input->getArgument( 'type' );

        $teamDao = new TeamDao();

        if ( !\Constants_Teams::isAllowedType( $type ) ) {
            $type = \Constants_Teams::PERSONAL;
            if ( $teamDao->getPersonalByUid( $user->uid ) ) {
                throw new InvalidArgumentException( "User already has the personal team" );
            }
        } else {
            $type = strtolower( $type );
        }

        $teamStruct = $teamDao->createUserTeam( $user, array(
                'type' => $type, 'name' => $input->getArgument( 'name' )
        ) );

        if ( $teamStruct ) {
            $output->write( " Team created with ID: " . $teamStruct->id, true );
        }

    }

}