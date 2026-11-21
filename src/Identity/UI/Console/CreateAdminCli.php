<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\Register\RegisterUserCommand;
use App\Identity\Domain\ValueObject\RoleName;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:identity:create-admin', description: 'Crea un usuario administrador (única vía de elevación: no existe ruta HTTP)')]
final class CreateAdminCli extends Command
{
    use HandleTrait;

    public function __construct(MessageBusInterface $commandBus)
    {
        parent::__construct();
        $this->messageBus = $commandBus;
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email del administrador');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $password = $io->askHidden('Contraseña (mín. 12 caracteres)');
        if (null === $password || $password !== $io->askHidden('Repite la contraseña')) {
            $io->error('Las contraseñas no coinciden.');

            return Command::FAILURE;
        }

        try {
            $id = $this->handle(new RegisterUserCommand((string) $input->getArgument('email'), $password, [RoleName::ADMIN]));
        } catch (\LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Administrador creado (id %s).', $id));

        return Command::SUCCESS;
    }
}