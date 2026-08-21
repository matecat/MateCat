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

    /**
     * The values that must never be escaped, because they are already markup. Anything added here
     * is a decision to write unescaped text into an email and should be argued for in review.
     *
     * @var list<string>
     */
    private const array RAW_ALLOWLIST = ['messageBody'];

    private const string PAYLOAD = '<script>alert(1)</script><a href="https://evil.com">click</a>';

    /**
     * @return array<string, array{string}>
     */
    public static function emailTemplates(): array
    {
        $root      = __DIR__ . '/../../../../../lib/View/Emails';
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
     * The opt-out has to stay small and deliberate. A template writing `->value()` or `EmailValue::raw`
     * is asking for unescaped output, which is exactly the state this whole change removed.
     */
    #[Test]
    #[DataProvider('emailTemplates')]
    public function noTemplateOptsOutOfEscaping(string $template): void
    {
        $contents = file_get_contents($template);
        $this->assertIsString($contents, "cannot read $template");

        foreach (['EmailValue::raw', '->value()'] as $escapeHatch) {
            $this->assertStringNotContainsString(
                $escapeHatch,
                $contents,
                basename($template) . " uses $escapeHatch. The only value permitted to be raw is "
                . implode(', ', self::RAW_ALLOWLIST) . ', and it is marked raw where it is built,'
                . ' not in a template'
            );
        }
    }

    /**
     * A payload written through the wrapper comes out as text, once.
     */
    #[Test]
    public function aWrappedValueIsEscapedExactlyOnce(): void
    {
        // No hostname in this one: a value that round-trips has to be one the defanger leaves
        // alone, or the assertion would be testing two transformations at once. Defanging has its
        // own tests.
        $wrapped = EmailValue::wrapAll(['name' => '<script>alert(1)</script> O\'Brien & Sons <Ltd>']);

        $rendered = (string)$wrapped['name'];

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
     * `$team['name']` are both array members, so wrapping only the top level would have changed
     * nothing where it mattered.
     */
    #[Test]
    public function nestedValuesAreEscapedToo(): void
    {
        $wrapped = EmailValue::wrapAll(['sender' => ['first_name' => self::PAYLOAD]]);

        $rendered = (string)$wrapped['sender']['first_name'];

        $this->assertStringNotContainsString('<script', $rendered);
        $this->assertStringNotContainsString('<a href', $rendered);
    }

    /**
     * The legacy half of the contract, and the reason `double_encode` is false: names stored before
     * the column held raw text are entity-encoded, and encoding them again is what the reader sees.
     */
    #[Test]
    public function alreadyEncodedTextIsNotEncodedAgain(): void
    {
        $wrapped = EmailValue::wrapAll(['name' => 'Ben &amp; Jerry&#39;s']);

        $this->assertSame('Ben &amp; Jerry&#39;s', (string)$wrapped['name']);
    }

    /**
     * The escape hatch still works, or the layout would print the tags of its own message body.
     */
    #[Test]
    public function rawValuesPassThroughUntouched(): void
    {
        $markup = '<p>an already rendered message</p>';

        $this->assertSame($markup, (string)EmailValue::raw($markup));
    }

    /**
     * The wiring, through a real email rather than through the wrapper.
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
        $sender             = new UserStruct();
        $sender->uid        = 1;
        $sender->email      = 'sender@example.com';
        $sender->first_name = self::PAYLOAD;
        $sender->last_name  = 'Lovelace';

        $team       = new TeamStruct();
        $team->id   = 1;
        $team->name = 'Harmless Team';

        $email  = new InvitedToTeamEmail($sender, 'invited@example.com', $team);
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');
        $body   = (string)$method->invoke($email);

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('<a href="https://evil.com"', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body, 'the payload must survive as text');
    }

    /**
     * The escape hatch for a single value, as opposed to the key list: escaped, never defanged.
     */
    #[Test]
    public function verbatimEscapesButDoesNotDefang(): void
    {
        $value = EmailValue::verbatim('https://dev.matecat.com/x?a=1&b=<2>');

        $this->assertSame('https://dev.matecat.com/x?a=1&amp;b=&lt;2&gt;', (string)$value);
    }

    /**
     * `value()` exists so a template can work with the value rather than write it — measure it,
     * compare it, pass it on — and it has to hand back what was given, not a transformed copy.
     */
    #[Test]
    public function theUnderlyingValueIsReadableUntransformed(): void
    {
        $wrapped = EmailValue::wrapAll(['name' => 'evil.com <b>']);

        $this->assertSame('evil.com <b>', $wrapped['name']->value());
        $this->assertSame('evil[.]com &lt;b&gt;', (string)$wrapped['name'], 'writing it still treats it');
    }

    /**
     * A value already wrapped keeps its own treatment instead of being wrapped again, which is what
     * lets `raw()` survive `wrapAll()` — the layout's message body is marked before it gets there.
     */
    #[Test]
    public function anAlreadyWrappedValueIsNotWrappedAgain(): void
    {
        $raw = EmailValue::raw('<p>rendered</p>');

        $wrapped = EmailValue::wrapAll(['messageBody' => $raw, 'nested' => ['inner' => $raw]]);

        $this->assertSame($raw, $wrapped['messageBody']);
        $this->assertSame('<p>rendered</p>', (string)$wrapped['nested']['inner']);
    }

    /**
     * `isset($user['email'])` in a template has to answer about the underlying array, and answer
     * false rather than fatally when the value is not one.
     */
    #[Test]
    public function presenceIsReportedForNestedKeys(): void
    {
        $wrapped = EmailValue::wrapAll(['user' => ['email' => 'bob@example.com'], 'name' => 'plain']);

        $this->assertTrue(isset($wrapped['user']['email']));
        $this->assertFalse(isset($wrapped['user']['missing']));
        $this->assertFalse(isset($wrapped['name']['anything']), 'a scalar has no offsets');
        $this->assertNull($wrapped['user']['missing']->value());
    }

    /**
     * Templates read; they do not assign. A write through the wrapper would produce a value whose
     * treatment nobody had decided, so it is ignored rather than honoured.
     */
    #[Test]
    public function writingThroughTheWrapperChangesNothing(): void
    {
        // Through the wrapper, not through the array wrapAll() returned: assigning to that array
        // replaces the wrapper rather than reaching it.
        $user = EmailValue::wrapAll(['user' => ['company' => 'Acme.com']])['user'];

        $user['company'] = 'replaced';
        $user['added']   = 'new';
        unset($user['company']);

        $this->assertSame('Acme[.]com', (string)$user['company'], 'the value is unchanged');
        $this->assertNull($user['added']->value(), 'and nothing was added');
    }

    /**
     * A template looping a wrapped list gets wrapped members, or the treatment would stop at the
     * first `foreach`.
     */
    #[Test]
    public function iteratingAWrappedListYieldsWrappedMembers(): void
    {
        $wrapped = EmailValue::wrapAll(['rows' => ['Acme.com', 'plain text']]);

        $seen = [];
        foreach ($wrapped['rows'] as $key => $row) {
            $seen[$key] = (string)$row;
        }

        $this->assertSame(['Acme[.]com', 'plain text'], $seen);
    }

    /**
     * Iterating something that is not a list yields nothing rather than raising.
     */
    #[Test]
    public function iteratingAScalarYieldsNothing(): void
    {
        $wrapped = EmailValue::wrapAll(['name' => 'plain']);

        $this->assertSame([], iterator_to_array($wrapped['name']));
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
        $email  = $this->invitationFor("O'Brien & Sons <Ltd>");
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildTxtMessage');
        $html   = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');

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
        $email  = $this->invitationFor('Line&lt;br&gt;Break');
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildTxtMessage');
        $html   = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');

        $text = (string)$method->invoke($email, (string)$html->invoke($email));

        $this->assertStringContainsString('Line<br>Break', $text);
    }

    private function invitationFor(string $teamName): InvitedToTeamEmail
    {
        $sender             = new UserStruct();
        $sender->uid        = 1;
        $sender->email      = 'sender@example.com';
        $sender->first_name = 'Ada';
        $sender->last_name  = 'Lovelace';

        $team       = new TeamStruct();
        $team->id   = 1;
        $team->name = $teamName;

        return new InvitedToTeamEmail($sender, 'invited@example.com', $team);
    }

    /**
     * A value the template cannot write — an array reached by mistake — emits nothing rather than
     * "Array" plus a conversion notice.
     */
    #[Test]
    public function anUnwritableValueEmitsNothing(): void
    {
        $wrapped = EmailValue::wrapAll(['rows' => [['a' => 1]]]);

        $this->assertSame('', (string)$wrapped['rows']);
    }

}
