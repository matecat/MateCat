<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Utils\Email\AbstractEmail;

/**
 * An email can be rendered inside a web request, for example after a segment is saved. A template
 * that throws must not leave its output buffer open: the half-rendered email would be sent to the
 * browser in front of the request's own response.
 */
class AbstractEmailTest extends AbstractTest
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function templateFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'email-template-');
        $this->assertIsString($path);
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    private function emailWith(string $templatePath, string $layoutPath): AbstractEmail
    {
        return new class ($templatePath, $layoutPath) extends AbstractEmail {
            public function __construct(string $templatePath, string $layoutPath)
            {
                $this->_setTemplateByPath($templatePath);
                $this->_setLayoutByPath($layoutPath);
            }

            protected function _getTemplateVariables(): array
            {
                return ['name' => 'Giulia'];
            }

            public function send(): void
            {
            }
        };
    }

    /**
     * @param list<mixed> $arguments
     */
    private function invoke(AbstractEmail $email, string $method, array $arguments = []): mixed
    {
        return (new ReflectionMethod($email, $method))->invoke($email, ...$arguments);
    }

    /**
     * Calls a protected render method and returns how many output buffers it left open.
     *
     * @param list<mixed> $arguments
     */
    private function buffersLeftOpenBy(AbstractEmail $email, string $method, array $arguments): int
    {
        $level = ob_get_level();

        try {
            $this->invoke($email, $method, $arguments);
            $this->fail("$method did not throw");
        } catch (RuntimeException $e) {
            $this->assertSame('template failed', $e->getMessage());
        } finally {
            $leftOpen = ob_get_level() - $level;

            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        return $leftOpen;
    }

    #[Test]
    public function aTemplateThatThrowsLeavesNoBufferOpen(): void
    {
        $email = $this->emailWith(
            $this->templateFile('<p>Hi <?= $name ?></p><?php throw new \RuntimeException("template failed"); ?>'),
            $this->templateFile('<div><?= $messageBody ?></div>')
        );

        $this->assertSame(0, $this->buffersLeftOpenBy($email, '_buildMessageContent', []));
    }

    #[Test]
    public function aLayoutThatThrowsLeavesNoBufferOpen(): void
    {
        $email = $this->emailWith(
            $this->templateFile('<p>Hi <?= $name ?></p>'),
            $this->templateFile('<div><?= $messageBody ?></div><?php throw new \RuntimeException("template failed"); ?>')
        );

        $this->assertSame(0, $this->buffersLeftOpenBy($email, '_buildHTMLMessage', ['<p>body</p>']));
    }

    #[Test]
    public function aTemplateThatOpensItsOwnBufferAndThrowsLeavesNoBufferOpen(): void
    {
        $email = $this->emailWith(
            $this->templateFile('<p>Hi</p><?php ob_start(); echo "inner"; throw new \RuntimeException("template failed"); ?>'),
            $this->templateFile('<div><?= $messageBody ?></div>')
        );

        $this->assertSame(0, $this->buffersLeftOpenBy($email, '_buildMessageContent', []));
    }

    #[Test]
    public function aTemplateThatRendersReturnsItsOutput(): void
    {
        $email = $this->emailWith(
            $this->templateFile('<p>Hi <?= $name ?></p>'),
            $this->templateFile('<div><?= $messageBody ?></div>')
        );
        $level = ob_get_level();

        $content = $this->invoke($email, '_buildMessageContent');
        $html = $this->invoke($email, '_buildHTMLMessage');

        $this->assertSame('<p>Hi Giulia</p>', $content);
        $this->assertSame('<div><p>Hi Giulia</p></div>', $html);
        $this->assertSame($level, ob_get_level());
    }
}
