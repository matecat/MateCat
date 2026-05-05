<?php

namespace unit\Controllers;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Regression guard: ensures no direct superglobal access ($_POST, $_GET, $_REQUEST)
 * remains in controller files. All request data must go through Klein's request abstraction
 * ($this->request->paramsPost(), $this->request->paramsGet(), $this->params, etc.).
 */
class SuperglobalEliminationTest extends AbstractTest
{
    private const CONTROLLER_DIR = __DIR__ . '/../../../lib/Controller';

    private const FORBIDDEN_SUPERGLOBALS = ['$_POST', '$_GET', '$_REQUEST'];

    #[Test]
    public function controllersDoNotAccessSuperglobalsDirectly(): void
    {
        $controllerDir = realpath(self::CONTROLLER_DIR);
        self::assertNotFalse($controllerDir, 'Controller directory not found: ' . self::CONTROLLER_DIR);

        $violations = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($controllerDir)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            $lines = file($filePath, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $lineNumber => $line) {
                $trimmed = ltrim($line);

                // skip single-line comments and block comment lines
                if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                foreach (self::FORBIDDEN_SUPERGLOBALS as $superglobal) {
                    if (str_contains($line, $superglobal)) {
                        $relativePath = str_replace($controllerDir . '/', '', $filePath);
                        $violations[] = sprintf(
                            '%s:%d — %s found: %s',
                            $relativePath,
                            $lineNumber + 1,
                            $superglobal,
                            trim($line)
                        );
                    }
                }
            }
        }

        self::assertEmpty(
            $violations,
            "Direct superglobal access found in controller files. Use Klein request abstractions instead:\n"
            . "  - \$_POST → \$this->request->paramsPost()->all() or \$this->params\n"
            . "  - \$_GET → \$this->request->paramsGet()->all()\n"
            . "  - \$_REQUEST → \$this->request->params() or \$this->params\n\n"
            . "Violations:\n" . implode("\n", $violations)
        );
    }
}
