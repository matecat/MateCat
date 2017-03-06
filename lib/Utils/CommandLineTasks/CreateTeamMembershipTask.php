<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:56
 */

namespace CommandLineTasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Teams\MembershipDao;
use Teams\MembershipStruct;
use Teams\TeamDao;

class CreateTeamMembershipTask extends Command
{

    protected function configure() {
        $this
        // the name of the command (the part after "bin/console")
        ->setName('teams:add-user')
            // the short description shown while running "php bin/console list"
            ->setDescription('Adds a member to the team.')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command allows you to create team memberhisps...")
            ->addArgument('team_id', InputArgument::REQUIRED)
            ->addArgument('user_email', InputArgument::REQUIRED)
            ->addOption('is_admin', 'a', null, 'Flag to make the member admin of the team')
        ;
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        // find the team, ensure the user is not already a member or the
        $teamDao = new TeamDao();
        $team = $teamDao->findById( $input->getArgument('team_id') );

        if ( !$team ) {
            throw new \Exception('team is not found');
        }

        $userDao = new \Users_UserDao() ;
        $user = $userDao->getByEmail( $input->getArgument('user_email' ) );

        if ( !$user ) {
            throw new \Exception('user is not found' );
        }

        $membershipDao = new MembershipDao() ;
        $membershipStruct = new MembershipStruct(array(
            'id_team' => $team->id,
            'uid' => $user->uid,
            'is_admin' => $input->getOption('is_admin')
        ));

        $membership = MembershipDao::insertStruct( $membershipStruct );

        $output->write(
            "membership created with id: " . $membership,  TRUE
        ) ;

    }

}