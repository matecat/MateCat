<?php

namespace unit\Utils\Url;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;
use Utils\Url\CanonicalRoutes;

class CanonicalRoutesTest extends AbstractTest
{
    private ?string $originalHttpHost = null;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->originalHttpHost = AppConfig::$HTTPHOST;
        } catch (\Error) {
            $this->originalHttpHost = null;
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

        // friendlySlug keeps latin chars, so "project-" remains (not just "-")
        $this->assertStringStartsWith('https://example.org/analyze/', $url);
        $this->assertStringEndsWith('/101-jkl', $url);
        $this->assertStringNotContainsString('/analyze/-/', $url);
    }
}

