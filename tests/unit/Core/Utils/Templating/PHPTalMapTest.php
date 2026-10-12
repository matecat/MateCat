<?php

namespace Matecat\Core\Utils\Templating;

use Matecat\TestHelpers\AbstractTest;
use PHPTAL;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Templating\BootstrapConfig;
use Utils\Templating\PHPTalMap;

class PHPTalMapTest extends AbstractTest
{
    #[Test]
    public function constructorConvertsNestedArraysToMaps(): void
    {
        $map = new PHPTalMap(['key' => ['nested' => 'value']]);

        $this->assertInstanceOf(PHPTalMap::class, $map['key']);
        $this->assertSame('value', $map['key']['nested']);
    }

    #[Test]
    public function constructorHandlesNumericKeys(): void
    {
        $map = new PHPTalMap([['a' => 1], ['b' => 2]]);

        $this->assertInstanceOf(PHPTalMap::class, $map[0]);
        $this->assertSame(1, $map[0]['a']);
    }

    #[Test]
    public function constructorHandlesScalarValues(): void
    {
        $map = new PHPTalMap(['name' => 'test', 'count' => 42]);

        $this->assertSame('test', $map['name']);
        $this->assertSame(42, $map['count']);
    }

    #[Test]
    public function toStringReturnsJson(): void
    {
        $map = new PHPTalMap(['key' => 'value']);

        $this->assertSame('{"key":"value"}', (string)$map);
    }

    #[Test]
    public function jsonSerializeReturnsStorage(): void
    {
        $map = new PHPTalMap(['a' => 1]);

        $serialized = $map->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertSame(1, $serialized['a']);
    }

    #[Test]
    public function arrayAccessSetAndGet(): void
    {
        $map = new PHPTalMap();
        $map['key'] = 'value';

        $this->assertSame('value', $map['key']);
    }

    #[Test]
    public function arrayAccessUnset(): void
    {
        $map = new PHPTalMap(['key' => 'value']);

        unset($map['key']);

        $this->assertNull($map['key']);
    }

    #[Test]
    public function magicGetSet(): void
    {
        $map = new PHPTalMap();
        $map->foo = 'bar';

        $this->assertSame('bar', $map->foo);
    }

    #[Test]
    public function magicGetReturnsNullForMissing(): void
    {
        $map = new PHPTalMap();

        $this->assertNull($map->nonexistent);
    }

    #[Test]
    public function emptyMapToString(): void
    {
        $map = new PHPTalMap();

        $this->assertSame('[]', (string)$map);
    }

    /**
     * A quality-model category label, which a user types and which reaches the cattool page inside a
     * script block through this class. The payload carries every character that could end the
     * element or the literal it sits in.
     */
    private const string HOSTILE_LABEL = '</script><img src=x onerror=alert(1)>He said "it\'s" A&B';

    /**
     * The shape CattoolController assigns to `lqa_nested_categories`: the serialised model, one level
     * of nesting per category and another per severity, all of it wrapped by the constructor into
     * further maps.
     *
     * @return array<string, mixed>
     */
    private static function nestedCategories(): array
    {
        return [
            'categories' => [
                [
                    'id' => 1,
                    'label' => self::HOSTILE_LABEL,
                    'severities' => [
                        ['id' => 1, 'label' => self::HOSTILE_LABEL, 'penalty' => 1],
                    ],
                ],
            ],
        ];
    }

    /**
     * The shape assigned to `lqa_flat_categories`: a plain list of category rows.
     *
     * @return list<array<string, mixed>>
     */
    private static function flatCategories(): array
    {
        return [
            ['id' => 1, 'label' => self::HOSTILE_LABEL, 'options' => ['note' => self::HOSTILE_LABEL]],
        ];
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function categoryShapes(): array
    {
        return [
            'lqa_nested_categories' => [self::nestedCategories()],
            'lqa_flat_categories' => [self::flatCategories()],
        ];
    }

    /**
     * The direct interpolation path — `${structure lqa_flat_categories}` — where the map encodes
     * itself. Nothing may survive that could close the script element or the string literal around
     * it, at any depth, and the value must still arrive intact for the page that reads it.
     */
    #[Test]
    #[DataProvider('categoryShapes')]
    public function aHostileCategoryLabelIsInertInTheEncodedMap(array $shape): void
    {
        $encoded = (string)(new PHPTalMap($shape));

        $this->assertStringNotContainsString('<', $encoded);
        $this->assertStringNotContainsString('&', $encoded);
        $this->assertStringNotContainsString("'", $encoded);
        $this->assertStringNotContainsString('</script', strtolower($encoded));

        // Inert, not mangled: the browser decodes it back to what the user typed.
        $decoded = json_decode($encoded, true);
        $this->assertSame($shape, $decoded, 'the escaping must be reversible by the JSON parser');
    }

    /**
     * The path the cattool page actually takes: the maps are view variables, and the template
     * interpolates the whole configuration document rather than each variable. The escaping is then
     * BootstrapConfig's, applied to a structure whose leaves are these maps — so this asserts the
     * flags that govern in production, which are not the ones the test above exercises.
     */
    #[Test]
    public function aHostileCategoryLabelIsInertInTheConfigurationDocument(): void
    {
        $view = new PHPTAL();
        $view->lqa_nested_categories = new PHPTalMap(self::nestedCategories());
        $view->lqa_flat_categories = new PHPTalMap(self::flatCategories());

        $document = (string)(new BootstrapConfig($view));

        $this->assertStringNotContainsString('<', $document);
        $this->assertStringNotContainsString('&', $document);
        $this->assertStringNotContainsString("'", $document);

        $decoded = json_decode($document, true);
        $this->assertSame(self::nestedCategories(), $decoded['lqa_nested_categories']);
        $this->assertSame(self::flatCategories(), $decoded['lqa_flat_categories']);
    }

    /**
     * End to end through PHPTAL, in the CDATA-marked script block the cattool page uses. The two
     * assertions above are about encoders; this one is about what a browser is handed, and it is the
     * one that would catch PHPTAL deciding to emit the value some other way.
     */
    #[Test]
    public function aHostileCategoryLabelStaysInsideTheScriptBlockWhenRendered(): void
    {
        $templatePath = tempnam(sys_get_temp_dir(), 'phptal_') . '.html';
        file_put_contents(
            $templatePath,
            "<html><body>\n"
            . "<script type=\"text/javascript\">\n"
            . "/*<![CDATA[*/\n"
            . "var categories = \${structure lqa_flat_categories};\n"
            . "/*]]>*/\n"
            . "</script>\n"
            . "</body></html>"
        );

        try {
            $view = new PHPTAL($templatePath);
            $view->setOutputMode(PHPTAL::HTML5);
            $view->lqa_flat_categories = new PHPTalMap(self::flatCategories());

            $rendered = $view->execute();
        } finally {
            unlink($templatePath);
        }

        // One script element, closed where the template closes it and nowhere else.
        $this->assertSame(1, substr_count(strtolower($rendered), '</script>'));
        $this->assertStringNotContainsString('<img', strtolower($rendered));

        preg_match('/var categories = (.*);/', $rendered, $matches);
        $this->assertCount(2, $matches, 'the categories must render as a literal');
        $this->assertSame(self::flatCategories(), json_decode($matches[1], true));
    }
}
