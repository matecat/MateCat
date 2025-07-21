<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 16.39
 *
 */

namespace View\API\V2\Json;

use Matecat\SubFiltering\MateCatFilter;

class SegmentTranslationMismatches {

    protected $data;
    protected $thereArePropagations;
    protected $featureSet;

    /**
     * SegmentTranslationMismatches constructor.
     * from query: getWarning( id_job, password )
     *
     * @param                  $Translation_mismatches
     * @param                  $thereArePropagations
     * @param \Model\FeaturesBase\FeatureSet|null $featureSet
     *
     * @throws \Exception
     */
    public function __construct( $Translation_mismatches, $thereArePropagations, \Model\FeaturesBase\FeatureSet $featureSet = null ) {
        $this->data                 = $Translation_mismatches;
        $this->thereArePropagations = $thereArePropagations;
        if ( $featureSet == null ) {
            $featureSet = new \Model\FeaturesBase\FeatureSet();
        }
        $this->featureSet = $featureSet;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function render() {

        $result = [
                'editable'       => [],
                'not_editable'   => [],
                'prop_available' => $this->thereArePropagations
        ];

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new \Model\FeaturesBase\FeatureSet();

        foreach ( $this->data as $position => $row ) {

            $Filter = MateCatFilter::getInstance( $featureSet, $row[ 'source' ], $row[ 'target' ], [] );

            if ( $row[ 'editable' ] ) {
                $result[ 'editable' ][] = [
                        'translation' => $Filter->fromLayer0ToLayer2( $row[ 'translation' ] ),
                        'TOT'         => $row[ 'TOT' ],
                        'involved_id' => explode( ",", $row[ 'involved_id' ] )
                ];
            } else {
                $result[ 'not_editable' ][] = [
                        'translation' => $Filter->fromLayer0ToLayer2( $row[ 'translation' ] ),
                        'TOT'         => $row[ 'TOT' ],
                        'involved_id' => explode( ",", $row[ 'involved_id' ] )
                ];
            }

        }

        return $result;

    }

}