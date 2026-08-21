<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Validation;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Validation\LegacyEntityDecoder;

/**
 * The backfill this drives gets one pass over the data, and the part of it that can silently be wrong
 * is the ordering: nothing about reading the replacement list tells you that `&#38;` has to come
 * last. These cases are what pins it.
 */
#[Group('unit')]
class LegacyEntityDecoderTest extends AbstractTest
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function storedNames(): array
    {
        return [
            // What FILTER_SANITIZE_SPECIAL_CHARS wrote, and what the reader should have seen.
            'apostrophe'      => ["O&#39;Brien", "O'Brien"],
            'ampersand'       => ['A &#38; B', 'A & B'],
            'angle brackets'  => ['&#60;core&#62;', '<core>'],
            'double quote'    => ['The &#34;Best&#34; glossary', 'The "Best" glossary'],
            'several at once' => ['&#60;a&#62; &#38; &#34;b&#34;', '<a> & "b"'],

            // A name written after the column started holding raw text: somebody typed these
            // characters. The named forms were never produced by that filter, so they stay.
            'typed named entity' => ['A &amp; B', 'A &amp; B'],
            'typed apostrophe'   => ["O'Brien", "O'Brien"],
            'plain'              => ['Acme Memory', 'Acme Memory'],
            'non latin'          => ['メモリ', 'メモリ'],
            'empty'              => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('storedNames')]
    public function it_decodes_only_what_the_old_filter_wrote(string $stored, string $expected): void
    {
        self::assertSame($expected, LegacyEntityDecoder::decode($stored));
    }

    #[Test]
    public function it_decodes_the_ampersand_last_so_a_typed_entity_survives(): void
    {
        // "&#38;#60;" is what a user who typed the six characters "&#60;" got: the filter encoded
        // their ampersand and left the rest. Decoding the ampersand first would produce "&#60;",
        // which the next rule would turn into "<" — one level too far, and what they typed is gone.
        self::assertSame('&#60;', LegacyEntityDecoder::decode('&#38;#60;'));
    }

    #[Test]
    public function decoding_is_not_idempotent_which_is_why_the_backfill_bounds_by_date(): void
    {
        // Found by running the backfill twice against a real table. Most values reach a fixed point
        // after one pass, but this one does not: "&#38;#60;" is the encoding of the six characters
        // "&#60;", and its correct decoding is "&#60;" — which still looks encoded, so a second pass
        // turns it into "<" and the name the user typed is gone.
        //
        // No rule over the string alone can tell those two apart. The backfill therefore selects on
        // `update_date` being older than the deploy, so a row it has written is out of scope
        // afterwards — and a name somebody types as "&#39;" from now on is never in scope at all.
        $once = LegacyEntityDecoder::decode('&#38;#60;');
        self::assertSame('&#60;', $once);
        self::assertSame('<', LegacyEntityDecoder::decode($once), 'a second pass over the same value keeps going');
    }

    #[Test]
    public function most_values_do_reach_a_fixed_point(): void
    {
        $once = LegacyEntityDecoder::decode("O&#39;Brien &#38; Sons");

        self::assertSame("O'Brien & Sons", $once);
        self::assertSame($once, LegacyEntityDecoder::decode($once));
    }
}
