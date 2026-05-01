<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Hook;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\FilterEvent;
use Model\FeaturesBase\Hook\RunEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

class FeatureSetDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Plugins\Features\TestDispatchFilterPassThrough::$invoked = false;
        \Plugins\Features\TestDispatchFilterThrowsGeneric::$invoked = false;
        \Plugins\Features\TestDispatchFilterAfterGeneric::$invoked = false;
        \Plugins\Features\TestDispatchFilterAfterRethrow::$invoked = false;
        \Plugins\Features\TestDispatchRunHandler::$invoked = false;
        \Plugins\Features\TestDispatchFilterThrowsHandled::$throwable = null;
    }

    #[Test]
    public function dispatchFilterInvokesHookHandlersWithEventAndReturnsSameEventInstance(): void
    {
        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_pass_through'])
        ]);

        $event = new DispatchFilterEvent();

        $result = $featureSet->dispatchFilter($event);

        self::assertSame($event, $result);
        self::assertTrue(\Plugins\Features\TestDispatchFilterPassThrough::$invoked);
        self::assertSame(['test_dispatch_filter_pass_through'], $event->trace);
    }

    #[Test]
    public function dispatchFilterSwallowsGenericExceptionsAndContinues(): void
    {
        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_throws_generic']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_after_generic']),
        ]);

        $event = new DispatchFilterEvent();

        $result = $featureSet->dispatchFilter($event);

        self::assertSame($event, $result);
        self::assertTrue(\Plugins\Features\TestDispatchFilterThrowsGeneric::$invoked);
        self::assertTrue(\Plugins\Features\TestDispatchFilterAfterGeneric::$invoked);
        self::assertSame(['test_dispatch_filter_after_generic'], $event->trace);
    }

    #[Test]
    public function dispatchFilterRethrowsValidationError(): void
    {
        \Plugins\Features\TestDispatchFilterThrowsHandled::$throwable = new ValidationError('validation');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_throws_handled']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_after_rethrow']),
        ]);

        $this->expectException(ValidationError::class);
        $featureSet->dispatchFilter(new DispatchFilterEvent());
    }

    #[Test]
    public function dispatchFilterRethrowsNotFoundException(): void
    {
        \Plugins\Features\TestDispatchFilterThrowsHandled::$throwable = new NotFoundException('not found');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_throws_handled']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_after_rethrow']),
        ]);

        $this->expectException(NotFoundException::class);
        $featureSet->dispatchFilter(new DispatchFilterEvent());
    }

    #[Test]
    public function dispatchFilterRethrowsAuthenticationError(): void
    {
        \Plugins\Features\TestDispatchFilterThrowsHandled::$throwable = new AuthenticationError('auth');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_throws_handled']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_after_rethrow']),
        ]);

        $this->expectException(AuthenticationError::class);
        $featureSet->dispatchFilter(new DispatchFilterEvent());
    }

    #[Test]
    public function dispatchFilterRethrowsReQueueException(): void
    {
        \Plugins\Features\TestDispatchFilterThrowsHandled::$throwable = new ReQueueException('requeue');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_throws_handled']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_after_rethrow']),
        ]);

        $this->expectException(ReQueueException::class);
        $featureSet->dispatchFilter(new DispatchFilterEvent());
    }

    #[Test]
    public function dispatchFilterRethrowsEndQueueException(): void
    {
        \Plugins\Features\TestDispatchFilterThrowsHandled::$throwable = new EndQueueException('endqueue');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_throws_handled']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_filter_after_rethrow']),
        ]);

        $this->expectException(EndQueueException::class);
        $featureSet->dispatchFilter(new DispatchFilterEvent());
    }

    #[Test]
    public function dispatchRunInvokesOnlyFeaturesWithMatchingHookMethod(): void
    {
        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_run_handler']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_run_no_handler']),
        ]);

        $event = new DispatchRunEvent();

        $featureSet->dispatchRun($event);

        self::assertTrue(\Plugins\Features\TestDispatchRunHandler::$invoked);
        self::assertSame(['test_dispatch_run_handler'], $event->trace);
    }

    #[Test]
    public function dispatchRunSwallowsGenericExceptionAndContinues(): void
    {
        \Plugins\Features\TestDispatchRunThrowsGeneric::$invoked = false;
        \Plugins\Features\TestDispatchRunAfterGeneric::$invoked = false;

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_run_throws_generic']),
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_run_after_generic']),
        ]);

        $event = new DispatchRunEvent();
        $featureSet->dispatchRun($event);

        self::assertTrue(\Plugins\Features\TestDispatchRunThrowsGeneric::$invoked);
        self::assertTrue(\Plugins\Features\TestDispatchRunAfterGeneric::$invoked);
    }

    #[Test]
    public function dispatchRunRethrowsNotFoundException(): void
    {
        \Plugins\Features\TestDispatchRunThrowsHandled::$throwable = new NotFoundException('not found');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_run_throws_handled']),
        ]);

        $this->expectException(NotFoundException::class);
        $featureSet->dispatchRun(new DispatchRunEvent());
    }

    #[Test]
    public function dispatchRunRethrowsAuthenticationError(): void
    {
        \Plugins\Features\TestDispatchRunThrowsHandled::$throwable = new AuthenticationError('auth');

        $featureSet = new FeatureSet([
            new BasicFeatureStruct(['feature_code' => 'test_dispatch_run_throws_handled']),
        ]);

        $this->expectException(AuthenticationError::class);
        $featureSet->dispatchRun(new DispatchRunEvent());
    }
}

