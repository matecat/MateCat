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
     * U+200D (ZERO WIDTH JOINER) is exempt. It is a format character, but it is also what holds an
     * emoji sequence together, and a name is allowed to contain one.
     *
     * Everything else is preserved verbatim.
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        // An invalid encoding cannot be normalised, measured or compared: `preg_replace` with the
        // `u` modifier abandons the subject and returns null, `mb_strlen` counts something other
        // than characters, and `Normalizer` refuses. Returning empty hands the caller's own
        // non-empty check the refusal, rather than letting a mangled byte sequence through.
        if (!mb_check_encoding($raw, 'UTF-8')) {
            return '';
        }

        // The lookahead sits inside the repeated group rather than in front of the class, so it is
        // tested once per character. In front of a `+` quantifier it would only guard the first
        // character of a run, and `ZWSP ZWJ` would have the joiner swallowed by the same match.
        $name = preg_replace('/(?:(?!\x{200D})[\p{Cc}\p{Cf}\p{Zl}\p{Zp}])+/u', ' ', $raw) ?? $raw;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name);

        // Composed form last, so what gets measured, stored and compared is one spelling. `Équipe`
        // written with a precomposed É and `Équipe` written with E + U+0301 are the same name to a
        // reader, and a UNIQUE(uid, name) index that cannot see that lets a second template be
        // created under a name that already exists.
        $normalized = Normalizer::normalize($name, Normalizer::FORM_C);

        return $normalized !== false ? $normalized : $name;
    }

    /**
     * Normalise, then cut to fit rather than refuse.
     *
     * For the paths where the name is not something the user is currently typing and a 400 would
     * break the request instead of correcting it — the OAuth callback, where the name comes from
     * the identity provider and refusing it would refuse the login.
     */
    public static function normalizeAndTruncate(?string $raw, int $storedMax): string
    {
        return mb_substr(self::normalize($raw), 0, $storedMax);
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
     * Two caps, because a name is measured twice for two different reasons.
     *
     * `$storedMax` is the column width: the raw string is what goes into the row, so that is what
     * has to fit. `$readableMax` is what the reader sees, which is not the same count — names
     * written before the columns held raw text are still entity-encoded, and measuring those on the
     * raw string rejected a name of some sixty visible characters for length.
     *
     * @throws InvalidArgumentException
     */
    public static function assertLength(string $name, string $param, int $storedMax, int $readableMax): void
    {
        if (mb_strlen($name) > $storedMax) {
            throw new InvalidArgumentException(
                self::REFUSAL_PREFIX . $param . ' must be at most ' . $storedMax . ' characters',
                400
            );
        }

        if (mb_strlen(self::asRead($name)) > $readableMax) {
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
     * A scheme (`https://`, `javascript:`) or a `www.` prefix is the only shape that is
     * unambiguously an address rather than a word, so no legitimate name carries one and this costs
     * nobody anything.
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
        if (preg_match('~[a-z][a-z0-9+.-]*://|\bwww\.~i', self::asRead($name)) !== 0) {
            throw new InvalidArgumentException(
                self::REFUSAL_PREFIX . $param . ' cannot contain a URL',
                400
            );
        }
    }

    /**
     * The whole pipeline, in the order the checks have to run: normalise, refuse empty, refuse
     * over-length, then refuse a link.
     *
     * The decode inside the length and link checks has to come after normalisation and before the
     * caps — a scheme smuggled as `https&#58;//evil.com` satisfies a rule that reads the raw string,
     * and arrives as a live URL because the mail client decodes it.
     *
     * @param bool $refuseUrl false for a field that is never quoted to a stranger — a person's own
     *                        name, which is defanged at the email sink like any other value but has
     *                        no reason to be refused at the door.
     *
     * @throws InvalidArgumentException
     */
    public static function validated(
        ?string $raw,
        string $param,
        int $storedMax,
        int $readableMax,
        bool $refuseUrl = true
    ): string {
        $name = self::normalize($raw);

        self::assertNotEmpty($name, $param);
        self::assertLength($name, $param, $storedMax, $readableMax);

        if ($refuseUrl) {
            self::assertNoUrl($name, $param);
        }

        return $name;
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
