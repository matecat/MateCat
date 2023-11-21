<?php

namespace Model\Analysis;

use API\App\Json\Analysis\AnalysisProject;
use API\App\Json\Analysis\MatchConstants;
use API\App\Json\Analysis\MatchContainerInterface;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */
class XTRFStatus extends AbstractStatus {

    /**
     * @var AnalysisProject $result
     */
    protected $result;

    /**
     * @return array
     */
    public function getResult() {

        $outputContent = [];

        if ( !empty( $this->result->getJobs() ) ) {

            foreach ( $this->result->getJobs() as $j ) {

                foreach ( $j->getChunks() as $chunk ) {

                    $vector = [
                            'name'        => $this->result->getName(),
                            'create_date' => $this->result->getCreateDate(),
                            'source'      => $j->getSource(),
                            'target'      => $j->getTarget()
                    ];

                    $outputContent[ $j->getId() . "-" . $chunk->getPassword() ] = null;

                    foreach ( $chunk->getFiles() as $file ) {

                        $vector[ 'firstLine' ] = str_pad( "File: ", 23 ) . $file->getId() . "_" . $file->getName();
                        $outputContent[ $j->getId() . "-" . $chunk->getPassword() ] .= $this->formatFile( $file, $vector );

                    }

                    $outputContent[ $j->getId() . "-" . $chunk->getPassword() ] .= str_repeat( "-", 80 ) . PHP_EOL . PHP_EOL;

                    $vector[ 'firstLine' ] = str_pad( "Total: ", 23 ) . count( $chunk->getFiles() ) . " files";
                    $outputContent[ $j->getId() . "-" . $chunk->getPassword() ] .= $this->formatFile( $chunk->getSummary(), $vector );

                }

            }


        }

        return $outputContent;

    }

    protected function formatFile( MatchContainerInterface $values, $vector ) {


        $_TOTAL_RAW_SUM = (
                $values->getMatch( MatchConstants::_NEW )->getRaw() +
                $values->getMatch( MatchConstants::_ICE )->getRaw() +
                $values->getMatch( MatchConstants::_REPETITIONS )->getRaw() +
                $values->getMatch( MatchConstants::_NUMBERS_ONLY )->getRaw() +
                $values->getMatch( MatchConstants::_INTERNAL )->getRaw() +
                $values->getMatch( MatchConstants::_100 )->getRaw() +
                $values->getMatch( MatchConstants::_100_PUBLIC )->getRaw() +
                $values->getMatch( MatchConstants::_95_99 )->getRaw() +
                $values->getMatch( MatchConstants::_85_94 )->getRaw() +
                $values->getMatch( MatchConstants::_75_84 )->getRaw() +
                $values->getMatch( MatchConstants::_50_74 )->getRaw() +
                $values->getMatch( MatchConstants::_MT )->getRaw()
        );

        $TOTAL_EQUIVALENT = (
                $values->getMatch( MatchConstants::_NEW )->getEquivalent() +
                $values->getMatch( MatchConstants::_ICE )->getEquivalent() +
                $values->getMatch( MatchConstants::_REPETITIONS )->getEquivalent() +
                $values->getMatch( MatchConstants::_NUMBERS_ONLY )->getEquivalent() +
                $values->getMatch( MatchConstants::_INTERNAL )->getEquivalent() +
                $values->getMatch( MatchConstants::_100 )->getEquivalent() +
                $values->getMatch( MatchConstants::_100_PUBLIC )->getEquivalent() +
                $values->getMatch( MatchConstants::_95_99 )->getEquivalent() +
                $values->getMatch( MatchConstants::_85_94 )->getEquivalent() +
                $values->getMatch( MatchConstants::_75_84 )->getEquivalent() +
                $values->getMatch( MatchConstants::_50_74 )->getEquivalent() +
                $values->getMatch( MatchConstants::_MT )->getEquivalent()
        );

        $fileContent = $vector[ 'firstLine' ] . PHP_EOL;
        $fileContent .= str_pad( "Date: ", 23 ) . date_create( $vector[ 'create_date' ] )->format( DATE_RFC822 ) . PHP_EOL;
        $fileContent .= str_pad( "Project: ", 23 ) . $vector[ 'name' ] . PHP_EOL;
        $fileContent .= str_pad( "Language direction: ", 23 ) . $vector[ 'source' ] . " > " . $vector[ 'target' ] . PHP_EOL;
        $fileContent .= PHP_EOL;

        $fileContent .=
                str_pad( "Match Types", 16 ) .
                str_pad( "Words", 12, " ", STR_PAD_LEFT ) .
                str_pad( "Percent", 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "New words", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_NEW )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_NEW )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Context Match", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_ICE )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_ICE )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Repetitions", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_REPETITIONS )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_REPETITIONS )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Format Change", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_NUMBERS_ONLY )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_NUMBERS_ONLY )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Internal Match", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_INTERNAL )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_INTERNAL )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "100%", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_100 )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_100 )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "100% Public TM", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_100_PUBLIC )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_100_PUBLIC )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "95% - 99%", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_95_99 )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_95_99 )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "85% - 94%", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_85_94 )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_85_94 )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "75% - 84%", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_75_84 )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_75_84 )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "50% - 74%", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_50_74 )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_50_74 )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "MT", 16 ) .
                str_pad( $values->getMatch( MatchConstants::_MT )->getRaw(), 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values->getMatch( MatchConstants::_MT )->getRaw() / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .= PHP_EOL;

        $fileContent .=
                str_pad( "Total Payable", 16 ) .
                str_pad( $TOTAL_EQUIVALENT, 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $TOTAL_EQUIVALENT / $_TOTAL_RAW_SUM * 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Total", 16 ) .
                str_pad( $_TOTAL_RAW_SUM, 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( 100, 2, '.', '' ), 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .= PHP_EOL . PHP_EOL;

        return $fileContent;

    }

}