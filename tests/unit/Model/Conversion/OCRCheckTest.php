<?php

namespace unit\Model\Conversion;

use Model\Conversion\OCRCheck;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class OCRCheckTest extends AbstractTest
{
    private string $tmpDir;
    private bool $originalOcrCheck;

    public function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'matecat_ocr_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->originalOcrCheck = AppConfig::$FILTERS_OCR_CHECK;
    }

    public function tearDown(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = $this->originalOcrCheck;

        // Clean up temp files
        $files = glob($this->tmpDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $f) {
            is_file($f) && unlink($f);
        }
        @rmdir($this->tmpDir);

        parent::tearDown();
    }

    /**
     * Creates a minimal valid PNG file on disk.
     */
    private function createPngFile(string $name): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $name;
        // Minimal 1x1 transparent PNG
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        file_put_contents($path, $png);

        return $path;
    }

    /**
     * Creates a minimal valid PDF file on disk.
     */
    private function createPdfFile(string $name): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, '%PDF-1.4 minimal');

        return $path;
    }

    // ================================================
    // OCR Check disabled
    // ================================================

    #[Test]
    public function warningReturnsFalseWhenOcrCheckDisabled(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = false;
        $ocr = new OCRCheck('en-US');
        $pngPath = $this->createPngFile('test.png');

        $this->assertFalse($ocr->thereIsWarning($pngPath));
    }

    #[Test]
    public function errorReturnsFalseWhenOcrCheckDisabled(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = false;
        $ocr = new OCRCheck('qnt-Latn-XA');
        $pngPath = $this->createPngFile('test.png');

        $this->assertFalse($ocr->thereIsError($pngPath));
    }

    // ================================================
    // OCR Check enabled — warnings
    // ================================================

    #[Test]
    public function warningReturnsTrueForImageWithUnsupportedLang(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        // Use a language code NOT in ocrSupported list
        $ocr = new OCRCheck('xx-FAKE');
        $pngPath = $this->createPngFile('scan.png');

        $this->assertTrue($ocr->thereIsWarning($pngPath));
    }

    #[Test]
    public function warningReturnsFalseForImageWithSupportedLang(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        // en-US is in the ocrSupported list
        $ocr = new OCRCheck('en-US');
        $pngPath = $this->createPngFile('scan.png');

        $this->assertFalse($ocr->thereIsWarning($pngPath));
    }

    #[Test]
    public function warningReturnsFalseForNonImageFile(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        $ocr = new OCRCheck('xx-FAKE');
        $txtPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'doc.txt';
        file_put_contents($txtPath, 'plain text content');

        $this->assertFalse($ocr->thereIsWarning($txtPath));
    }

    // ================================================
    // OCR Check enabled — errors
    // ================================================

    #[Test]
    public function errorReturnsTrueForImageWithNotSupportedLang(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        // qnt-Latn-XA is in the ocrNotSupported list
        $ocr = new OCRCheck('qnt-Latn-XA');
        $pngPath = $this->createPngFile('scan.png');

        $this->assertTrue($ocr->thereIsError($pngPath));
    }

    #[Test]
    public function errorReturnsFalseForImageWithSupportedLang(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        $ocr = new OCRCheck('en-US');
        $pngPath = $this->createPngFile('scan.png');

        $this->assertFalse($ocr->thereIsError($pngPath));
    }

    #[Test]
    public function errorReturnsFalseForNonImageFile(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        $ocr = new OCRCheck('qnt-Latn-XA');
        $txtPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'doc.txt';
        file_put_contents($txtPath, 'plain text content');

        $this->assertFalse($ocr->thereIsError($txtPath));
    }

    #[Test]
    public function warningReturnsTrueForPdfWithUnsupportedLang(): void
    {
        AppConfig::$FILTERS_OCR_CHECK = true;
        $ocr = new OCRCheck('xx-FAKE');
        $pdfPath = $this->createPdfFile('doc.pdf');

        $this->assertTrue($ocr->thereIsWarning($pdfPath));
    }
}

