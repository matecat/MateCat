<?php

namespace unit\Utils;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\XliffReplacer\XliffReplacerCallback;

class XliffReplacerCallbackTest extends AbstractTest
{
    /**
     * @throws Exception
     */
    #[Test]
    public function testSegmentsWithTagG()
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'it-IT'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();
        $projectStructMock->method('getMetadataValue')->willReturn(false);

        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<g id="1">Hello</g>';
        $target = '<g id="3">Hola</g>';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-EN', 'es-ES', $jobStructMock);

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testSegmentsWithTagPh()
    {
        $jobStructMock = $this->getStubBuilder(JobStruct::class)
            ->setConstructorArgs([
                [
                    'id' => '1',
                    'password' => 'password',
                    'source' => 'en-US',
                    'target' => 'it-IT'
                ]
            ])->getStub();

        $projectStructMock = $this->getStubBuilder(ProjectStruct::class)->getStub();
        $projectStructMock->method('getMetadataValue')->willReturn(false);

        $jobStructMock->method('getProject')->willReturn($projectStructMock);

        $segment = '<ph id="1"/> Hello';
        $target = '<ph id="3"/> Hola';

        /** @noinspection PhpParamsInspection */
        $xliffReplacerCallback = new XliffReplacerCallback(new FeatureSet(), 'en-EN', 'es-ES', $jobStructMock);

        $this->assertTrue($xliffReplacerCallback->thereAreErrors(1, $segment, $target));
    }
}