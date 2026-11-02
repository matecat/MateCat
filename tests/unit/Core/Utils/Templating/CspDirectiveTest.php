<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Templating;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Templating\CspDirective;

class CspDirectiveTest extends AbstractTest
{
    #[Test]
    public function eachDirectiveNamesTheTemplateVariableItIsRenderedFrom(): void
    {
        self::assertSame('x_plugin_csp_default_src', CspDirective::DEFAULT_SRC->templateVariable());
        self::assertSame('x_plugin_csp_script_src_elem', CspDirective::SCRIPT_SRC_ELEM->templateVariable());
        self::assertSame('x_plugin_csp_child_src', CspDirective::CHILD_SRC->templateVariable());
        self::assertSame('x_plugin_csp_font_src', CspDirective::FONT_SRC->templateVariable());
        self::assertSame('x_plugin_csp_style_src', CspDirective::STYLE_SRC->templateVariable());
    }
}
