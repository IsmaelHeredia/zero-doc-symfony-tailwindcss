<?php

declare(strict_types=1);

namespace App\Shared\UI\Console;

use App\Shared\Infrastructure\Assets\SriInjector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:assets:sri', description: 'Calcula los hashes SRI de los assets compilados y los inyecta en las plantillas')]
final class AssetsSriCli extends Command
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('check', null, InputOption::VALUE_NONE, 'No escribe: falla si alguna plantilla está desactualizada');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $check = (bool) $input->getOption('check');

        try {
            $changed = (new SriInjector($this->projectDir.'/public', $this->projectDir.'/templates'))->sync(!$check);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($check) {
            if ([] !== $changed) {
                $io->error(array_merge(['Plantillas desactualizadas respecto a los assets compilados:'], $changed, ['Ejecuta: bash bin/dev assets']));

                return Command::FAILURE;
            }
            $io->success('Las plantillas coinciden con los ficheros compilados.');

            return Command::SUCCESS;
        }

        $io->success(sprintf('%d plantilla(s) actualizada(s).', count($changed)));

        return Command::SUCCESS;
    }
}