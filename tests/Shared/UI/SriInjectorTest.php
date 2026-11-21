<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI;

use App\Shared\Infrastructure\Assets\SriInjector;
use PHPUnit\Framework\TestCase;

final class SriInjectorTest extends TestCase
{
    private string $dir;
    private string $template;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/sri-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/public/assets/dist', 0777, true);
        mkdir($this->dir . '/templates/app', 0777, true);
        file_put_contents($this->dir . '/public/assets/app.css', 'body{}');
        file_put_contents($this->dir . '/public/assets/dist/x.js', 'console.log(1)');
        $this->template = $this->dir . '/templates/app/x.html';
        file_put_contents($this->template, <<<'HTML'
<link rel="stylesheet" href="/assets/app.css">
<link rel="preload" href="/assets/app.css">
<script type="module" src="/assets/dist/x.js"></script>
<script src="https://example.test/y.js"></script>
HTML);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    public function test_it_adds_integrity_and_a_cache_buster_to_local_scripts_and_stylesheets_only(): void
    {
        self::assertSame([$this->template], $this->injector()->sync(true));

        $html = (string) file_get_contents($this->template);
        $css = 'sha384-' . base64_encode(hash('sha384', 'body{}', true));
        $js = 'sha384-' . base64_encode(hash('sha384', 'console.log(1)', true));
        self::assertStringContainsString('<link rel="stylesheet" href="/assets/app.css?v=' . substr(hash('sha256', 'body{}'), 0, 12) . '" integrity="' . $css . '">', $html);
        self::assertStringContainsString('<script type="module" src="/assets/dist/x.js?v=' . substr(hash('sha256', 'console.log(1)'), 0, 12) . '" integrity="' . $js . '"></script>', $html);
        self::assertStringContainsString('<link rel="preload" href="/assets/app.css">', $html);
        self::assertStringContainsString('<script src="https://example.test/y.js"></script>', $html);
    }

    public function test_it_is_idempotent(): void
    {
        $this->injector()->sync(true);
        $first = (string) file_get_contents($this->template);

        self::assertSame([], $this->injector()->sync(true));
        self::assertSame($first, (string) file_get_contents($this->template));
    }

    public function test_a_modified_asset_makes_the_template_stale_until_it_is_rewritten(): void
    {
        $this->injector()->sync(true);
        self::assertSame([], $this->injector()->sync(false));

        file_put_contents($this->dir . '/public/assets/dist/x.js', 'console.log("manipulado")');

        self::assertSame([$this->template], $this->injector()->sync(false));
        $this->injector()->sync(true);
        self::assertSame([], $this->injector()->sync(false));
    }

    public function test_a_missing_asset_is_an_error(): void
    {
        file_put_contents($this->template, '<script type="module" src="/assets/dist/no-existe.js"></script>');

        $this->expectException(\RuntimeException::class);
        $this->injector()->sync(false);
    }

    private function injector(): SriInjector
    {
        return new SriInjector($this->dir . '/public', $this->dir . '/templates');
    }
}