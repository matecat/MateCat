<?php

/**
 * `memory_keys`.`key_name` used to be written through FILTER_SANITIZE_SPECIAL_CHARS, so a resource
 * someone called "O'Brien" was stored as "O&#39;Brien". Nothing decoded it on the way back except a
 * html_entity_decode in TmKeyManagementController, which had to go: with names now stored as typed,
 * it turned a resource genuinely called "A &amp; B" into "A & B" for good.
 *
 * Removing that decode without this migration would show every key written before the change with
 * its entities visible. So the rows are decoded once, here, and the read path stays honest.
 *
 * Only the five numeric forms that filter produced are decoded, and `&#38;` last: `&#38;#60;` is
 * what a user who typed "&#60;" got, and decoding the ampersand first would turn it into "<" — one
 * level too far. The named forms (`&amp;`, `&lt;`) are deliberately left alone: that filter never
 * wrote them, so an `&amp;` in the column today is one somebody typed.
 */
class DecodeLegacyEntitiesInMemoryKeyNames
{
    public array $sql_up = [
        "
        UPDATE `memory_keys`
           SET `key_name` = REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(`key_name`, '&#60;', '<'),
                                        '&#62;', '>'),
                                    '&#34;', '\"'),
                                '&#39;', ''''),
                            '&#38;', '&')
         WHERE `key_name` LIKE '%&#%';
    "
    ];

    /**
     * Not reversible. Re-encoding would not restore the previous state, it would encode the
     * characters in every name typed since — including the ones this migration was written to stop
     * being mangled. The previous state is a backup, not a statement.
     */
    public array $sql_down = [
        "SELECT 'DecodeLegacyEntitiesInMemoryKeyNames is not reversible' AS `note`;"
    ];
}
