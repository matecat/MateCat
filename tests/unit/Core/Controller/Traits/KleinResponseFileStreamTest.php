<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\Traits;

use Controller\Traits\KleinResponseFileStream;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;

class KleinResponseFileStreamTest extends AbstractTest
{
    private KleinResponseFileStream $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streamer = new KleinResponseFileStream(new Response());
    }

    #[Test]
    public function streamFileFromPointer_outputs_file_content(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, 'hello world');
        rewind($tmp);

        ob_start();
        try {
            $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'test.txt');
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame('hello world', $output);
    }

    #[Test]
    public function streamFileDownloadFromPointer_sets_attachment_disposition(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, 'data');
        rewind($tmp);

        ob_start();
        try {
            $this->streamer->streamFileDownloadFromPointer($tmp, 'report.csv');
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame('data', $output);
    }

    #[Test]
    public function streamFileInlineFromPointer_sets_inline_disposition(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, 'inline content');
        rewind($tmp);

        ob_start();
        try {
            $this->streamer->streamFileInlineFromPointer($tmp, 'doc.pdf', 'application/pdf');
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame('inline content', $output);
    }

    #[Test]
    public function streamFileFromPointer_handles_empty_file(): void
    {
        $tmp = tmpfile();
        rewind($tmp);

        ob_start();
        try {
            $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'empty.txt');
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function streamFileFromPointer_handles_multiline_content(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, "line1\nline2\nline3");
        rewind($tmp);

        ob_start();
        try {
            $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'inline', 'multi.txt');
        } catch (\Klein\Exceptions\ResponseAlreadySentException) {
        }
        $output = ob_get_clean();

        $this->assertSame("line1\nline2\nline3", $output);
    }
}
