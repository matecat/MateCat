<?php

declare(strict_types=1);

namespace Utils\Validation;

use InvalidArgumentException;
use Normalizer;

/**
 * The one place a name a user typed by hand is normalised and checked.
 *
 * Every name field in MateCat used to encode its value at the write boundary, through one
 * `FILTER_SANITIZE_SPECIAL_CHARS` or another. That put entity text in the column — which a JSON
 * response or a JavaScript string can never decode back, so a name containing `& < > '` displayed
 * wrong everywhere — while leaving untouched the three things that actually matter for a name: the
 * line breaks that continue a mail Subject header, the invisible characters that spoof how a name
 * reads, and its length.
 *
 * Names are stored as they were typed. Making a value safe for a given output is that output's job:
 * {@see \Utils\Email\EmailValue} for emails, {@see \Utils\Templating\BootstrapConfig} for the page
 * script, `json_encode` for the API, `htmlspecialchars` with `ENT_XML1` for XML.
 *
 * This class replaces five mutually incompatible sanitizers that used to do the job — the team-name
 * pair on `TeamsController`, `CatUtils::stripMaliciousContentFromAName`,
 * `CatUtils::sanitizeProjectName`, the name branch of `TmKeyManager::sanitize`, and the
 * reject-on-difference block in `UserKeysController`. Only the first had a length cap or refused
 * anything; the other four silently deleted characters instead, which is how `O'Brien` came to be
 * stored as `O Brien` and `Acme & Co (2024)` as `Acme  Co 2024`.
 *
 * The two halves fail in opposite directions on purpose. {@see normalize()} is a transformer: when
 * it cannot run it returns what it was given, because blanking a value nobody asked it to delete is
 * worse than leaving it unchanged. The `assert*` methods are rules: they refuse on anything other
 * than an explicit "the engine looked and found nothing", because a check whose job is to reject
 * must reject when it cannot decide.
 */
final class UserSuppliedName
{
    /**
     * Prefixed to every refusal, so a caller does not choose its own phrasing and the answers to a
     * bad name are the same shape on every endpoint. The parameter name follows it in lowercase:
     * it is a code identifier, and one that opens a message keeps its case.
     */
    private const string REFUSAL_PREFIX = 'Wrong parameter: ';

    /**
     * The width shared by `project_templates`.`name`, `xliff_config_templates`.`name`,
     * `filters_config_templates`.`name` and `payable_rate_templates`.`name`.
     */
    public const int TEMPLATE_NAME_MAX_LENGTH = 255;

    /** `qa_model_templates`.`label` is narrower than the rest, at varchar(45). */
    public const int QA_MODEL_LABEL_MAX_LENGTH = 45;

    /**
     * The flags the email templates escape with, so a value is decoded the way the reader's mail
     * client will decode it. HTML5 matters: it emits `&apos;`, which the default HTML 4.01 decoding
     * does not know.
     */
    private const int DECODE_FLAGS = ENT_QUOTES | ENT_HTML5;

