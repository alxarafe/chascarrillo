<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class AdminResponsiveTablesTest extends TestCase
{
    /**
     * @dataProvider administrativeTableTemplates
     */
    public function testAdministrativeTablesKeepTheirResponsiveAccessibleContract(string $template): void
    {
        $contents = file_get_contents(__DIR__ . '/../../' . $template);

        self::assertNotFalse($contents);
        self::assertStringContainsString('table-responsive admin-table-scroll', $contents);
        self::assertStringContainsString('tabindex="0"', $contents);
        self::assertStringContainsString('aria-describedby=', $contents);
        self::assertStringContainsString('Desplaza horizontalmente para ver todas las columnas.', $contents);
        self::assertStringContainsString('aria-label=', $contents);
        self::assertStringContainsString('admin-table-actions', $contents);
        self::assertStringContainsString('scope="col"', $contents);
    }

    public function testSharedStylesPreventGlobalOverflowAndKeepActionsReachable(): void
    {
        $styles = file_get_contents(__DIR__ . '/../../templates/partial/head.blade.php');

        self::assertNotFalse($styles);
        self::assertStringContainsString('.admin-table-scroll {', $styles);
        self::assertStringContainsString('max-width: 100%;', $styles);
        self::assertStringContainsString('overflow-x: auto;', $styles);
        self::assertStringContainsString('min-width: 48rem;', $styles);
        self::assertStringContainsString('position: sticky;', $styles);
        self::assertStringContainsString('right: 0;', $styles);
        self::assertStringContainsString(':focus-visible', $styles);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function administrativeTableTemplates(): array
    {
        return [
            'posts' => ['templates/post/index.blade.php'],
            'pages' => ['templates/page_admin/index.blade.php'],
            'menu items' => ['templates/menu/edit.blade.php'],
        ];
    }
}
