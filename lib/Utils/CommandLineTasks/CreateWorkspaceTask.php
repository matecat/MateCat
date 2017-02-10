<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 08/02/17
 * Time: 19.46
 *
 */

namespace CommandLineTasks;


use InvalidArgumentException;
use Organizations\MembershipDao;
use Organizations\OrganizationDao;
use Organizations\WorkspaceDao;
use Organizations\WorkspaceOptionsStruct;
use Organizations\WorkspaceStruct;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateWorkspaceTask extends Command {

    protected function configure() {
        $this
                // the name of the command (the part after "bin/console")
                ->setName( 'workspace:create' )
                // the short description shown while running "php bin/console list"
                ->setDescription( 'Creates new workspace into an existing organization.' )
                // the full command description shown when running the command with
                // the "--help" option
                ->setHelp( "This command allows you to create workspaces..." )
                ->addArgument( 'user_email', InputArgument::REQUIRED )
                ->addArgument( 'name', InputArgument::REQUIRED )
                ->addArgument( 'id_organization', InputArgument::REQUIRED )
                ->addArgument( 'options', InputArgument::OPTIONAL );

    }

    protected function execute( InputInterface $input, OutputInterface $output ) {

        $userDao = new \Users_UserDao();
        $user    = $userDao->getByEmail( $input->getArgument( 'user_email' ) );

        $wSpace                  = new WorkspaceStruct();
        $wSpace->id_organization = $input->getArgument( 'id_organization' );
        $wSpace->name            = $input->getArgument( 'name' );
        $wSpace->options         = new WorkspaceOptionsStruct( json_decode( $input->getArgument( 'options' ), true ) );

        $organization = ( new MembershipDao() )->findOrganizationByIdAndUser( $wSpace->id_organization, $user );
        if( empty( $organization ) ){
            throw new InvalidArgumentException( "User user does not belong to this organization: " . $wSpace->id_organization );
        }

        ( new WorkspaceDao() )->create( $wSpace );
        $output->write(" Workspace created with ID: " . $wSpace->id , TRUE ) ;
        $output->write(" It belongs to the organization ID: " . $wSpace->id_organization , TRUE ) ;

    }

}