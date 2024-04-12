<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/04/17
 * Time: 19.29
 *
 */

namespace API\V2;


use API\V2\Exceptions\NotFoundException;
use API\V2\Json\JobTranslator;
use API\V2\Validators\JobPasswordValidator;
use API\V2\Validators\LoginValidator;
use InvalidArgumentException;
use Jobs_JobStruct;
use Outsource\ConfirmationDao;
use Translators\TranslatorsModel;

class JobsTranslatorsController extends KleinController {

    /**
     * @var Jobs_JobStruct
     * @see JobsTranslatorsController::afterConstruct method
     */
    protected $jStruct;

    /**
     * @var Jobs_JobStruct
     */
    private $chunk;

    public function add(){

        $this->params = filter_var_array( $this->params, [
                'email'         => [ 'filter' => FILTER_SANITIZE_EMAIL ],
                'delivery_date' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'timezone'      => [ 'filter' => FILTER_SANITIZE_NUMBER_FLOAT, 'flags' => FILTER_FLAG_ALLOW_FRACTION ],
                'id_job'        => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'      => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK
                ]
        ], true );

        if( empty( $this->params[ 'email' ] ) ){
            throw new InvalidArgumentException( "Wrong parameter :email ", 400 );
        }

        if($this->jStruct->wasDeleted()){
            throw new NotFoundException('No job found.');
        }

        $TranslatorsModel = new TranslatorsModel( $this->jStruct );
        $TranslatorsModel
                ->setUserInvite( $this->user )
                ->setDeliveryDate( $this->params[ 'delivery_date' ] )
                ->setJobOwnerTimezone( $this->params[ 'timezone' ] )
                ->setEmail( $this->params[ 'email' ] );

        $tStruct = $TranslatorsModel->update();
        $this->response->json(
                [
                        'job' => [
                                'id'         => $this->jStruct->id,
                                'password'   => $this->jStruct->password,
                                'translator' => ( new JobTranslator( $tStruct ) )->renderItem()
                        ]
                ]
        );

    }

    public function get(){

        $this->params = filter_var_array( $this->params, [
                'id_job'        => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'      => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK
                ]
        ], true );

        $confDao            = new ConfirmationDao();
        $confirmationStruct = $confDao->setCacheTTL( 60 * 60 )->getConfirmation( $this->jStruct );

        if ( !empty( $confirmationStruct ) ) {
            throw new InvalidArgumentException( "The Job is Outsourced.", 400 );
        }

        if($this->jStruct->wasDeleted()){
            throw new NotFoundException('No job found.');
        }

        //do not show outsourced translators
        $outsourceInfo = $this->jStruct->getOutsource();
        $tStruct       = $this->jStruct->getTranslator();
        $translator    = null;
        if ( empty( $outsourceInfo ) ) {
            $translator = ( !empty( $tStruct ) ? ( new JobTranslator( $tStruct ) )->renderItem() : null );
        }
        $this->response->json(
                [
                        'job' => [
                                'id'         => $this->jStruct->id,
                                'password'   => $this->jStruct->password,
                                'translator' => $translator
                        ]
                ]
        );

    }

    protected function afterConstruct() {
        $validJob = new JobPasswordValidator( $this );
        $this->jStruct = $validJob->getJob();
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( $validJob );
    }

    /**
     * To maintain compatibility with JobPasswordValidator
     * (line 36)
     *
     * @param Jobs_JobStruct $jobs_JobStruct
     */
    public function setChunk(\Jobs_JobStruct $jobs_JobStruct) {
        $this->chunk = $jobs_JobStruct;
    }
}