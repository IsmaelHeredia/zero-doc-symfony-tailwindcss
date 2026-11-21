<?php

declare(strict_types=1);

namespace App\Tests\Shared\UI;

use App\Shared\Infrastructure\Assets\SriInjector;
use PHPUnit\Framework\TestCase;

final class AssetsIntegrityTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_templates_are_in_sync_with_the_built_assets(): void
    {
        $stale = (new SriInjector(self::ROOT . '/public', self::ROOT . '/templates'))->sync(false);

        self::assertSame([], $stale, 'Plantillas desactualizadas. Ejecuta: bash bin/dev assets');
    }

    public function test_every_asset_reference_carries_the_right_integrity_and_version(): void
    {
        foreach ($this->files(self::ROOT . '/templates', 'html') as $path => $html) {
            $references = preg_match_all('~(?:src|href)="/assets/(?!icon\.svg)~', $html);
            preg_match_all(
                '~<(?:script|link)\b[^>]*(?:src|href)="(/assets/[^"?]+)\?v=([a-f0-9]{12})"[^>]*\sintegrity="(sha384-[A-Za-z0-9+/=]+)"[^>]*>~',
                $html,
                $matches,
                PREG_SET_ORDER,
            );

            self::assertCount($references, $matches, "$path: hay assets sin integrity/versión");
            foreach ($matches as [, $asset, $version, $integrity]) {
                $bytes = (string) file_get_contents(self::ROOT . '/public' . $asset);
                self::assertSame('sha384-' . base64_encode(hash('sha384', $bytes, true)), $integrity, "$path: $asset");
                self::assertSame(substr(hash('sha256', $bytes), 0, 12), $version, "$path: $asset");
            }
        }
    }

    public function test_public_assets_contain_exactly_what_the_pages_reference(): void
    {
        $referenced = [];
        foreach ($this->files(self::ROOT . '/templates', 'html') as $html) {
            preg_match_all('~(?:src|href)="(/assets/[^"?]+)~', $html, $m);
            array_push($referenced, ...$m[1]);
        }
        $referenced = array_values(array_unique($referenced));
        sort($referenced);

        $present = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/public/assets', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $present[] = str_replace(self::ROOT . '/public', '', $file->getPathname());
            }
        }
        sort($present);

        self::assertSame($referenced, $present);
    }

    public function test_bundles_are_self_contained(): void
    {
        $count = 0;
        foreach ($this->files(self::ROOT . '/public/assets/dist', 'js') as $path => $js) {
            ++$count;
            self::assertSame(0, preg_match('~\bfrom\s*["\'](\.{1,2}/|/|https?:)~', $js), "$path: import externo al bundle");
            self::assertSame(0, preg_match('~\bimport\s*\(~', $js), "$path: import dinámico");
        }
        self::assertGreaterThanOrEqual(8, $count, 'Faltan bundles: ejecuta «bash bin/dev assets»');
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