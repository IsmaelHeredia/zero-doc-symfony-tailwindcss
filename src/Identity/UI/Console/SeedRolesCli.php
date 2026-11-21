<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\SeedRoles\SeedDefaultRolesCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:identity:seed', description: 'Crea los roles y permisos por defecto (idempotente)')]
final class SeedRolesCli extends Command
{
    use HandleTrait;

    public function __construct(MessageBusInterface $commandBus)
    {
        parent::__construct();
        $this->messageBus = $commandBus;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->handle(new SeedDefaultRolesCommand());
        $output->writeln('Roles y permisos sincronizados.');

        return Command::SUCCESS;
    }
}