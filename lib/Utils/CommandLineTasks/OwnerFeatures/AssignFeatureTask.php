<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 12:10
 */

namespace CommandLineTasks\OwnerFeatures;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Organizations\OrganizationDao;

class AssignFeatureTask extends Command {

    protected function configure() {
        $this
            // the name of the command (the part after "bin/console")
            ->setName('features:assign')
            ->setDescription('Adds feature to a user or a team.')
            ->addArgument('user_or_organization_id', InputArgument::REQUIRED, 'Id of the user to assign the feature to. Default is user id.')
            ->addArgument('features', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'List of features to enable. Valid features are: ' . implode( ', ',  \Features::$VALID_CODES ) )
            ->addOption('org', 'o', null, 'Take the input id as a organization id' )
            ->addOption('force', 'f', null, 'Force the name of a feature even if validation fails' )
        ;
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        // validate the input array of features token
        $reference = $this->__getReference( $input ) ;

       if ( $input->getOption('force') )  {
           $valid_features = $input->getArgument('features') ;
       } else {
           $valid_features = $this->__validateFeatures( $input->getArgument('features') ) ;
       }

        $featureDao = new \OwnerFeatures_OwnerFeatureDao();

        foreach ( $valid_features as $feature ) {
            $values = array(
                    'uid'             => $reference[ 'uid' ],
                    'id_organization' => $reference[ 'id_organization' ],
                    'feature_code'    => $feature,
                    'options'         => '{}',
                    'enabled'         => true
            );

            $insert = $featureDao->create( new \OwnerFeatures_OwnerFeatureStruct( $values ) );
        }

    }

    private function __getReference( InputInterface $input ) {
        if ( $input->getOption('org') ) {
            $dao = new OrganizationDao();
            $team = $dao->findById( $input->getArgument('user_or_organization_id') );

            if ( !$team ) {
                throw  new \Exception('team not found');
            }
            return array( 'uid' => NULL, 'id_organization' => $team->id ) ;
        }

        else {

            $dao = new \Users_UserDao() ;
            $user = $dao->getByUid( $input->getArgument('user_or_organization_id')) ;

            if ( !$user ) {
                throw new \Exception('user not found' );
            }

            return array( 'uid' => $user->uid, 'id_organization' => NULL ) ;
        }
    }

    private function __validateFeatures( $features ) {
        foreach( $features as $k ) {
            if ( !in_array($k, \Features::$VALID_CODES ) )  {
                throw  new \Exception('feature ' . $k . ' is not valid' ) ;
            }
        }
        return $features ;
    }

}
