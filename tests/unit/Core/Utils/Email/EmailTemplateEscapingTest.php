<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\EmailValue;
use Utils\Email\InvitedToTeamEmail;

/**
 * The rule that replaced remembering to escape.
 *
 * Counted on 2026-08-13: eighty of the eighty-seven interpolations under `lib/View/Emails` wrote
 * their value raw, the seven exceptions being the team names added by `d2d207f880`. One of the raw
 * ones was reachable in principle — the inviter's name in `email_invited_to_team.html`, which
 * rendered a live `<a href>` when fed markup — and was only unexploitable because every writer of
 * that column sanitises. Escaping now happens once, in {@see EmailValue}, and these tests exist so
 * it keeps happening: the sweep stops a template escaping by hand and double-encoding, and the
 * render proves a payload comes out inert.
 */
class EmailTemplateEscapingTest extends AbstractTest
{

    private const string PAYLOAD = '<script>alert(1)</script><a href="https://evil.com">click</a>';

    /**
     * @return array<string, array{string}>
     */
    public static function emailTemplates(): array
    {
        $root = __DIR__ . '/../../../../../lib/View/Emails';
        $templates = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $templates[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
            }
        }

        ksort($templates);

        return $templates;
    }

    /**
     * Hand-escaping is now a bug rather than diligence: the value arrives escaped, so a template
     * that escapes it again shows the reader "&amp;lt;" where someone wrote "<". This is the check
     * that would have caught the double-encoding regression the existing legacy-name tests caught
     * for one field only.
     */
    #[Test]
    #[DataProvider('emailTemplates')]
    public function noTemplateEscapesByHand(string $template): void
    {
        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        $this->assertStringNotContainsString(
            'htmlspecialchars',
            $contents,
            basename($template) . ' escapes a value by hand. Values reach templates already escaped'
            . ' through EmailValue; escaping again double-encodes what the reader sees'
        );
    }

    /**
     * The message body is the one value printed unescaped, and only the layouts print it. A template
     * that printed `$messageBody`, or reached for `EmailValue` itself, would be writing markup nobody
     * escaped.
     */
    #[Test]
    #[DataProvider('emailTemplates')]
    public function onlyTheLayoutsPrintUnescapedMarkup(string $template): void
    {
        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        $this->assertStringNotContainsString('EmailValue', $contents, basename($template) . ' uses EmailValue');

        if (!in_array(basename($template), ['skeleton.html', 'empty_skeleton.html'], true)) {
            $this->assertStringNotContainsString(
                '$messageBody',
                $contents,
                basename($template) . ' prints $messageBody, which only a layout may embed'
            );
        }
    }

    /**
     * Every value a template writes into an `href` is a link the email exists to offer, so it has to
     * be listed in {@see \Utils\Email\AbstractEmail::verbatimKeys()}. An unlisted one is defanged and
     * the reader gets `https[:]//…` instead of a working link: `manageUrl`, `project_url` and
     * `segmentUrl` shipped that way and were only found by manual testing.
     */
    #[Test]
    #[DataProvider('emailTemplates')]
    public function everyLinkTargetIsExemptFromDefanging(string $template): void
    {
        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        $verbatimKeys = (new ReflectionMethod(InvitedToTeamEmail::class, 'verbatimKeys'))
            ->invoke($this->invitationFor('Team'));

        // `$sender['email']` is judged by the key read, since that is what EmailValue matches on.
        preg_match_all('~href="<\?=\s*\$(\w+)(?:\[\s*[\'"](\w+)[\'"]\s*])?~', $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = ($match[2] ?? '') !== '' ? $match[2] : $match[1];

            $this->assertContains(
                $key,
                $verbatimKeys,
                basename($template) . " links to \$$key, which is not in AbstractEmail::verbatimKeys()."
                . ' It will be defanged and the link will not work'
            );
        }
    }

    /**
     * A payload comes out as text, once.
     */
    #[Test]
    public function aValueIsEscapedExactlyOnce(): void
    {
        // No hostname in this one: a value that round-trips has to be one the defanger leaves
        // alone, or the assertion would be testing two transformations at once. Defanging has its
        // own tests.
        $rendered = EmailValue::escapeAll(['name' => '<script>alert(1)</script> O\'Brien & Sons <Ltd>'])['name'];

        $this->assertStringNotContainsString('<script', $rendered);
        $this->assertStringNotContainsString('<a href', $rendered);
        $this->assertStringNotContainsString('&amp;lt;', $rendered, 'must not double-encode');
        $this->assertSame(
            '<script>alert(1)</script> O\'Brien & Sons <Ltd>',
            html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }

    /**
     * Nested values are the ones that were actually raw in production — `$sender['first_name']` and
     * `$team['name']` are both array members, so escaping only the top level would have changed
     * nothing where it mattered.
     */
    #[Test]
    public function nestedValuesAreEscapedToo(): void
    {
        $rendered = EmailValue::escapeAll(['sender' => ['first_name' => self::PAYLOAD]])['sender']['first_name'];

        $this->assertStringNotContainsString('<script', $rendered);
        $this->assertStringNotContainsString('<a href', $rendered);
    }

    /**
     * Entity text is decoded and then escaped once, so what reaches the reader is the characters
     * rather than either the entities or a double-encoded "&amp;amp;". The entity spellings differ
     * between input and output — `&#39;` in, `&apos;` out — because the output is escaped from the
     * decoded characters and not copied from the input.
     */
    #[Test]
    public function alreadyEncodedTextIsDecodedThenEscapedOnce(): void
    {
        $rendered = EmailValue::escapeAll(['name' => 'Ben &amp; Jerry&#39;s'])['name'];

        $this->assertSame('Ben &amp; Jerry&apos;s', $rendered);
        $this->assertStringNotContainsString('&amp;amp;', $rendered, 'must not double-encode');
        $this->assertSame('Ben & Jerry\'s', html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * The point of escaping the decoded form. An entity-encoded colon is invisible to the defanger
     * until the text is decoded, and the recipient's mail client decodes it either way — so before
     * the decode this arrived as a live `https://evil.com`.
     */
    #[Test]
    public function aSchemeSmuggledAsEntitiesIsStillDefanged(): void
    {
        $escaped = EmailValue::escapeAll(['name' => 'Visit https&#58;//evil&#46;com now']);

        $this->assertSame('Visit https[:]//evil[.]com now', $escaped['name']);
    }

    /**
     * The wiring, through a real email rather than through EmailValue alone.
     *
     * Deliberately asserted on the *inviter's name* and not the team name. The team name already had
     * tests, which is why it was the one field of three that was escaped; this is the field that had
     * none and rendered a live `<a href="https://evil.com">` when fed markup. Reachable only in
     * principle — `SignupController` and `UserController` both strip non-letters from a name before
     * it is stored — but a defence that depends on every current writer staying careful is the same
     * arrangement that produced the gap.
     */
    #[Test]
    public function aPayloadInAnUntestedFieldIsEscapedInARenderedEmail(): void
    {
        $sender = new UserStruct();
        $sender->uid = 1;
        $sender->email = 'sender@example.com';
        $sender->first_name = self::PAYLOAD;
        $sender->last_name = 'Lovelace';

        $team = new TeamStruct();
        $team->id = 1;
        $team->name = 'Harmless Team';

        $email = new InvitedToTeamEmail($sender, 'invited@example.com', $team);
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');
        $body = (string)$method->invoke($email);

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('<a href="https://evil.com"', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body, 'the payload must survive as text');
    }

    /**
     * Every member of a list is escaped, so the treatment does not stop at the first `foreach`.
     */
    #[Test]
    public function everyMemberOfAListIsEscaped(): void
    {
        $escaped = EmailValue::escapeAll(['rows' => ['Acme.com', 'plain text']]);

        $this->assertSame(['Acme[.]com', 'plain text'], $escaped['rows']);
    }

    /**
     * The plain-text part of an email is built by stripping the HTML one, so everything escaping did
     * has to be undone again — with the same flags, or it is only partly undone.
     *
     * The apostrophe is the case that matters: values are escaped as HTML5, where it becomes
     * `&apos;`, and PHP decodes as HTML 4.01 by default, which has no such entity. A team called
     * `O'Brien` reached the reader as `O&apos;Brien`.
     */
    #[Test]
    public function theTextPartUndoesEscapingRatherThanShowingEntities(): void
    {
        $email = $this->invitationFor("O'Brien & Sons <Ltd>");
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildTxtMessage');
        $html = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');

        $text = (string)$method->invoke($email, (string)$html->invoke($email));

        $this->assertStringContainsString("O'Brien & Sons <Ltd>", $text);
        $this->assertStringNotContainsString('&apos;', $text);
        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringNotContainsString('&lt;', $text);
    }

    /**
     * Decoding happens after the tags have been dealt with, so a value that merely contains markup
     * does not become markup: what a user typed stays what they typed.
     */
    #[Test]
    public function textTypedAsMarkupStaysTextInTheTextPart(): void
    {
        $email = $this->invitationFor('Line&lt;br&gt;Break');
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildTxtMessage');
        $html = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');

        $text = (string)$method->invoke($email, (string)$html->invoke($email));

        $this->assertStringContainsString('Line<br>Break', $text);
    }

    private function invitationFor(string $teamName): InvitedToTeamEmail
    {
        $sender = new UserStruct();
        $sender->uid = 1;
        $sender->email = 'sender@example.com';
        $sender->first_name = 'Ada';
        $sender->last_name = 'Lovelace';

        $team = new TeamStruct();
        $team->id = 1;
        $team->name = $teamName;

        return new InvitedToTeamEmail($sender, 'invited@example.com', $team);
    }

}
