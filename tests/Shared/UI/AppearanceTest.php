<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI;

use PHPUnit\Framework\TestCase;

final class AppearanceTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    private const HOOKS = ['open', 'copy'];

    public function test_every_page_loads_the_compiled_css_and_has_the_theme_toggle(): void
    {
        foreach ($this->files(self::ROOT.'/templates', 'html') as $path => $html) {
            self::assertMatchesRegularExpression('~<link rel="stylesheet" href="/assets/app\.css\?v=[a-f0-9]{12}" integrity="sha384-[A-Za-z0-9+/=]+">~', $html, $path);
            self::assertStringNotContainsString('style.css', $html, $path);
            self::assertMatchesRegularExpression('~<script src="/assets/theme\.js\?v=[a-f0-9]{12}" integrity="sha384-[A-Za-z0-9+/=]+"></script>~', $html, "$path: falta theme.js (con integrity) en <head>");
            self::assertStringContainsString('id="theme-slot"', $html, "$path: falta el hueco del botón de tema");
            self::assertLessThan(
                (int) strpos($html, '</head>'),
                (int) strpos($html, '/assets/theme.js'),
                "$path: theme.js debe cargarse en <head> (antes del primer pintado)",
            );
        }
        self::assertFileDoesNotExist(self::ROOT.'/public/assets/style.css');
    }

    public function test_every_class_used_by_templates_and_scripts_exists_in_the_compiled_css(): void
    {
        $css = (string) file_get_contents(self::ROOT.'/public/assets/app.css');
        self::assertNotSame('', $css, 'public/assets/app.css está vacío: ejecuta composer css:build');

        $used = [];
        foreach ($this->files(self::ROOT.'/assets-src/js', 'js') as $path => $html) {
            preg_match_all('/\sclass="([^"]*)"/', $html, $matches);
            foreach ($matches[1] as $list) {
                foreach (preg_split('/\s+/', trim($list), -1, PREG_SPLIT_NO_EMPTY) as $class) {
                    $used[$class] = $path;
                }
            }
        }
        foreach ($this->files(self::ROOT.'/public/assets', 'js') as $path => $js) {
            preg_match_all("/class:\s*'([^']+)'/", $js, $matches);
            foreach ($matches[1] as $list) {
                foreach (preg_split('/\s+/', trim($list), -1, PREG_SPLIT_NO_EMPTY) as $class) {
                    $used[$class] = $path;
                }
            }
        }

        foreach ($used as $class => $path) {
            if (\in_array($class, self::HOOKS, true)) {
                continue;
            }
            $selector = '.'.preg_replace('/([^A-Za-z0-9_-])/', '\\\\$1', $class);
            self::assertSame(
                1,
                preg_match('~'.preg_quote($selector, '~').'(?![A-Za-z0-9_-])~', $css),
                "La clase «$class» ($path) no está en app.css: ¿falta composer css:build o hay un typo?",
            );
        }
    }

    public function test_the_compiled_css_is_self_contained_and_csp_friendly(): void
    {
        $css = (string) file_get_contents(self::ROOT.'/public/assets/app.css');

        self::assertSame(0, preg_match('~url\(\s*[\'"]?(https?:)?//~i', $css), 'El CSS no debe cargar recursos externos');
        self::assertSame(0, preg_match('~@import\s+(url\()?[\'"]?https?:~i', $css), 'Sin @import remotos');
        self::assertStringContainsString('--brand', $css);
    }

    public function test_light_and_dark_define_exactly_the_same_tokens(): void
    {
        $source = (string) file_get_contents(self::ROOT.'/assets-src/theme.css');
        self::assertSame(1, preg_match('/^:root\s*\{(.*?)^\}/ms', $source, $light));
        self::assertSame(1, preg_match('/^\.dark\s*\{(.*?)^\}/ms', $source, $dark));

        $tokens = static function (string $block): array {
            preg_match_all('/(--[a-z0-9-]+)\s*:/', $block, $m);
            $names = array_unique($m[1]);
            sort($names);

            return $names;
        };

        self::assertSame($tokens($light[1]), $tokens($dark[1]));
        self::assertNotEmpty($tokens($light[1]));
    }

    public function test_every_palette_offered_by_theme_js_exists_in_the_stylesheet(): void
    {
        $js = (string) file_get_contents(self::ROOT.'/assets-src/js/theme.js');
        $source = (string) file_get_contents(self::ROOT.'/assets-src/theme.css');

        self::assertSame(1, preg_match('/PALETTES\s*=\s*\[([^\]]+)\]/', $js, $list));
        preg_match_all("/'([a-z]+)'/", $list[1], $names);
        self::assertNotEmpty($names[1]);

        foreach (array_diff($names[1], ['indigo']) as $palette) {
            self::assertStringContainsString('[data-palette="'.$palette.'"]', $source, "Paleta «$palette» sin definir");
            self::assertStringContainsString('[data-palette="'.$palette.'"].dark', $source, "Paleta «$palette» sin variante oscura");
        }
    }

    public function test_the_theme_preference_cannot_be_wiped_by_forgetting_link_keys(): void
    {
        $js = (string) file_get_contents(self::ROOT.'/assets-src/js/theme.js');
        self::assertSame(1, preg_match("/THEME_KEY\s*=\s*'([^']+)'/", $js, $key));

        self::assertStringStartsNotWith('zk:links:', $key[1]);
    }

    private function files(string $dir, string $extension): iterable
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $extension === $file->getExtension()) {
                yield $file->getPathname() => (string) file_get_contents($file->getPathname());
            }
        }
    }
}