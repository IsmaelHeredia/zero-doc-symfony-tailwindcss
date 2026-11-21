<?php

declare(strict_types=1);

namespace App\Identity\UI\Console;

use App\Identity\Application\RevokeRole\RevokeRoleCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:identity:revoke-role', description: 'Quita un rol a un usuario (no permite quitar el último admin)')]
final class RevokeRoleCli extends Command
{
    use HandleTrait;

    public function __construct(MessageBusInterface $commandBus)
    {
        parent::__construct();
        $this->messageBus = $commandBus;
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)->addArgument('role', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->handle(new RevokeRoleCommand((string) $input->getArgument('email'), (string) $input->getArgument('role')));
        } catch (\LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Rol revocado.');

        return Command::SUCCESS;
    }
}