<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

trait SeedsRoles
{
    private function seedRoles(KernelInterface $kernel): void
    {
        $this->runConsole($kernel, 'app:identity:seed');
    }

    private function runConsole(KernelInterface $kernel, string $command, array $arguments = []): void
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $exitCode = $application->run(new ArrayInput(['command' => $command] + $arguments), new NullOutput());
        self::assertSame(0, $exitCode, sprintf('Falló "%s".', $command));
    }
}