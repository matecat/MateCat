<?php

declare(strict_types=1);

namespace Matecat\Core\Features\Hook;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\Hook\Event\Filter\ContentSecurityPolicySourcesEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Templating\CspDirective;

class ContentSecurityPolicySourcesEventTest extends AbstractTest
{
    #[Test]
    public function itStartsEmptyForEveryDirective(): void
    {
        $event = new ContentSecurityPolicySourcesEvent('context_preview.html');

        self::assertSame('context_preview.html', $event->getTemplateName());
        foreach (CspDirective::cases() as $directive) {
            self::assertSame([], $event->getSources($directive));
        }
    }

    #[Test]
    public function itAccumulatesSourcesPerDirectiveAcrossPlugins(): void
    {
        $event = new ContentSecurityPolicySourcesEvent('cattool.html');

        $event->addSources(CspDirective::DEFAULT_SRC, 'a0.muscache.com');
        $event->addSources(CspDirective::DEFAULT_SRC, 'cdn.example.com', 'img.example.com');
        $event->addSources(CspDirective::FONT_SRC, 'fonts.example.com');

        self::assertSame(['a0.muscache.com', 'cdn.example.com', 'img.example.com'], $event->getSources(CspDirective::DEFAULT_SRC));
        self::assertSame(['fonts.example.com'], $event->getSources(CspDirective::FONT_SRC));
        self::assertSame([], $event->getSources(CspDirective::STYLE_SRC));
    }

    #[Test]
    public function itDropsDuplicatesWithinADirective(): void
    {
        $event = new ContentSecurityPolicySourcesEvent('cattool.html');

        $event->addSources(CspDirective::DEFAULT_SRC, 'a0.muscache.com', 'a0.muscache.com');
        $event->addSources(CspDirective::DEFAULT_SRC, 'a0.muscache.com');
        $event->addSources(CspDirective::STYLE_SRC, 'a0.muscache.com');

        self::assertSame(['a0.muscache.com'], $event->getSources(CspDirective::DEFAULT_SRC));
        self::assertSame(['a0.muscache.com'], $event->getSources(CspDirective::STYLE_SRC));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validSources(): array
    {
        return [
            'host'              => ['a0.muscache.com'],
            'wildcard subdomain' => ['*.translated.com'],
            'scheme and host'   => ['https://cdn.example.com'],
            'host and port'     => ['cdn.example.com:443'],
            'wildcard port'     => ['cdn.example.com:*'],
            'host and path'     => ['cdn.example.com/screenshots/'],
            'websocket scheme'  => ['wss://socket.example.com'],
            'bare scheme'       => ['data:'],
            'single label'      => ['localhost'],
        ];
    }

    #[Test]
    #[DataProvider('validSources')]
    public function itAcceptsWellFormedSources(string $source): void
    {
        $event = new ContentSecurityPolicySourcesEvent('cattool.html');

        $event->addSources(CspDirective::DEFAULT_SRC, $source);

        self::assertSame([$source], $event->getSources(CspDirective::DEFAULT_SRC));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSources(): array
    {
        return [
            'directive injection' => ['cdn.example.com; script-src *'],
            'trailing semicolon'  => ['cdn.example.com;'],
            'two sources at once' => ['a.example.com b.example.com'],
            'comma'               => ['a.example.com,b.example.com'],
            'quoted keyword'      => ["'unsafe-inline'"],
            'double quote'        => ['cdn.example.com"'],
            'empty'               => [''],
            'newline'             => ["cdn.example.com\nscript-src *"],
            'html'                => ['<script>'],
        ];
    }

    #[Test]
    #[DataProvider('invalidSources')]
    public function itRejectsSourcesThatCouldBreakOutOfTheDirective(string $source): void
    {
        $event = new ContentSecurityPolicySourcesEvent('cattool.html');

        $this->expectException(InvalidArgumentException::class);

        $event->addSources(CspDirective::DEFAULT_SRC, $source);
    }

    #[Test]
    public function aRejectedSourceLeavesTheEarlierOnesInTheSameCallInPlace(): void
    {
        $event = new ContentSecurityPolicySourcesEvent('cattool.html');

        try {
            $event->addSources(CspDirective::DEFAULT_SRC, 'ok.example.com', 'bad;');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            self::assertSame(['ok.example.com'], $event->getSources(CspDirective::DEFAULT_SRC));
        }
    }
}
