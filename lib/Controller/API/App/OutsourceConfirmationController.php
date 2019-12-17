<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/17
 * Time: 19.48
 *
 */

namespace API\App;


use API\V2\Exceptions\AuthorizationError;
use Outsource\ConfirmationDao;
use Outsource\TranslatedConfirmationStruct;
use Translators\TranslatorsModel;

class OutsourceConfirmationController extends AbstractStatefulKleinController {

    public function confirm() {

        $params = filter_var_array( $this->request->params(), array(
                'id_job'   => FILTER_SANITIZE_STRING,
                'password' => FILTER_SANITIZE_STRING,
                'payload'  => FILTER_SANITIZE_STRING,
        ) );

        $payload = \SimpleJWT::getValidPayload( $params[ 'payload' ] );

        if ( $params[ 'id_job' ] != $payload[ 'id_job' ] || $params[ 'password' ] != $payload[ 'password' ] ) {
            throw new AuthorizationError( "Invalid Job" );
        }

        $jStruct = ( \Jobs_JobDao::getByIdAndPassword( $params[ 'id_job' ], $params[ 'password' ] ) );
        $translatorModel = new TranslatorsModel( $jStruct, 0 );
        $jTranslatorStruct = $translatorModel->getTranslator();

        $confirmationStruct = new TranslatedConfirmationStruct( $payload );

        if ( !empty( $jTranslatorStruct ) ) {
            $translatorModel->changeJobPassword();
            $confirmationStruct->password = $jStruct->password;
        }

        $confirmationStruct->create_date = date( DATE_ISO8601, time() );
        $cDao = new ConfirmationDao();
        $cDao->insertStruct( $confirmationStruct, [ 'ignore' => true, 'no_nulls' => true ] );
        $cDao->destroyConfirmationCache( $jStruct );

        $confirmationArray = $confirmationStruct->toArray();
        unset( $confirmationArray['id'] );
        $this->response->json( [ 'confirm' => $confirmationArray ] );

    }

}