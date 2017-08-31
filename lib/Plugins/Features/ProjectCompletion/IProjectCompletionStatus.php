<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/01/2017
 * Time: 17:16
 */

namespace Features\ProjectCompletion;


interface IProjectCompletionStatus {

    public function isCompletable();

    public function isReviewable();

    public function isTranslatable();

    public function notReviewableMessage();

    public function notTranslatableMessage();

    public function notCompletableMessage();

}