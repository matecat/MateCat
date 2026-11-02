<?php

namespace Matecat\Core\Utils\Tools;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Tools\ContentDisposition;

class ContentDispositionTest extends AbstractTest
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function headerProvider(): array
    {
        return [
            'plain ascii is unchanged from the old header' => ['report.docx', 'attachment; filename="report.docx"'],
            'ascii with spaces and parentheses' => ['my report (1).docx', 'attachment; filename="my report (1).docx"'],
            'cyrillic' => ['спасибо.txt', "attachment; filename=\"_______.txt\"; filename*=UTF-8''%D1%81%D0%BF%D0%B0%D1%81%D0%B8%D0%B1%D0%BE.txt"],
            'cjk' => ['報告.xlsx', "attachment; filename=\"__.xlsx\"; filename*=UTF-8''%E5%A0%B1%E5%91%8A.xlsx"],
            'arabic' => ['تقرير.docx', "attachment; filename=\"_____.docx\"; filename*=UTF-8''" . rawurlencode('تقرير.docx')],
            'emoji is one character' => ['🎉.txt', "attachment; filename=\"_.txt\"; filename*=UTF-8''%F0%9F%8E%89.txt"],
            'percent is replaced in the ascii copy' => ['100%.txt', "attachment; filename=\"100_.txt\"; filename*=UTF-8''100%25.txt"],
            'semicolon is replaced in the ascii copy' => ['a;b.txt', "attachment; filename=\"a_b.txt\"; filename*=UTF-8''a%3Bb.txt"],
            'header-breaking characters are stripped' => ["evil\".docx\r\nX-Injected: yes", 'attachment; filename="evil.docxX-Injected: yes"'],
            'backslash and nul are stripped' => ["a\\b\0.txt", 'attachment; filename="ab.txt"'],
            'invalid utf-8 is replaced byte by byte' => ["bad\xC3.txt", "attachment; filename=\"bad_.txt\"; filename*=UTF-8''bad%C3.txt"],
        ];
    }

    #[Test]
    #[DataProvider('headerProvider')]
    public function buildsTheHeaderValue(string $filename, string $expected): void
    {
        $this->assertSame($expected, ContentDisposition::build($filename));
    }

    #[Test]
    public function buildsAnInlineHeaderValue(): void
    {
        $this->assertSame(
            "inline; filename=\"___.pdf\"; filename*=UTF-8''%D0%B4%D0%BE%D0%BA.pdf",
            ContentDisposition::build('док.pdf', 'inline')
        );
    }

    #[Test]
    public function sanitizeRemovesOnlyTheHeaderBreakingCharacters(): void
    {
        $this->assertSame('спасибо; 100%.txt', ContentDisposition::sanitize("спа\"сибо;\r\n 100%\\.txt\0"));
    }
}
