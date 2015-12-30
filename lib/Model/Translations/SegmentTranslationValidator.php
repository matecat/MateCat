<?php

class Translations_SegmentTranslationValidator extends \DataAccess_AbstractValidator {

    public function validate() {

        $valid_statuses = array(
            Constants_TranslationStatus::STATUS_NEW,
            Constants_TranslationStatus::STATUS_DRAFT,
            Constants_TranslationStatus::STATUS_TRANSLATED,
            Constants_TranslationStatus::STATUS_APPROVED,
            Constants_TranslationStatus::STATUS_REJECTED
        );

        if (! in_array( $this->struct->status, $valid_statuses )) {
            $this->errors[] = array('status', "is not valid");
        }

    }

}
