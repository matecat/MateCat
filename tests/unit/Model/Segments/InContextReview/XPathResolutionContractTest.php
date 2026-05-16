<?php

namespace unit\Model\Segments\InContextReview;

use DOMDocument;
use DOMXPath;
use Matecat\XliffParser\XliffParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Contract test: every trans-unit with restype="x-path" in the test XLIFF
 * must resolve against its context HTML file and produce the expected text.
 *
 * This guarantees the XLIFF resname values and the context HTML stay in sync.
 */
class XPathResolutionContractTest extends AbstractTest
{
    private const string FIXTURES_DIR = TEST_DIR . '/resources/files/in-context-review/';

    private const array FILE_MAP = [
        'sample-document.html' => 'test-context-mapping.html',
        'product-page.html'    => 'test-context-mapping-product-page.html',
    ];

    private static function loadDom(string $htmlFilename): DOMXPath
    {
        $html = file_get_contents(self::FIXTURES_DIR . $htmlFilename);

        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED);

        return new DOMXPath($dom);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function xpathTransUnitProvider(): array
    {
        $content = file_get_contents(self::FIXTURES_DIR . 'test-context-mapping.xlf');
        $xliff   = (new XliffParser())->xliffToArray($content);

        $cases = [];

        foreach ($xliff['files'] as $file) {
            $original    = $file['attr']['original'] ?? 'unknown';
            $htmlFile    = self::FILE_MAP[ $original ] ?? null;

            if ($htmlFile === null) {
                continue;
            }

            foreach ($file['trans-units'] as $transUnit) {
                $restype = $transUnit['attr']['restype'] ?? '';

                if ($restype !== 'x-path') {
                    continue;
                }

                $id         = $transUnit['attr']['id'];
                $xpath      = $transUnit['attr']['resname'];
                $sourceText = html_entity_decode($transUnit['source']['raw-content'], ENT_QUOTES | ENT_XML1, 'UTF-8');

                $cases[ "$id: $xpath" ] = [$htmlFile, $xpath, $sourceText];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('xpathTransUnitProvider')]
    public function testXPathResolvesToExpectedText(string $htmlFile, string $xpath, string $expectedText): void
    {
        $domXPath = self::loadDom($htmlFile);
        $nodeList = $domXPath->query($xpath);

        self::assertNotFalse($nodeList, "XPath expression '$xpath' is malformed");
        self::assertGreaterThan(0, $nodeList->length, "XPath '$xpath' matched 0 nodes in $htmlFile");
        self::assertSame(1, $nodeList->length, "XPath '$xpath' must match exactly 1 node in $htmlFile");

        $node  = $nodeList->item(0);
        $value = ($node instanceof \DOMAttr) ? $node->nodeValue : $node->textContent;

        self::assertSame($expectedText, $value, "XPath '$xpath' resolved text mismatch in $htmlFile");
    }

    #[Test]
    public function testAllXPathTransUnitsAreCoveredByProvider(): void
    {
        $content  = file_get_contents(self::FIXTURES_DIR . 'test-context-mapping.xlf');
        $xliff    = (new XliffParser())->xliffToArray($content);
        $provider = self::xpathTransUnitProvider();

        $xpathIds = [];

        foreach ($xliff['files'] as $file) {
            foreach ($file['trans-units'] as $transUnit) {
                if (($transUnit['attr']['restype'] ?? '') === 'x-path') {
                    $xpathIds[] = $transUnit['attr']['id'];
                }
            }
        }

        self::assertCount(
            count($xpathIds),
            $provider,
            'Provider must cover every x-path trans-unit: ' . implode(', ', $xpathIds)
        );
    }
}
