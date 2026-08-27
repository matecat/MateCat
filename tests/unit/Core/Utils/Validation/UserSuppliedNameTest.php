<?php

namespace Matecat\Core\Utils\Validation;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Validation\UserSuppliedName;

class UserSuppliedNameTest extends AbstractTest
{
    // ─── normalize ───────────────────────────────────────────────────────

    /**
     * The cases the five sanitizers this class replaces used to destroy. Every one of them is a
     * name a real customer types, and each was silently rewritten before: `\P{L}` stripping turned
     * `O'Brien` into `O Brien`, the project allowlist turned `Acme & Co (2024)` into
     * `Acme  Co 2024`, and `FILTER_FLAG_STRIP_HIGH` emptied any name written in a non-Latin script.
     *
     * @return array<string, array{string}>
     */
    public static function namesStoredAsTyped(): array
    {
        return [
            'plain'                      => ['Marketing Team'],
            'apostrophe'                 => ["O'Brien"],
            'hyphen'                     => ['Jean-Luc'],
            'ampersand and parentheses'  => ['Acme & Co (2024)'],
            'angle brackets'             => ['Team <core>'],
            'double quote'               => ['The "Best" Team'],
            'entity text typed as text'  => ['A &amp; B'],
            'version number'             => ['Localisation 2.0'],
            'abbreviation'               => ['Translated S.r.l.'],
            'a company named after its domain' => ['Acme.com'],
            'an address'                 => ['f.surname@example.com'],
            'accented latin'             => ['Équipe Française'],
            'han'                        => ['李明'],
            'cyrillic'                   => ['Тест'],
            'katakana'                   => ['メモリ'],
            'arabic'                     => ['ذاكرة'],
            'persian with a non-joiner'  => ["می\u{200C}خواهم"],
        ];
    }

    #[Test]
    #[DataProvider('namesStoredAsTyped')]
    public function normalize_leaves_a_legitimate_name_exactly_as_typed(string $typed): void
    {
        self::assertSame($typed, UserSuppliedName::normalize($typed));
    }

    #[Test]
    public function normalize_strips_control_characters_and_collapses_whitespace(): void
    {
        $normalized = UserSuppliedName::normalize("Team\r\nBcc: victim\tand\x00more   spaces");

        self::assertSame('Team Bcc: victim and more spaces', $normalized);
        self::assertStringNotContainsString("\r", $normalized);
        self::assertStringNotContainsString("\n", $normalized);
    }

    #[Test]
    public function normalize_replaces_a_control_character_rather_than_deleting_it(): void
    {
        // Deleting would join the two words into the single token "Bcc:victim", which is exactly
        // the string the header check exists to keep out of a Subject line.
        self::assertSame('Bcc: victim', UserSuppliedName::normalize("Bcc:\nvictim"));
    }

    #[Test]
    public function normalize_removes_zero_width_and_bidi_format_characters(): void
    {
        // U+200B ZERO WIDTH SPACE, U+202E RIGHT-TO-LEFT OVERRIDE: invisible, and used to spoof how
        // a name reads.
        self::assertSame('Acme Corp', UserSuppliedName::normalize("Acme\u{200B} \u{202E}Corp"));
    }

    #[Test]
    public function normalize_keeps_the_zero_width_non_joiner_because_it_changes_the_word(): void
    {
        // U+200C is a letter-joining rule in Persian, Urdu, Pashto and Kurdish, not decoration:
        // "می‌خواهم" written without it is a different word. The loss would happen on write, so it
        // could never be recovered — the same damage as storing "O'Brien" as "O Brien", which is
        // what this class exists to stop.
        $wanted = "می\u{200C}خواهم";

        self::assertSame($wanted, UserSuppliedName::normalize($wanted));
    }

    #[Test]
    public function normalize_keeps_the_non_joiner_even_when_another_format_character_precedes_it(): void
    {
        // The regression the in-group lookahead exists for: guarding a `+` quantifier from the
        // front only tests the first character of a run, so a ZWSP immediately followed by a ZWNJ
        // would have had the non-joiner swallowed by the same match and the word after it changed.
        self::assertSame(
            "A می\u{200C}خواهم",
            UserSuppliedName::normalize("A\u{200B}می\u{200C}خواهم")
        );
    }

