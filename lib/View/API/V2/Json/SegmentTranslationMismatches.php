<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 16.39
 *
 */

namespace API\V2\Json;


use CatUtils;

class SegmentTranslationMismatches {

    protected $data;
    protected $thereArePropagations;

    /**
     * SegmentTranslationMismatches constructor.
     *
     * from query: getWarning( id_job, password )
     *
     * @param array $Translation_mismatches
     * @param       $thereArePropagations
     */
    public function __construct( $Translation_mismatches, $thereArePropagations ) {
        $this->data                 = $Translation_mismatches;
        $this->thereArePropagations = $thereArePropagations;
    }

    /**
     * @return array
     */
    public function render() {

        $result = [
                'editable'       => [],
                'not_editable'   => [],
                'prop_available' => $this->thereArePropagations
        ];

        foreach ( $this->data as $position => $row ) {

            if ( $row[ 'editable' ] ) {
                $result[ 'editable' ][] = [
                        'translation' => CatUtils::rawxliff2view( $row[ 'translation' ] ),
                        'TOT'         => $row[ 'TOT' ],
                        'involved_id' => explode( ",", $row[ 'involved_id' ] )
                ];
            } else {
                $result[ 'not_editable' ][] = [
                        'translation' => CatUtils::rawxliff2view( $row[ 'translation' ] ),
                        'TOT'         => $row[ 'TOT' ],
                        'involved_id' => explode( ",", $row[ 'involved_id' ] )
                ];
            }

        }

        return $result;

    }

}