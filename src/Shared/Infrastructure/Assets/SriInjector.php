<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Assets;

final class SriInjector
{
    private const TAG = '~<(script|link)\b([^>]*)>~i';

    public function __construct(private readonly string $publicDir, private readonly string $templatesDir)
    {
    }

    public function sync(bool $write): array
    {
        $changed = [];
        foreach ($this->templates() as $file) {
            $old = (string) file_get_contents($file);
            $new = (string) preg_replace_callback(self::TAG, fn(array $m): string => $this->rewrite($m), $old);

            if ($new !== $old) {
                $changed[] = $file;
                if ($write) {
                    file_put_contents($file, $new);
                }
            }
        }
        sort($changed);

        return $changed;
    }

    private function rewrite(array $m): string
    {
        [$full, $tag, $attrs] = $m;
        $tag = strtolower($tag);
        $attribute = 'script' === $tag ? 'src' : 'href';
        $isIcon = 'link' === $tag && 1 === preg_match('~\brel="icon"~', $attrs);

        if ('link' === $tag && !$isIcon && 1 !== preg_match('~\brel="stylesheet"~', $attrs)) {
            return $full;
        }
        if (1 !== preg_match('~\b' . $attribute . '="(/assets/[^"?]+)(?:\?v=[a-f0-9]+)?"~', $attrs, $asset)) {
            return $full;
        }

        $path = $asset[1];
        $bytes = !str_contains($path, '..') && is_file($this->publicDir . $path) ? file_get_contents($this->publicDir . $path) : false;
        if (false === $bytes) {
            throw new \RuntimeException(sprintf('El asset %s no existe: ejecuta «bash bin/dev assets».', $path));
        }

        $attrs = (string) preg_replace('~\s+integrity="[^"]*"~', '', $attrs);
        $attrs = (string) preg_replace(
            '~\b' . $attribute . '="[^"]*"~',
            $attribute . '="' . $path . '?v=' . substr(hash('sha256', $bytes), 0, 12) . '"',
            $attrs,
        );

        if ($isIcon) {
            return '<' . $tag . rtrim($attrs) . '>';
        }

        return '<' . $tag . rtrim($attrs) . ' integrity="sha384-' . base64_encode(hash('sha384', $bytes, true)) . '">';
    }

    private function templates(): iterable
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->templatesDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && 'html' === $file->getExtension()) {
                yield $file->getPathname();
            }
        }
    }
}