    #[Test]
    public function normalize_removes_the_zero_width_joiner(): void
    {
        // The opposite of the non-joiner, and the reason the exemption is not for both. A joiner
        // between two letters is invisible, so "Ad<ZWJ>min" reads exactly like "Admin" on screen
        // while being a different string — two rows can then exist under names that read the same.
        // It only carries meaning inside an emoji sequence, and {@see assertNoAstral()} refuses
        // those anyway.
        self::assertSame('Ad min', UserSuppliedName::normalize("Ad\u{200D}min"));
    }

    #[Test]
    public function normalize_composes_to_a_single_unicode_form(): void
    {
        $decomposed  = "E\u{0301}quipe";   // E + COMBINING ACUTE ACCENT
        $precomposed = "\u{00C9}quipe";    // É

        self::assertSame($precomposed, UserSuppliedName::normalize($decomposed));
        self::assertSame(
            UserSuppliedName::normalize($precomposed),
            UserSuppliedName::normalize($decomposed),
            'two spellings of one name must compare equal, or a UNIQUE index cannot see the clash'
        );
    }

    #[Test]
    public function normalize_scrubs_a_byte_it_cannot_read(): void
    {
        // A lone continuation byte. Scrubbing gives up that byte and keeps the rest, which is the
        // only outcome of the three that is not a loss: returning empty deletes a name nobody asked
        // to delete, and returning the input unchanged leaves a string preg_replace cannot process,
        // so a CR would reach a Subject header.
        $scrubbed = UserSuppliedName::normalize("\x80Acme");

        self::assertStringContainsString('Acme', $scrubbed);
        self::assertTrue(mb_check_encoding($scrubbed, 'UTF-8'));
    }

    #[Test]
    public function normalize_keeps_an_emoji_because_refusing_is_not_its_job(): void
    {
        // The transformer/rule split: normalize() reports what the name is, and
        // {@see UserSuppliedName::assertNoAstral()} decides whether it can be stored. Stripping
        // here would take the choice away from the callers that must refuse rather than mangle.
        self::assertSame('Team 🚀', UserSuppliedName::normalize('Team 🚀'));
    }

    #[Test]
    public function normalize_treats_a_missing_name_as_empty(): void
    {
        self::assertSame('', UserSuppliedName::normalize(null));
        self::assertSame('', UserSuppliedName::normalize('   '));
    }

    // ─── normalizeAndTruncate ────────────────────────────────────────────

    #[Test]
    public function normalizeAndTruncate_cuts_to_fit_rather_than_refusing(): void
    {
        self::assertSame(str_repeat('a', 50), UserSuppliedName::normalizeAndTruncate(str_repeat('a', 80), 50));
    }

    #[Test]
    public function normalizeAndTruncate_counts_characters_not_bytes(): void
    {
        // mb_substr, not substr: cutting a multibyte name on a byte boundary produces invalid UTF-8.
        self::assertSame('メモリ', UserSuppliedName::normalizeAndTruncate('メモリテスト', 3));
    }

    // ─── assertNotEmpty ──────────────────────────────────────────────────

    #[Test]
    public function assertNotEmpty_refuses_an_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name is empty');