class DispatchFilterEvent extends FilterEvent
{
    public array $trace = [];

    public static function hookName(): string
    {
        return 'dispatchFilterHook';
    }
}

class DispatchRunEvent extends RunEvent
{
    public array $trace = [];

    public static function hookName(): string
    {
        return 'dispatchRunHook';
    }
}

namespace Plugins\Features;

use Exception;
use Model\FeaturesBase\Hook\FilterEvent;
use Model\FeaturesBase\Hook\RunEvent;

class TestDispatchFilterPassThrough extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_filter_pass_through';

    public static bool $invoked = false;

    public function dispatchFilterHook(FilterEvent $event): void
    {
        self::$invoked = true;
        if ($event instanceof \Tests\Unit\Features\Hook\DispatchFilterEvent) {
            $event->trace[] = self::FEATURE_CODE;
        }
    }
}

class TestDispatchFilterThrowsGeneric extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_filter_throws_generic';

    public static bool $invoked = false;

    public function dispatchFilterHook(FilterEvent $event): void
    {
        self::$invoked = true;
        throw new Exception('generic failure');
    }
}

class TestDispatchFilterAfterGeneric extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_filter_after_generic';

    public static bool $invoked = false;

    public function dispatchFilterHook(FilterEvent $event): void
    {
        self::$invoked = true;
        if ($event instanceof \Tests\Unit\Features\Hook\DispatchFilterEvent) {
            $event->trace[] = self::FEATURE_CODE;
        }
    }
}

class TestDispatchFilterThrowsHandled extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_filter_throws_handled';

    public static ?Exception $throwable = null;

    public function dispatchFilterHook(FilterEvent $event): void
    {
        throw self::$throwable ?? new Exception('missing throwable');
    }
}

class TestDispatchFilterAfterRethrow extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_filter_after_rethrow';

    public static bool $invoked = false;

    public function dispatchFilterHook(FilterEvent $event): void
    {
        self::$invoked = true;
        if ($event instanceof \Tests\Unit\Features\Hook\DispatchFilterEvent) {
            $event->trace[] = self::FEATURE_CODE;
        }
    }
}

class TestDispatchRunHandler extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_run_handler';

    public static bool $invoked = false;

    public function dispatchRunHook(RunEvent $event): void
    {
        self::$invoked = true;
        if ($event instanceof \Tests\Unit\Features\Hook\DispatchRunEvent) {
            $event->trace[] = self::FEATURE_CODE;
        }
    }
}

class TestDispatchRunNoHandler extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_run_no_handler';
}

class TestDispatchRunThrowsGeneric extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_run_throws_generic';

    public static bool $invoked = false;

    public function dispatchRunHook(RunEvent $event): void
    {
        self::$invoked = true;
        throw new Exception('generic run failure');
    }
}

class TestDispatchRunAfterGeneric extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_run_after_generic';

    public static bool $invoked = false;

    public function dispatchRunHook(RunEvent $event): void
    {
        self::$invoked = true;
        if ($event instanceof \Tests\Unit\Features\Hook\DispatchRunEvent) {
            $event->trace[] = self::FEATURE_CODE;
        }
    }
}

class TestDispatchRunThrowsHandled extends BaseFeature
{
    public const string FEATURE_CODE = 'test_dispatch_run_throws_handled';

    public static ?Exception $throwable = null;

    public function dispatchRunHook(RunEvent $event): void
    {
        throw self::$throwable ?? new Exception('missing throwable');
    }
}
