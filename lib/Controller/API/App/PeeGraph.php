<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/06/17
 * Time: 15.40
 *
 */

namespace API\App;

use API\V2\KleinController;
use DataAccess\ShapelessConcreteStruct;
use DateTime;
use Exception;
use InvalidArgumentException;
use LanguageStats_LanguageStatsDAO;
use API\App\Json\PeeGraphData;

class PeeGraph extends KleinController {

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

}