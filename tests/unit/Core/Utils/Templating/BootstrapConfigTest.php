<?php

namespace Matecat\Core\Utils\Templating;

use JsonSerializable;
use Matecat\TestHelpers\AbstractTest;
use PHPTAL;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;
use Utils\Templating\BootstrapConfig;
use Utils\Templating\PHPTalBoolean;
use Utils\Templating\PHPTalMap;
use Utils\Templating\PHPTalString;

class BootstrapConfigTest extends AbstractTest
{

    private PHPTAL $view;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view = new PHPTAL();
    }

    /**
     * @return BootstrapConfig
     */
    private function config(): BootstrapConfig
    {
        return new BootstrapConfig($this->view);
    }

    /**
     * @return array<string, mixed>
     */
    private function decoded(): array
    {
        return json_decode((string)$this->config(), true);
    }

    #[Test]
    public function aViewWithNoVariablesRendersAnEmptyDocument(): void
    {
        // PHPTAL's context always carries `repeat`, `_xmlDeclaration` and `_docType`; an empty
        // document is the proof that none of them is mistaken for a template variable.
        $this->assertSame('{}', (string)$this->config());
    }

    #[Test]
    public function scalarsAndNullAreCarried(): void
    {
        $this->view->set('name', 'Acme');
        $this->view->set('count', 7);
        $this->view->set('ratio', 1.5);
        $this->view->set('enabled', true);
        $this->view->set('missing', null);

        $this->assertSame(
            ['name' => 'Acme', 'count' => 7, 'ratio' => 1.5, 'enabled' => true, 'missing' => null],
            $this->decoded()
        );
    }

    #[Test]
    public function arraysOfScalarsAreCarried(): void
    {
        $this->view->set('codes', ['translated', 'airbnb']);

        $this->assertSame(['codes' => ['translated', 'airbnb']], $this->decoded());
    }

    #[Test]
    public function nestedArraysAreCarried(): void
    {
        $this->view->set('tree', ['a' => ['b' => ['c' => 1]]]);

        $this->assertSame(['tree' => ['a' => ['b' => ['c' => 1]]]], $this->decoded());
    }

    #[Test]
    public function anEmptyArrayIsCarried(): void
    {
        $this->view->set('nothing', []);

        $this->assertSame(['nothing' => []], $this->decoded());
    }

    #[Test]
    public function phpTalBooleanBecomesARealBooleanRatherThanTheStringItRendersAs(): void
    {
        $this->view->set('yes', new PHPTalBoolean(true));
        $this->view->set('no', new PHPTalBoolean(false));

        $this->assertSame(['yes' => true, 'no' => false], $this->decoded());
    }

    #[Test]
    public function phpTalStringIsCarriedUnwrappedRatherThanAsTheQuotedLiteralItRendersAs(): void
    {
        $this->view->set('team_name', new PHPTalString('Acme Team'));

        // Not '"Acme Team"': the class supplies its own quotes when it stands alone in a template,
        // and those must not survive into a document that is quoting the value itself.
        $this->assertSame(['team_name' => 'Acme Team'], $this->decoded());
    }

    #[Test]
    public function phpTalMapIsCarriedAsItsContents(): void
    {
        $this->view->set('user_plugins', new PHPTalMap(['translated', 'airbnb']));

        $this->assertSame(['user_plugins' => ['translated', 'airbnb']], $this->decoded());
    }

    #[Test]
    public function anArbitraryJsonSerializableIsCarried(): void
    {
        $this->view->set('custom', new class implements JsonSerializable {
            /**
             * @return array<string, int>
             */
            public function jsonSerialize(): array
            {
                return ['answer' => 42];
            }
        });

        $this->assertSame(['custom' => ['answer' => 42]], $this->decoded());
    }

    #[Test]
    public function anObjectThatDeclaresNoSerialisedFormIsDropped(): void
    {
        // The reason this filter exists: json_encode() emits every public property of an object it
        // does not know how to serialise, so a struct would publish columns the page never asked for.
        $struct = new stdClass();
        $struct->id = 5;
        $struct->password = 'hunter2';

        $this->view->set('project', $struct);
        $this->view->set('kept', 'yes');

        $config = (string)$this->config();

        $this->assertSame(['kept' => 'yes'], json_decode($config, true));
        $this->assertStringNotContainsString('hunter2', $config);
    }

    #[Test]
    public function anArrayIsDroppedWholeWhenAnyElementDeclaresNoSerialisedForm(): void
    {
        $this->view->set('mixed', ['safe', new stdClass()]);
        $this->view->set('kept', 'yes');

        $this->assertSame(['kept' => 'yes'], $this->decoded());
    }

    #[Test]
    public function anArrayIsDroppedWholeWhenAnObjectIsNestedDeeply(): void
    {
        $this->view->set('mixed', ['a' => ['b' => ['c' => new stdClass()]]]);

        $this->assertSame([], $this->decoded());
    }

    #[Test]
    public function theNonceIsNeverCarried(): void
    {
        // A nonce the page can read back is a nonce an injected script can read back, which is the
        // one thing it exists to prevent.
        $this->view->set('x_nonce_unique_id', 'a-real-nonce');

        $config = (string)$this->config();

        $this->assertSame('{}', $config);
        $this->assertStringNotContainsString('a-real-nonce', $config);
    }

    #[Test]
    public function theNonceIsNeverCarriedInsideInjectedMarkup(): void
    {
        // Excluding the variable that holds the nonce is not the same as keeping the nonce out: the
        // dev server stamps it onto every tag it injects, so vite_html carries the identical value
        // under a name the exclusion list would not have caught.
        $this->view->set('vite_html', '<script type="module" nonce="a-real-nonce" src="/@vite/client"></script>');

        $config = (string)$this->config();

        $this->assertSame('{}', $config);
        $this->assertStringNotContainsString('a-real-nonce', $config);
    }

    #[Test]
    public function theMarkupAppendListsAreNeverCarried(): void
    {
        $this->view->set('footer_js', ['<script src="a.js"></script>']);
        $this->view->set('config_js', ['var x = 1;']);

        $this->assertSame('{}', (string)$this->config());
    }

    #[Test]
    public function flashMessagesAreLeftToTheFooterMacro(): void
    {
        // The macro emits them as config.flash_messages, which is the name the page reads. Carrying
        // them here as well would put the same payload on the page twice, under two names.
        $this->view->set('flashMessages', ['service' => ['invitation sent']]);

        $this->assertSame('{}', (string)$this->config());
    }

    #[Test]
    public function phpTalOwnContextPropertiesAreNeverCarried(): void
    {
        // `repeat` is the only one a template could collide with: PHPTAL_Context::__set() refuses any
        // name beginning with an underscore, so the other two cannot be set as template variables.
        $this->view->getContext()->repeat = 'clobbered';

        $this->assertSame('{}', (string)$this->config());
    }

    #[Test]
    public function theDocumentDoesNotContainItself(): void
    {
        // The view holds the BootstrapConfig under `config_json`. It declares no serialised form, so
        // the same filter that drops structs drops it, and __toString() cannot recurse.
        $config = $this->config();
        $this->view->set('config_json', $config);
        $this->view->set('kept', 'yes');

        $this->assertSame(['kept' => 'yes'], json_decode((string)$config, true));
    }

    #[Test]
    public function valuesAssignedAfterConstructionAreCarried(): void
    {
        // The whole point of holding the view rather than a copy of its values: the controller builds
        // this object when it sets the view, and everything assigned later — including by a view
        // decorator — is still in the document.
        $config = $this->config();
        $this->view->set('late', 'arrived');

        $this->assertSame(['late' => 'arrived'], json_decode((string)$config, true));
    }

    #[Test]
    public function markupDelimitersAreEscapedSoAValueCannotCloseTheScriptBlock(): void
    {
        $this->view->set('team_name', '</script><script>alert(1)</script>');

        $config = (string)$this->config();

        $this->assertStringNotContainsString('</script>', $config);
        $this->assertStringNotContainsString('<', $config);
        $this->assertStringNotContainsString('>', $config);
        $this->assertSame('</script><script>alert(1)</script>', json_decode($config, true)['team_name']);
    }

    #[Test]
    public function quotesAndAmpersandsAreEscaped(): void
    {
        $this->view->set('quoted', 'O\'Brien "quoted" & co');

        $config = (string)$this->config();

        $this->assertStringNotContainsString("'", $config);
        $this->assertStringNotContainsString('&', $config);
        $this->assertSame('O\'Brien "quoted" & co', json_decode($config, true)['quoted']);
    }

    #[Test]
    public function lineSeparatorsAreEscapedSoTheDocumentStaysValidJavaScript(): void
    {
        // U+2028 and U+2029 are legal unescaped inside a JSON string but were illegal inside a
        // JavaScript string literal before ES2019 — the one way JSON is not a subset of JavaScript.
        $this->view->set('separators', "a\u{2028}b\u{2029}c");

        $config = (string)$this->config();

        $this->assertStringNotContainsString("\u{2028}", $config);
        $this->assertStringNotContainsString("\u{2029}", $config);
        $this->assertSame("a\u{2028}b\u{2029}c", json_decode($config, true)['separators']);
    }

    #[Test]
    public function nonAsciiTextIsCarriedUnescaped(): void
    {
        $this->view->set('name', 'café 東京');

        $this->assertStringContainsString('café 東京', (string)$this->config());
    }

    #[Test]
    public function anUnencodableValueThrowsRatherThanEmittingAnEmptyDocument(): void
    {
        // Leaving the page silently unconfigured would be the worse failure: every script reading
        // `config` would fail somewhere further along, far from the cause.
        $this->view->set('broken', "\xB1\x31");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to encode the page configuration');

        (string)$this->config();
    }

}
