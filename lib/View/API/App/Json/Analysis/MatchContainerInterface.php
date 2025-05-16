<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 17/11/23
 * Time: 16:33
 *
 */

namespace API\App\Json\Analysis;

interface MatchContainerInterface {

    /**
     * @param string $matchName
     *
     * @return AnalysisMatch
     */
    public function getMatch( string $matchName ): AnalysisMatch;

}