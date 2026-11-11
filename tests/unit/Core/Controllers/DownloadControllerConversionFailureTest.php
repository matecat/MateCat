<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\ExternalServiceException;
use Controller\API\V2\DownloadController;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Throwable;
use Utils\Registry\AppConfig;

/**
 * A file that Filters cannot convert back to its original format fails the whole download, with
 * an error the user sees, instead of a zip where the intermediate XLIFF takes the document's place.
 */
#[CoversClass(DownloadController::class)]
class DownloadControllerConversionFailureTest extends AbstractTest
{
    private DownloadController $controller;
    private ReflectionClass $reflector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflector  = new ReflectionClass(DownloadController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();
        $this->reflector->getProperty('id_job')->setValue($this->controller, 9029010);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function abortOnFailedConversionsDoesNothingWhenEveryConversionSucceeded(): void
    {
        $this->reflector->getProperty('downloadToken')->setValue($this->controller, 'token_ok');

        $this->reflector->getMethod('abortOnFailedConversions')->invoke(
            $this->controller,
            [10 => ['successful' => true, 'document_content' => 'converted']],
            [10 => ['output_filename' => 'file.docx']]
        );

        // the token is left for finalize() to report "Download complete."
        self::assertSame('token_ok', $this->reflector->getProperty('downloadToken')->getValue($this->controller));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function abortOnFailedConversionsFailsTheDownloadAndReleasesTheToken(): void
    {
        $this->reflector->getProperty('downloadToken')->setValue($this->controller, 'token_failed');

        try {
            $this->reflector->getMethod('abortOnFailedConversions')->invoke(
                $this->controller,
                [
                    10 => ['successful' => true, 'document_content' => 'converted'],
                    11 => ['successful' => false, 'errorMessage' => 'conversion exploded'],
                ],
                [
                    10 => ['output_filename' => 'file.docx'],
                    11 => ['output_filename' => '<b>file</b>.idml'],
                ]
            );
            self::fail('Expected ExternalServiceException');
        } catch (ExternalServiceException $e) {
            self::assertStringStartsWith('Download failed: a file could not be converted back', $e->getMessage());
            self::assertStringContainsString(AppConfig::$SUPPORT_MAIL, $e->getMessage());
            // the message is rendered as HTML by the client: user-supplied filenames stay out of it
            self::assertStringNotContainsString('<b>file</b>', $e->getMessage());
        }

        // unlockToken() consumed the token, so finalize() can never report a successful download
        self::assertNull($this->reflector->getProperty('downloadToken')->getValue($this->controller));
    }

    /**
     * A conversion that returns no document is a failure too, even without an error message.
     *
     * @throws Throwable
     */
    #[Test]
    public function abortOnFailedConversionsFailsTheDownloadWhenADocumentIsEmpty(): void
    {
        $this->expectException(ExternalServiceException::class);

        $this->reflector->getMethod('abortOnFailedConversions')->invoke(
            $this->controller,
            [10 => ['successful' => true, 'document_content' => '']],
            [10 => ['output_filename' => 'file.docx']]
        );
    }
}
