<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/06/17
 * Time: 15.40
 *
 */

namespace API\App;

use API\App\Json\PeeGraphData;
use API\App\Json\PeeTableData;
use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\WhitelistAccessValidator;
use DataAccess\ShapelessConcreteStruct;
use DateTime;
use Exception;
use InvalidArgumentException;
use LanguageStats_LanguageStatsDAO;

class PeeData extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new WhitelistAccessValidator( $this ) );
    }

    public function getPeePlots(){

        $params = filter_var_array(
                $this->params,
                [
                        'month_interval' => [ 'filter'  => FILTER_CALLBACK, 'flags' => FILTER_FORCE_ARRAY,
                                              'options' => [ $this, 'parseDateTime' ]
                        ],
                        'sources'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FORCE_ARRAY ],
                        'targets'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FORCE_ARRAY ],
                        'fuzzy_band'     => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FORCE_ARRAY ],
                ] );

        if(
                empty( $params[ 'month_interval' ][0] ) ||
                empty( $params[ 'month_interval' ][1] ) ||
                empty( $params[ 'sources' ] ) ||
                empty( $params[ 'targets' ] )
        ){
            throw new InvalidArgumentException( "Bad Request.", 400 );
        }

        $lDao = new LanguageStats_LanguageStatsDAO();

        /**
         * @var DateTime $begin
         * @var DateTime $end
         */
        $begin = $params[ 'month_interval' ][0];
        $end = $params[ 'month_interval' ][1];

        $query = new ShapelessConcreteStruct([
                'date_start' => $begin->format( 'Y-m-d' ),
                'date_end'   => $end->format( 'Y-m-d' ),
                'sources'    => $params[ 'sources' ],
                'targets'    => $params[ 'targets' ],
                'fuzzy_band' => array_merge( [ 'MT_MyMemory' ], ( is_array( $params[ 'fuzzy_band' ] ) ? $params[ 'fuzzy_band' ] : []  ) )
        ]);

        $stats = $lDao->getGraphData( $query );

        $format = new PeeGraphData( $stats );
        $this->response->json( $format->render() );

    }

    protected static function parseDateTime( $string ){
        try {
            return new DateTime( $string );
        } catch ( Exception $e ){
            throw new InvalidArgumentException( "Bad Request.", 400 );
        }
    }

    public function getPeeTableData(){

        $params = filter_var_array(
                $this->params,[
                'date'      => [
                        'filter'  => FILTER_CALLBACK,
                        'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH,
                        'options' => [ $this, 'parseDateTime' ]
                ],
        ] );

        $languageStats = ( new LanguageStats_LanguageStatsDAO() )->getLanguageStats( $params[ 'date' ] );

        $format = new PeeTableData( $languageStats );
        $this->response->json( $format->render() );

    }

}