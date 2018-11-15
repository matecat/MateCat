<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/09/16
 * Time: 13.32
 *
 */

namespace Engines\Traits;


use Engines_Results_MT;
use Engines_Results_MyMemory_Matches;

trait FormatResponse {

    protected function _composeResponseAsMatch( array $all_args, $decoded ){

        $mt_result = new Engines_Results_MT( $decoded );

        if ( $mt_result->error->code < 0 ) {
            $mt_result = $mt_result->get_as_array();
            $mt_result['error'] = (array)$mt_result['error'];
            return $mt_result;
        }

        $mt_match_res = new Engines_Results_MyMemory_Matches(
                $this->_resetSpecialStrings( $all_args[ 1 ][ 'text' ] ),
                $mt_result->translatedText,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        );

        return $mt_match_res->getMatches();

    }

}