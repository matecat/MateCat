<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/06/17
 * Time: 16.36
 *
 */

namespace API\App\Json;


use Analysis_PayableRates;
use DataAccess\ShapelessConcreteStruct;
use Langs_Languages;

class PeeTableData {

    protected $data;

    /**
     * PeeGraphData constructor.
     *
     * @param ShapelessConcreteStruct[] $languageStats
     */
    public function __construct( array $languageStats ) {
        $this->data = $languageStats;
    }

    public function render() {

        $languages_instance = Langs_Languages::getInstance();

        if ( !empty( $this->data ) ) {
            $dataLangStats = [];
        } else {
            $dataLangStats[] = [
                    "source"           => null,
                    "target"           => null,
                    "pee"              => 0,
                    "fuzzy_band"       => null,
                    "totalwordPEE"     => null,
                    "current_payable"  => null,
                    "payable_rate"     => null,
                    "saving_diff"      => null,
                    "job_count"        => null
            ];
        }

        foreach ( $this->data as $k => $value ) {

            $proposal_pee              = Analysis_PayableRates::proposalPee( Analysis_PayableRates::pee2payable( $value->total_post_editing_effort ) );
            $fuzzy = ( stripos( $value->fuzzy_band, 'MT' ) !== false ? 'MT' : $value->fuzzy_band );
            $dataLangStats[] = [
                    "source"           => $languages_instance->getLocalizedName( $value->source ),
                    "target"           => $languages_instance->getLocalizedName( $value->target ),
                    "pee"              => $value->total_post_editing_effort,
                    "fuzzy_band"       => $value->fuzzy_band,
                    "totalwordPEE"     => number_format( $value->total_word_count, 0, ",", "." ),
                    "current_payable"  => Analysis_PayableRates::getPayableRates( $value->source, $value->target )[ $fuzzy ],
                    "payable_rate"     => $proposal_pee,
                    "saving_diff"      => Analysis_PayableRates::wordsSavingDiff(
                            Analysis_PayableRates::getPayableRates( $value->source, $value->target )[ $fuzzy ],
                            $proposal_pee,
                            $value->total_word_count
                    ),
                    "job_count"        => $value->job_count
            ];

        }

        return [ 'langStats' => $dataLangStats ];

    }

}