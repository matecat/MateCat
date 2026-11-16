<?php

namespace Matecat\Core\Utils\Url;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;
use Utils\Url\CanonicalRoutes;

class CanonicalRoutesTest extends AbstractTest
{
    private ?string $originalHttpHost = null;

    protected function setUp(): void
    {
        parent::setUp();
        // $HTTPHOST is a typed static property with no default, so it may be
        // uninitialized. isset() returns false (without throwing) in that case.
        if (isset(AppConfig::$HTTPHOST)) {
            $this->originalHttpHost = AppConfig::$HTTPHOST;
        }
        AppConfig::$HTTPHOST = 'https://example.org';
    }

    protected function tearDown(): void
    {
        if ($this->originalHttpHost !== null) {
            AppConfig::$HTTPHOST = $this->originalHttpHost;
        }
        parent::tearDown();
    }

    #[Test]
    public function analyzeWithLatinProjectName(): void
    {
        $url = CanonicalRoutes::analyze([
            'project_name' => 'My Project',
            'id_project' => 123,
            'password' => 'abc',
        ]);

        $this->assertEquals('https://example.org/analyze/my-project/123-abc', $url);
    }

    #[Test]
    public function analyzeWithCyrillicProjectName(): void
    {
        $url = CanonicalRoutes::analyze([
            'project_name' => 'Проф',
            'id_project' => 456,
            'password' => 'def',
        ]);

        $this->assertEquals('https://example.org/analyze/' . rawurlencode('Проф') . '/456-def', $url);
    }

    #[Test]
    public function analyzeWithChineseProjectName(): void
    {
        $url = CanonicalRoutes::analyze([
            'project_name' => '项目',
            'id_project' => 789,
            'password' => 'ghi',
        ]);

        $this->assertEquals('https://example.org/analyze/' . rawurlencode('项目') . '/789-ghi', $url);
    }

    #[Test]
    public function analyzeWithMixedLatinAndCyrillicProjectName(): void
    {
        $url = CanonicalRoutes::analyze([
            'project_name' => 'Project Проф',
            'id_project' => 101,
            'password' => 'jkl',
        ]);

        // Latin chars are slugified; non-Latin (Cyrillic) chars are percent-encoded
        // per codepoint by friendlySlug(). PHP's strtolower() is ASCII-only, so 'П'
        // is not lowercased and is encoded as uppercase '%D0%9F' (not lowercase '%D0%BF').
        $expectedSlug = 'project-' . rawurlencode('Проф');
        $this->assertEquals('https://example.org/analyze/' . $expectedSlug . '/101-jkl', $url);
    }

    #[Test]
    public function translateWritesTheProjectNameAsASlug(): void
    {
        $url = CanonicalRoutes::translate('My Project', 123, 'abcdef123456', 'en-US', 'it-IT');

        $this->assertEquals('https://example.org/translate/my-project/en-US-it-IT/123-abcdef123456', $url);
    }

    #[Test]
    public function reviseWritesTheProjectNameAsASlug(): void
    {
        $url = CanonicalRoutes::revise('My Project', 123, 'revpwd', 'en-US', 'it-IT');

        $this->assertEquals('https://example.org/revise/my-project/en-US-it-IT/123-revpwd', $url);
    }

    #[Test]
    public function aProjectNameCannotClimbOutOfTheTranslatePath(): void
    {
        $url = CanonicalRoutes::translate('../../gdrive/delete/all?', 123, 'abcdef123456', 'en-US', 'it-IT');

        $this->assertEquals('https://example.org/translate/gdrivedeleteall/en-US-it-IT/123-abcdef123456', $url);
    }

    #[Test]
    public function aProjectNameCannotClimbOutOfTheRevisePath(): void
    {
        $url = CanonicalRoutes::revise('../../gdrive/delete/all?', 123, 'revpwd', 'en-US', 'it-IT', ['revision_number' => 2, 'id_segment' => 42]);

        $this->assertEquals('https://example.org/revise2/gdrivedeleteall/en-US-it-IT/123-revpwd#42', $url);
    }

    #[Test]
    public function aProjectNameThatIsADotSegmentBecomesADash(): void
    {
        $translateUrl = CanonicalRoutes::translate('..', 123, 'abcdef123456', 'en-US', 'it-IT');
        $this->assertEquals('https://example.org/translate/-/en-US-it-IT/123-abcdef123456', $translateUrl);

        $reviseUrl = CanonicalRoutes::revise('.', 123, 'revpwd', 'en-US', 'it-IT');
        $this->assertEquals('https://example.org/revise/-/en-US-it-IT/123-revpwd', $reviseUrl);

        $analyzeUrl = CanonicalRoutes::analyze(['project_name' => '..', 'id_project' => 123, 'password' => 'abc']);
        $this->assertEquals('https://example.org/analyze/-/123-abc', $analyzeUrl);
    }
}

