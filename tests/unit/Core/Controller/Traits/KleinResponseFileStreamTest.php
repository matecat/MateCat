<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\Traits;

use Controller\Traits\KleinResponseFileStream;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;

class KleinResponseFileStreamTest extends AbstractTest
{
    private RecordingHeadersFileStream $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streamer = new RecordingHeadersFileStream(new Response());
    }

    #[Test]
    public function streamFileFromPointer_outputs_file_content(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, 'hello world');
        rewind($tmp);

        ob_start();
        $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'test.txt');
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
        $this->streamer->streamFileDownloadFromPointer($tmp, 'report.csv');
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
        $this->streamer->streamFileInlineFromPointer($tmp, 'doc.pdf', 'application/pdf');
        $output = ob_get_clean();

        $this->assertSame('inline content', $output);
    }

    #[Test]
    public function streamFileFromPointer_handles_empty_file(): void
    {
        $tmp = tmpfile();
        rewind($tmp);

        ob_start();
        $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'empty.txt');
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
        $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'inline', 'multi.txt');
        $output = ob_get_clean();

        $this->assertSame("line1\nline2\nline3", $output);
    }

    #[Test]
    public function streamFileFromPointer_sets_content_length(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, 'hello world');
        rewind($tmp);

        ob_start();
        $this->streamer->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'test.txt');
        ob_end_clean();

        $this->assertContains(['Content-Length: 11', true], $this->streamer->headers);
    }

    /**
     * The headers a download has always sent, in the same order: each replaces a header of the
     * same name already set, such as the cache headers of session_start(), so none is sent twice.
     */
    #[Test]
    public function streamFileFromPointer_sends_the_download_headers_replacing_the_previous_ones(): void
    {
        $tmp = tmpfile();
        fwrite($tmp, 'zip');
        rewind($tmp);

        ob_start();
        $before = time();
        $this->streamer->streamFileFromPointer($tmp, 'application/zip', 'attachment', 'a.zip');
        ob_end_clean();

        $lastModified = $this->streamer->headers[1][0] ?? '';
        $this->assertContains($lastModified, [
            'Last-Modified: ' . gmdate('D, d M Y H:i:s', $before) . ' GMT',
            'Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT',
        ]);
        $this->assertSame([
            ['Expires: Tue, 03 Jul 2001 06:00:00 GMT', true],
            [$lastModified, true],
            ['Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true],
            ['Cache-Control: post-check=0, pre-check=0', false],
            ['Pragma: no-cache', true],
            ['Content-Type: application/zip', true],
            ['Content-Disposition: attachment; filename="a.zip"', true],
            ['Expires: 0', true],
            ['Connection: close', true],
            ['Content-Length: 3', true],
        ], $this->streamer->headers);
    }

    #[Test]
    public function streamFileFromPointer_strips_unsafe_characters_from_the_filename(): void
    {
        $this->assertSame(
            'Content-Disposition: attachment; filename="report.csv"',
            $this->contentDispositionFor('report.csv'),
            'A plain filename is kept as it is.'
        );
        $this->assertSame(
            'Content-Disposition: attachment; filename="abc.txt"',
            $this->contentDispositionFor("a\"b\\c\r\n\0.txt")
        );
    }

    /**
     * The name is the project name a user typed, not a path: a slash in it is kept.
     */
    #[Test]
    public function streamFileFromPointer_keeps_a_slash_in_the_filename(): void
    {
        $this->assertSame(
            'Content-Disposition: attachment; filename="Client A/B 2026.zip"',
            $this->contentDispositionFor('Client A/B 2026.zip')
        );
    }

    private function contentDispositionFor(string $filename): ?string
    {
        $streamer = new RecordingHeadersFileStream(new Response());

        $tmp = tmpfile();
        fwrite($tmp, 'data');
        rewind($tmp);

        ob_start();
        $streamer->streamFileDownloadFromPointer($tmp, $filename);
        ob_end_clean();

        foreach ($streamer->headers as [$header]) {
            if (str_starts_with($header, 'Content-Disposition: ')) {
                return $header;
            }
        }

        return null;
    }

    /**
     * The headers go out with PHP's header(). Under PHPUnit the output has already started, so
     * header() only warns: the warning shows it was called.
     */
    #[Test]
    public function streamFileFromPointer_sends_the_headers_with_php_header(): void
    {
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_WARNING);

        $tmp = tmpfile();
        fwrite($tmp, 'hello world');
        rewind($tmp);

        ob_start();
        try {
            (new KleinResponseFileStream(new Response()))->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'test.txt');
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        $this->assertSame('hello world', $output);
        $this->assertCount(10, $warnings);
        $this->assertStringContainsString('Cannot modify header information', $warnings[0]);
    }

    /**
     * Response::send() calls fastcgi_finish_request(): under PHP-FPM the connection closes there and
     * the bytes written after it never reach the client. The body must go out before the request ends.
     */
    #[Test]
    public function streamFileFromPointer_sends_the_body_without_finishing_the_request(): void
    {
        $response = $this->getMockBuilder(Response::class)->onlyMethods(['send'])->getMock();
        $response->expects($this->never())->method('send');

        $tmp = tmpfile();
        fwrite($tmp, 'hello world');
        rewind($tmp);

        ob_start();
        try {
            (new RecordingHeadersFileStream($response))->streamFileFromPointer($tmp, 'text/plain', 'attachment', 'test.txt');
        } finally {
            $output = ob_get_clean();
        }

        $this->assertSame('hello world', $output);
        $this->assertTrue($response->isLocked());
    }
}

/**
 * Records the headers instead of sending them, each with the replace flag of header().
 */
class RecordingHeadersFileStream extends KleinResponseFileStream
{
    /** @var list<array{string, bool}> */
    public array $headers = [];

    protected function header(string $header, bool $replace = true): void
    {
        $this->headers[] = [$header, $replace];
    }
}
