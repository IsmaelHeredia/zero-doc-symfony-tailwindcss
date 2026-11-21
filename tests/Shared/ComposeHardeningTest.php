<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ComposeHardeningTest extends TestCase
{
    public function test_every_published_port_is_bound_to_loopback_only(): void
    {
        $compose = Yaml::parseFile(__DIR__.'/../../compose.yaml');
        $published = 0;

        foreach ($compose['services'] as $name => $service) {
            foreach ($service['ports'] ?? [] as $port) {
                ++$published;
                self::assertIsString($port, "$name: usa la sintaxis corta «IP:host:contenedor»");
                self::assertStringStartsWith('127.0.0.1:', $port, "$name publica $port fuera de loopback");
            }
            self::assertArrayNotHasKey('network_mode', $service, "$name: network_mode saltaría el aislamiento");
            self::assertNotTrue($service['privileged'] ?? false, "$name: privileged");
            foreach ($service['volumes'] ?? [] as $volume) {
                self::assertStringNotContainsString('docker.sock', (string) $volume, "$name monta el socket de Docker");
            }
        }
        self::assertGreaterThan(0, $published);
    }
}