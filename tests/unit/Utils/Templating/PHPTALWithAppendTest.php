<?php

namespace unit\Utils\Templating;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utils\Templating\PHPTALWithAppend;

#[CoversClass(PHPTALWithAppend::class)]
class PHPTALWithAppendTest extends TestCase
{
    private PHPTALWithAppend $tal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tal = new PHPTALWithAppend();
    }

    #[Test]
    public function appendCreatesArrayOnFirstCall(): void
    {
        $this->tal->append('footer_js', 'script1.js');

        $store = $this->getInternalStore();
        $this->assertSame(['script1.js'], $store['footer_js']);
    }

    #[Test]
    public function appendAccumulatesValues(): void
    {
        $this->tal->append('footer_js', 'script1.js');
        $this->tal->append('footer_js', 'script2.js');

        $store = $this->getInternalStore();
        $this->assertSame(['script1.js', 'script2.js'], $store['footer_js']);
    }

    #[Test]
    public function appendWorksWithMultipleKeys(): void
    {
        $this->tal->append('footer_js', 'a.js');
        $this->tal->append('css_resources', 'style.css');

        $store = $this->getInternalStore();
        $this->assertSame(['a.js'], $store['footer_js']);
        $this->assertSame(['style.css'], $store['css_resources']);
    }

    #[Test]
    public function appendDoesNotOverwritePreviousEntries(): void
    {
        $this->tal->append('footer_js', 'first.js');
        $this->tal->append('footer_js', 'second.js');
        $this->tal->append('footer_js', 'third.js');

        $store = $this->getInternalStore();
        $this->assertCount(3, $store['footer_js']);
        $this->assertSame('first.js', $store['footer_js'][0]);
        $this->assertSame('third.js', $store['footer_js'][2]);
    }

    #[Test]
    public function setViaMagicSetStoresInContext(): void
    {
        $this->tal->basepath = '/test';

        $reflector = new ReflectionClass($this->tal);
        $contextProp = $reflector->getProperty('_context');
        $context = $contextProp->getValue($this->tal);

        $this->assertSame('/test', $context->basepath);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function getInternalStore(): array
    {
        $reflector = new ReflectionClass($this->tal);
        $prop = $reflector->getProperty('internal_store');

        return $prop->getValue($this->tal);
    }
}
