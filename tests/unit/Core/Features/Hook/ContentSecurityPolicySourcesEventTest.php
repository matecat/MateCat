<?php

declare(strict_types=1);

namespace Matecat\Core\Features\Hook;

use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\Hook\Event\Filter\ContentSecurityPolicySourcesEvent;
use PHPUnit\Framework\Attributes\Test;

class ContentSecurityPolicySourcesEventTest extends AbstractTest
{
    #[Test]
    public function sourcesAccumulatePerDirective(): void
    {
        $event = new ContentSecurityPolicySourcesEvent();
        $event->addSources('default-src', 'a.example.com');
        $event->addSources('default-src', 'b.example.com', 'wss:');
        $event->addSources('style-src', 'c.example.com');

        self::assertSame(
            ['default-src' => 'a.example.com b.example.com wss:', 'style-src' => 'c.example.com'],
            $event->getSources()
        );
    }

    #[Test]
    public function aSourceThatWouldEndItsDirectiveIsSkippedAndTheOthersAreKept(): void
    {
        $event = new ContentSecurityPolicySourcesEvent();
        $event->addSources(
            'default-src',
            'a.example.com',
            'b.example.com; script-src *',
            "'unsafe-inline'",
            "d.example.com\n",
            'x&#59script-src-elem&#32*',
            'c.example.com'
        );

        self::assertSame(['default-src' => 'a.example.com c.example.com'], $event->getSources());
    }
}
