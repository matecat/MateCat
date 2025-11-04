<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/01/2019
 * Time: 15:34
 */


namespace Plugins\Features\ReviewExtended;

interface IReviewedWordCountModel
{
    public function evaluateChunkReviewEventTransitions(): void;
}