<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI;

use PHPUnit\Framework\TestCase;

final class FrontendHygieneTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    public function test_templates_have_nothing_a_strict_csp_would_block(): void
    {
        foreach ($this->files(self::ROOT.'/templates', 'html') as $path => $html) {
            self::assertSame(0, preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $html), "$path: script inline");
            self::assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "$path: manejador de eventos inline");
            self::assertSame(0, preg_match('/\sstyle\s*=/i', $html), "$path: estilo inline");
            self::assertSame(0, preg_match('/<style[\s>]/i', $html), "$path: <style> inline");
            self::assertSame(0, preg_match('#(?:src|href)="https?://#i', $html), "$path: recurso externo");
        }
    }

    public function test_every_referenced_script_and_stylesheet_exists(): void
    {
        foreach ($this->files(self::ROOT.'/templates', 'html') as $path => $html) {
            preg_match_all('/(?:src|href)="(\/assets\/[^"?]+)(?:\?v=[a-f0-9]+)?"/', $html, $matches);
            self::assertNotEmpty($matches[1], "$path no referencia ningún asset");
            foreach ($matches[1] as $asset) {
                self::assertFileExists(self::ROOT.'/public'.$asset, "$path referencia $asset, que no existe: ¿falta «bash bin/dev assets»?");
            }
        }
    }

    public function test_scripts_never_inject_html_or_evaluate_strings(): void
    {
        foreach ($this->files(self::ROOT.'/assets-src/js', 'js') as $path => $js) {
            self::assertSame(
                0,
                preg_match('/\.(innerHTML|outerHTML)\s*=|insertAdjacentHTML\s*\(|document\.write\s*\(|\beval\s*\(|new Function\s*\(/', $js),
                "$path: inyección de HTML o evaluación de strings",
            );
        }
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