    /**
     * Normalise a name on the way in.
     *
     * Control and format characters are removed — a name is a single line of text, and CR/LF in
     * particular would otherwise travel into the Subject header of a notification email. They are
     * replaced with a space rather than deleted, because deleting joins the words on either side:
     * `"Bcc:\nvictim"` would become the single token `Bcc:victim`. Runs of whitespace then collapse,
     * so a name cannot be padded out to look like separate lines.
     *
     * U+200C (ZERO WIDTH NON-JOINER) is the one exemption. It is a format character, but it is also
     * a letter-joining rule in Persian, Urdu, Pashto and Kurdish: `می‌خواهم` written without it is a
     * different word, and the loss happens on write, so it cannot be recovered later. U+200D (ZERO
     * WIDTH JOINER) is deliberately *not* exempt — it only matters for holding an emoji sequence
     * together, which {@see assertNoAstral()} refuses anyway, and a joiner between two letters is
     * invisible, so `Ad<U+200D>min` would read exactly like `Admin` while being a different string.
     *
     * Everything else is preserved verbatim.
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        // Invalid bytes are replaced rather than the whole value refused. An invalid encoding cannot
        // be normalised, measured or compared — `preg_replace` with the `u` modifier abandons the
        // subject and returns null, `mb_strlen` counts something other than characters, and
        // `Normalizer` refuses — so something has to give. Scrubbing gives up only the bytes that
        // are broken: one stray byte from an older client costs that byte — replaced by mbstring's
        // substitute character — rather than the whole name, and every step below still runs, which
        // is what keeps a CR out of a Subject header.
        $name = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

        // The lookahead sits inside the repeated group rather than in front of the class, so it is
        // tested once per character. In front of a `+` quantifier it would only guard the first
        // character of a run, and `ZWSP ZWNJ` would have the non-joiner swallowed by the same match.
        $name = preg_replace('/(?:(?!\x{200C})[\p{Cc}\p{Cf}\p{Zl}\p{Zp}])+/u', ' ', $name) ?? $name;
        $name = self::collapseWhitespace($name);

        // Composed form last, so what gets measured, stored and compared is one spelling. `Équipe`
        // written with a precomposed É and `Équipe` written with E + U+0301 are the same name to a
        // reader, and a UNIQUE(uid, name) index that cannot see that lets a second template be
        // created under a name that already exists.
        $normalized = Normalizer::normalize($name, Normalizer::FORM_C);

        return $normalized !== false ? $normalized : $name;
    }

    /**
     * Normalise, drop what the connection cannot carry, then cut to fit rather than refuse.
     *
     * For the paths where the name is not something the user is currently typing and a 400 would
     * break the request instead of correcting it — the OAuth callback, where the name comes from
     * the identity provider and refusing it would refuse the login.
     */
    public static function normalizeAndTruncate(?string $raw, int $storedMax): string
    {
        // Trimmed after the cut, not only before it. `trim()` inside normalize() runs against the
        // whole string, so cutting `aaaa bbbbbbbbbb` to five characters used to store `"aaaa "`.
        return trim(mb_substr(self::stripAstral(self::normalize($raw)), 0, $storedMax));
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertNotEmpty(string $name, string $param): void
    {
        if ($name === '') {
            throw new InvalidArgumentException(self::REFUSAL_PREFIX . $param . ' is empty', 400);
        }
    }

    /**
     * Refuse a character the storage cannot carry.
     *
     * Nothing between the request and the row rejects one today, so MySQL decides: the value is cut
     * at the offending character and `Acme 😀 Team` is stored as `Acme`, silently, with no error and
     * nothing shown to the user.
     *
     * How many bytes a name column holds is not something this code knows, and should not be.
     * MateCat is open source and every installation owns its schema — `INSTALL/matecat.sql` ships
     * `utf8mb4`, while an older installation is three-byte — so a rule that read the storage would
     * be a rule that behaved differently on each. This refuses on the narrower assumption instead:
     * where the column could hold the character the rule is stricter than it needs to be, which is
     * the safe direction to be wrong in. See the golden rule in CLAUDE.md.
     *
     * Relaxing it where the storage allows is an `ALTER TABLE` per column, the connection charset,
     * and the index widths that follow from four bytes per character — infrastructure, not a
     * change here.
     *
     * This refuses CJK Extension B (`𠀀`) along with the emoji, which holds rare but real Chinese
     * and Japanese name characters. That is the cost of assuming the narrower storage.
     *
     * @throws InvalidArgumentException
     */
    public static function assertNoAstral(string $name, string $param): void
    {
        // `!== 0` rather than `=== 1`, for the reason spelled out in assertNoUrl().
        if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $name) !== 0) {
            throw new InvalidArgumentException(
                self::REFUSAL_PREFIX . $param
                . ' cannot contain emoji or other characters outside the Basic Multilingual Plane',
                400
            );
        }
    }

    /**
     * Two caps, because a name can be measured twice for two different reasons.
     *
     * `$storedMax` is the column width: the raw string is what goes into the row, so that is what
     * has to fit. `$readableMax` is what the reader sees, which is not the same count — names
     * written before the columns held raw text are still entity-encoded, and measuring those on the
     * raw string rejected a name of some sixty visible characters for length. It defaults to
     * `$storedMax`, which is what every caller but the team name wants: one column, one limit.
     *
     * @throws InvalidArgumentException
     */
    public static function assertLength(string $name, string $param, int $storedMax, ?int $readableMax = null): void
    {
        if (mb_strlen($name) > $storedMax) {
            throw new InvalidArgumentException(
                self::REFUSAL_PREFIX . $param . ' must be at most ' . $storedMax . ' characters',
                400
            );
        }

        if ($readableMax !== null && mb_strlen(self::asRead($name)) > $readableMax) {
            throw new InvalidArgumentException(
                self::REFUSAL_PREFIX . $param . ' must be at most ' . $readableMax . ' characters',
                400
            );
        }
    }

    /**
     * Refuse a name that reads as a link.
     *
     * For the fields quoted back in a transactional email MateCat sends, on the owner's behalf, to
     * an address that owner typed in — a team name in an invitation being the case this exists for.
     * A scheme followed by `://`, or a `www.` prefix, is the only shape that is unambiguously an
     * address rather than a word, so no legitimate name carries one and this costs nobody anything.
     *
     * A scheme with no authority — `javascript:`, `data:` — is deliberately not matched here. It is
     * not a link in a name that every sink escapes as text, and neutralising a scheme at the point
     * it could become one is {@see \Utils\Email\LinkDefanger}'s job, which rewrites the colon and so
     * covers every scheme rather than the three worth naming.
     *
     * A bare hostname is deliberately **not** refused. Measured against production on 2026-08-13
     * that rule rejected 120 stored team names, of which 14 were attacks and 106 were real:
     * customers name a team after their own domain, about twenty are named after a member's
     * address, and one refusal was this company's own name. What the rule defended against — a mail
     * client linkifying the name — is handled where it happens, by {@see \Utils\Email\LinkDefanger},
     * which also covers the names already stored.
     *
     * @throws InvalidArgumentException
     */
    public static function assertNoUrl(string $name, string $param): void
    {
        // `!== 0` rather than `=== 1`: preg_match returns false when PCRE gives up — a backtrack or
        // JIT stack limit — and compared with `=== 1` that reads as "no match", so a check written
        // to reject would admit exactly what it exists to keep out. Only an explicit 0, the engine
        // having looked and found nothing, is a pass.
        if (preg_match('~[a-z][a-z0-9+.-]*://|\bwww\.~iu', self::asRead($name)) !== 0) {
            throw new InvalidArgumentException(
                self::REFUSAL_PREFIX . $param . ' cannot contain a URL',
                400
            );
        }
    }

    /**
     * The whole pipeline, in the order the checks have to run: normalise, refuse empty, refuse a
     * character the connection cannot carry, then refuse over-length.
     *
     * The decode inside the length check has to come after normalisation and before the cap — a
     * name written before the columns held raw text is measured on what its reader sees, not on the
     * entity text the row happens to hold.
     *
     * @throws InvalidArgumentException
     */
    public static function validated(?string $raw, string $param, int $storedMax, ?int $readableMax = null): string
    {
        $name = self::normalize($raw);

        self::assertNotEmpty($name, $param);
        self::assertNoAstral($name, $param);
        self::assertLength($name, $param, $storedMax, $readableMax);

        return $name;
    }

    /**
     * {@see validated()} plus the URL rule, for a name MateCat quotes back to a stranger.
     *
     * Named rather than passed as a flag because it is the exception: the team name is the only
     * field that reaches an address someone else typed in, and every other caller was writing
     * `refuseUrl: false` to say so.
     *
     * @throws InvalidArgumentException
     */
    public static function validatedForEmailQuote(
        ?string $raw,
        string $param,
        int $storedMax,
        ?int $readableMax = null
    ): string {
        $name = self::validated($raw, $param, $storedMax, $readableMax);

        // Last, and on the decoded form: a scheme smuggled as `https&#58;//evil.com` satisfies a
        // rule that reads the raw string, and arrives as a live URL because the mail client decodes
        // it.
        self::assertNoUrl($name, $param);

        return $name;
    }

    /**
     * Runs of whitespace to a single space, and none at either end.
     */
    private static function collapseWhitespace(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /**
     * Drop what {@see assertNoAstral()} would refuse, for the callers that must not throw.
     *
     * A space rather than nothing, then collapsed, for the same reason the control characters are
     * replaced rather than deleted: deleting joins the words on either side, and `Acme 😀 Team`
     * should read `Acme Team` rather than `Acme  Team` or `AcmeTeam`.
     */
    private static function stripAstral(string $name): string
    {
        return self::collapseWhitespace(preg_replace('/[\x{10000}-\x{10FFFF}]+/u', ' ', $name) ?? $name);
    }

    /**
     * What the reader ends up seeing, rather than what was typed.
     *
     * The email templates escape with `double_encode: false`, so that names stored before the
     * columns held raw text still render correctly. The cost is that entity text passes through to
     * the recipient and is turned back into characters by their mail client's HTML parser — so a
     * rule that judges a name, rather than printing it, has to judge the decoded form.
     */
    private static function asRead(string $name): string
    {
        return html_entity_decode($name, self::DECODE_FLAGS, 'UTF-8');
    }
}
