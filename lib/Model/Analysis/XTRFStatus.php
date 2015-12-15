<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */
class Analysis_XTRFStatus extends Analysis_APIStatus {

    public function getResult(){

        parent::getResult();

        if( $this->result[ 'errors' ] ){
            return $this->formatErrors();
        }

        $outputContent = array();

        /**
         * Every target language will be put in a separated txt file.
         *
         * Each row of this cycle is a target language,
         *
         * Every target language file will contain a report for single file and the total for the job.
         *
         */
        foreach( $this->result[ 'data' ][ 'jobs' ] as $idJob => $chunksContainer ){

            foreach ( $chunksContainer[ 'chunks' ] as $password => $filesContainer ){

                list( $source, $target ) = explode( "|", $this->result[ 'jobs' ][ 'langpairs' ][ $idJob . "-" . $password ] );

                $outputContent[ $idJob . "-" . $password ] = null;

                foreach( $filesContainer as $idFile => $values ){

                    $vector = array();
                    $vector[ 'firstLine' ] = str_pad( "File: ", 23, " ", STR_PAD_RIGHT ) . $idFile . "_" . $values[ 'FILENAME' ];
                    $vector[ 'source' ]    = $source;
                    $vector[ 'target' ]    = $target;

                    $outputContent[ $idJob . "-" . $password ] .= $this->formatFile( $values, $vector );

                }

                $outputContent[ $idJob . "-" . $password ] .= str_repeat( "-", 80 ) . PHP_EOL . PHP_EOL;

            }

            foreach( $chunksContainer[ 'totals' ] as $password => $jobResume ){

                list( $source, $target ) = explode( "|", $this->result[ 'jobs' ][ 'langpairs' ][ $idJob . "-" . $password ] );

                $vector = array();
                $vector[ 'firstLine' ] = str_pad( "Total: ", 23, " ", STR_PAD_RIGHT ) . count( $chunksContainer[ 'chunks' ][ $password ] ) . " files";
                $vector[ 'source' ] = $source;
                $vector[ 'target' ] = $target;

                $outputContent[ $idJob . "-" . $password ] .= $this->formatFile( $chunksContainer[ 'totals' ][ $password ], $vector );

            }

        }

        return $outputContent;

    }

    public function formatErrors(){
        return "Error.";
    }

    protected function formatFile( $values, $vector ){

        $_TOTAL_RAW_SUM = (
                $values[ 'NEW' ][0] +
                $values[ 'ICE' ][0] +
                $values[ 'REPETITIONS' ][0] +
                $values[ 'NUMBERS_ONLY' ][0] +
                $values[ 'INTERNAL_MATCHES' ][0] +
                $values[ 'TM_100' ][0] +
                $values[ 'TM_100_PUBLIC' ][0] +
                $values[ 'TM_95_99' ][0] +
                $values[ 'TM_85_94' ][0] +
                $values[ 'TM_75_84' ][0] +
                $values[ 'TM_50_74' ][0] +
                $values[ 'MT' ][0]
        );

        $fileContent = $vector[ 'firstLine' ] . PHP_EOL;
        $fileContent .= str_pad( "Date: ", 23, " ", STR_PAD_RIGHT ) . date_create( $this->_project_data[0][ 'create_date' ] )->format( DATE_RFC822 ) . PHP_EOL;
        $fileContent .= str_pad( "Project: ", 23, " ", STR_PAD_RIGHT ) . $this->_project_data[0][ 'pname' ] . PHP_EOL;
        $fileContent .= str_pad( "Language direction: ", 23, " ", STR_PAD_RIGHT ) . $vector[ 'source' ] . " > " . $vector[ 'target' ] . PHP_EOL;
        $fileContent .= PHP_EOL;

        $fileContent .=
                str_pad( "Match Types", 16, " ", STR_PAD_RIGHT ) .
                str_pad( "Words", 12, " ", STR_PAD_LEFT ) .
                str_pad( "Percent", 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "New words", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'NEW' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'NEW' ][0] / $values[ 'TOTAL_PAYABLE' ][0] * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Context Match", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'ICE' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'ICE' ][0] / $values[ 'TOTAL_PAYABLE' ][0] * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Repetitions", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'REPETITIONS' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'REPETITIONS' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Format Change", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'NUMBERS_ONLY' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'NUMBERS_ONLY' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Internal Match", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'INTERNAL_MATCHES' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'INTERNAL_MATCHES' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "100%", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TM_100' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TM_100' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "100% Public TM", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TM_100_PUBLIC' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TM_100_PUBLIC' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "95% - 99%", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TM_95_99' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TM_95_99' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "85% - 94%", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TM_85_94' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TM_85_94' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "75% - 84%", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TM_75_84' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TM_75_84' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "50% - 74%", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TM_50_74' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TM_50_74' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "MT", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'MT' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'MT' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .= PHP_EOL;

        $fileContent .=
                str_pad( "Total Payable", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $values[ 'TOTAL_PAYABLE' ][0], 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $values[ 'TOTAL_PAYABLE' ][0] / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .=
                str_pad( "Total", 16, " ", STR_PAD_RIGHT ) .
                str_pad( $_TOTAL_RAW_SUM, 12, " ", STR_PAD_LEFT ) .
                str_pad( number_format( $_TOTAL_RAW_SUM / $_TOTAL_RAW_SUM * 100, 2, '.', '' ) , 14, " ", STR_PAD_LEFT ) .
                PHP_EOL;

        $fileContent .= PHP_EOL . PHP_EOL;

        return $fileContent;

    }

}