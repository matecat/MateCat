<?php

namespace Controller\Traits;

use Exception;
use InvalidArgumentException;
use Matecat\Locales\Languages;

trait ValidatesDialectStrictTrait
{
    /**
     * Validate `dialect_strict` param
     *
     * Example: {"it-IT": true, "en-US": false, "fr-FR": false}
     *
     * @param Languages $lang_handler
     * @param null $dialect_strict
     *
     * @return string|null
     */
    private function validateDialectStrictParam(Languages $lang_handler, $dialect_strict = null): ?string
    {
        if (!empty($dialect_strict)) {
            $dialect_strict = trim(html_entity_decode($dialect_strict));

            // first check if `dialect_strict` is a valid JSON
            if (!json_validate($dialect_strict)) {
                throw new InvalidArgumentException("dialect_strict is not a valid JSON");
            }

            $dialectStrictObj = json_decode($dialect_strict, true);

            foreach ($dialectStrictObj as $lang => $value) {
                try {
                    $lang_handler->validateLanguage($lang);
                } catch (Exception) {
                    throw new InvalidArgumentException(
                        'Wrong `dialect_strict` object, language, ' . $lang . ' is not supported'
                    );
                }

                if (!is_bool($value)) {
                    throw new InvalidArgumentException(
                        'Wrong `dialect_strict` object, not boolean declared value for ' . $lang
                    );
                }
            }

            return html_entity_decode($dialect_strict);
        }

        return null;
    }
}

