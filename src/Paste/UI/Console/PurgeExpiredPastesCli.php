<?php

declare(strict_types=1);

namespace App\Paste\UI\Console;

use App\Paste\Application\Purge\DeleteExpiredPasteCommand;
use App\Paste\Application\Purge\ExpiredPasteFinder;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:paste:purge-expired', description: 'Destruye físicamente los pastes caducados (una transacción y una fila de auditoría por paste)')]
final class PurgeExpiredPastesCli extends Command
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly ExpiredPasteFinder $finder,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
        $this->messageBus = $commandBus;
    }

    protected function configure(): void
    {
        $this->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Pastes por tanda', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batch = max(1, (int) $input->getOption('batch'));
        $purged = 0;
        $failed = [];

        do {
            $ids = array_values(array_diff(
                $this->finder->findExpiredIds($this->clock->now(), $batch + count($failed)),
                array_keys($failed),
            ));

            $progress = 0;
            foreach ($ids as $id) {
                try {
                    if (true === $this->handle(new DeleteExpiredPasteCommand($id))) {
                        ++$progress;
                    }
                } catch (\Throwable $e) {
                    $failed[$id] = true;
                    $io->warning($e->getMessage());
                }
            }
            $purged += $progress;
        } while ($progress > 0);

        $io->success(sprintf('%d paste(s) destruido(s).', $purged));
        if ([] !== $failed) {
            $io->error(sprintf('%d paste(s) fallaron; se reintentarán en la próxima ejecución.', count($failed)));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}