        UserSuppliedName::assertNotEmpty('', 'name');
    }

    // ─── assertLength ────────────────────────────────────────────────────

    #[Test]
    public function assertLength_refuses_a_name_wider_than_the_column(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name must be at most 255 characters');

        UserSuppliedName::assertLength(str_repeat('a', 256), 'name', 255, 100);
    }

    #[Test]
    public function assertLength_refuses_a_name_longer_than_the_reader_would_see(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name must be at most 100 characters');

        UserSuppliedName::assertLength(str_repeat('a', 101), 'name', 255, 100);
    }

    #[Test]
    public function assertLength_measures_the_readable_cap_against_the_decoded_name(): void
    {
        // 250 raw characters, 50 as read. Measured on the raw string this was refused for length,
        // which is the defect the decode fixes.
        UserSuppliedName::assertLength(str_repeat('&amp;', 50), 'name', 255, 100);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function assertLength_still_bounds_the_raw_name_by_the_column(): void
    {
        // 52 characters as read, but 260 in the row. The readable cap passes and the column cap
        // does not — the two are measured separately for exactly this case.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name must be at most 255 characters');

        UserSuppliedName::assertLength(str_repeat('&amp;', 52), 'name', 255, 100);
    }

    #[Test]
    public function assertLength_names_the_parameter_it_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wrong parameter: first_name must be at most 50 characters');

        UserSuppliedName::assertLength(str_repeat('a', 51), 'first_name', 50, 50);
    }

    // ─── assertNoUrl ─────────────────────────────────────────────────────

    /**
     * @return array<string, array{string}>
     */
    public static function linkShapedNames(): array
    {
        return [
            'the reported payload'         => ['https://bing.com'],
            'scheme only'                  => ['http://x'],
            'javascript scheme'            => ['javascript://comment'],
            'www prefix'                   => ['www.example.com'],
            'uppercase'                    => ['HTTPS://EVIL.COM'],
            'scheme smuggled as an entity' => ['https&#58;//evil.com'],
        ];
    }

    #[Test]
    #[DataProvider('linkShapedNames')]
    public function assertNoUrl_refuses_a_name_that_reads_as_a_link(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name cannot contain a URL');

        UserSuppliedName::assertNoUrl($name, 'name');
    }

    /**
     * The six cases a bare-hostname rule refused and this one accepts. Measured against production,
     * that rule was wrong 106 times out of 120.
     *
     * @return array<string, array{string}>
     */
    public static function namesCarryingADot(): array
    {
        return [
            'a company named after its domain' => ['Acme.com'],
            'a suffix that is not a domain'    => ['Alpha.Beta'],
            'a hostname inside prose'          => ['Acme.guru Polite statements'],
            'a name that is an address'        => ['f.surname@example.com'],
            'an abbreviation'                  => ['Translated S.r.l.'],
            'a dot smuggled as an entity'      => ['evil&#46;com'],
        ];
    }

    #[Test]
    #[DataProvider('namesCarryingADot')]
    public function assertNoUrl_accepts_a_name_that_merely_carries_a_dot(string $name): void
    {
        UserSuppliedName::assertNoUrl($name, 'name');

        $this->expectNotToPerformAssertions();
    }

    /**
     * The `!== 0` guard cannot be exercised from here, and it is worth recording why rather than
     * shipping a test that passes for the wrong reason.
     *
     * `preg_match` returns false when PCRE abandons a match, and lowering `pcre.backtrack_limit` is
     * the usual way to force that. It does nothing to this pattern. `[a-z0-9+.-]*` is followed by
     * `:`, which is not in the class, so PCRE2 auto-possessifies the quantifier — there is no
     * alternative division to return to and so no backtracking to limit. The `\bwww\.` branch has
     * none either, and the start bitmap skips every position that cannot begin a match without
     * counting against the limit. Verified with the JIT both on and off.
     *
     * So the guard is defensive against a future edit to the pattern, not against a reachable input.
     * Note that `TeamsControllerTest::create_refuses_a_link_when_the_regex_engine_gives_up` claims to
     * pin it but does not: it passes `https://bing.com`, which the rule refuses whether the engine
     * decided or gave up, so that test holds equally with `=== 1`.
     */

    // ─── validated ───────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('namesStoredAsTyped')]
    public function validated_returns_a_legitimate_name_as_typed(string $typed): void
    {
        self::assertSame($typed, UserSuppliedName::validated($typed, 'name', 255));
    }

    #[Test]
    public function validated_runs_the_empty_check_before_the_length_check(): void
    {
        // A name that is nothing but control characters normalises to empty, and must be answered
        // as empty rather than as some other failure.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name is empty');

        UserSuppliedName::validated("\r\n\t", 'name', 255, 100);
    }

    #[Test]
    public function validated_does_not_apply_the_url_rule(): void
    {
        // A person's own name is never quoted to a stranger in an invitation, so there is nothing
        // for the rule to defend; the email sink defangs it like any other value. The rule lives on
        // validatedForEmailQuote() instead of behind a flag, because the team name is the one field
        // that needs it and every other caller was writing `refuseUrl: false` to say so.
        self::assertSame('www.smith', UserSuppliedName::validated('www.smith', 'last_name', 50));
    }

    #[Test]
    public function validated_for_an_email_quote_applies_the_url_rule(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot contain a URL');

        UserSuppliedName::validatedForEmailQuote('https://evil.example', 'name', 255, 100);
    }

    #[Test]
    public function validated_normalizes_before_it_measures(): void
    {
        // 100 characters padded out to 130 with whitespace and control characters: the caps have to
        // see the collapsed form, or padding alone would fail a name that fits.
        $padded = str_repeat("a\t\r\n ", 20) . str_repeat('b', 20);

        self::assertSame(
            str_repeat('a ', 19) . 'a ' . str_repeat('b', 20),
            UserSuppliedName::validated($padded, 'name', 60, 60)
        );
    }
    // ─── characters the storage cannot carry ──────────────────────────────

    #[Test]
    public function validated_refuses_a_character_outside_the_basic_multilingual_plane(): void
    {
        // Nothing refused one before, so MySQL decided: the value was cut at the emoji and
        // "Acme 😀 Team" was stored as "Acme", silently. How wide the column is depends on the
        // installation, so the rule assumes the narrower storage rather than reading it, and the
        // honest answer is a 400 that says why.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot contain characters outside the Basic Multilingual Plane');

        UserSuppliedName::validated('Acme 😀 Team', 'name', 255);
    }

    #[Test]
    public function validated_refuses_a_character_outside_the_plane_even_when_it_is_a_letter(): void
    {
        // CJK Extension B holds rare but real Chinese and Japanese name characters. Refusing is
        // still better than the alternative, which is the name being cut at that character with no
        // error, but this is the cost of assuming the narrower storage, not a judgement about the
        // name.
        $this->expectException(InvalidArgumentException::class);

        UserSuppliedName::validated("\u{20000}", 'name', 255);
    }

    #[Test]
    public function validated_accepts_an_emoji_that_fits_inside_the_plane(): void
    {
        // The rule is about how many bytes the character needs, not about emoji: these sit inside
        // the Basic Multilingual Plane and fit wherever the astral ones do not. Refusing them would
        // be a product restriction with nothing behind it, so the message says what the rule checks
        // rather than promising "no emoji" and then accepting these.
        // The variation selector on the last two is U+FE0F, a mark rather than a format character,
        // so normalize() leaves it alone and the name comes back exactly as typed.
        self::assertSame('Acme ☺ Team', UserSuppliedName::validated('Acme ☺ Team', 'name', 255));
        self::assertSame('Love ❤️ Co', UserSuppliedName::validated('Love ❤️ Co', 'name', 255));
        self::assertSame('Team ✈️', UserSuppliedName::validated('Team ✈️', 'name', 255));
    }

    #[Test]
    public function normalize_and_truncate_strips_what_validated_refuses(): void
    {
        // The OAuth callback and project creation cannot throw: a 400 there refuses the login or
        // the upload rather than correcting anything. A space rather than nothing, so the words
        // either side stay separate.
        self::assertSame('Acme Team', UserSuppliedName::normalizeAndTruncate('Acme 😀 Team', 255));
    }

    // ─── the two failure modes the transformer has to survive ─────────────

    #[Test]
    public function normalize_scrubs_invalid_utf8_rather_than_blanking_the_name(): void
    {
        // Returning empty would have handed the caller's own non-empty check the refusal, but the
        // four normalizeAndTruncate callers have no such check — one stray byte from an older
        // client would have erased the whole name. Returning the input unchanged is worse still:
        // preg_replace cannot run on it, so a CR would survive into a Subject header.
        $scrubbed = UserSuppliedName::normalize("Jos\xE9 Smith");

        self::assertNotSame('', $scrubbed);
        self::assertTrue(mb_check_encoding($scrubbed, 'UTF-8'));
        self::assertStringContainsString('Smith', $scrubbed);
    }

    #[Test]
    public function normalize_scrubs_and_still_removes_a_control_character(): void
    {
        $scrubbed = UserSuppliedName::normalize("Bcc:\r\nvictim\xE9");

        self::assertStringNotContainsString("\r", $scrubbed);
        self::assertStringNotContainsString("\n", $scrubbed);
    }

    #[Test]
    public function normalize_and_truncate_does_not_leave_a_trailing_space(): void
    {
        // trim() runs inside normalize(), before the cut, so cutting at a word boundary used to
        // store "aaaa " — a name with a space nobody typed at the end of it.
        self::assertSame('aaaa', UserSuppliedName::normalizeAndTruncate('aaaa bbbbbbbbbb', 5));
    }

    #[Test]
    public function normalize_and_truncate_keeps_a_name_of_zero(): void
    {
        // "0" is a name someone can type, and the call sites used to reach here through
        // `filter_var(...) ?: null`, where '0' ?: null is null.
        self::assertSame('0', UserSuppliedName::normalizeAndTruncate('0', 100));
    }
}
