<?php

namespace App\Command;

use App\Service\JobService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-jobs',
    description: 'Add a short description for your command',
)]
class ImportJobsCommand extends Command
{
    public function __construct(private JobService $jobService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDefinition(
            new InputDefinition([
                new InputOption('city', 'c', InputOption::VALUE_REQUIRED, 'Ville à importer (bordeaux, rennes, paris)'),
                new InputOption('date', 'd', InputOption::VALUE_REQUIRED, 'Date à importer (YYYY-mm-dd)'),
            ])
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $city = $input->getOption('city');
        $date = $input->getOption('date');

        if ($city) {
            $io->note(sprintf('You passed a city: %s', $city));
        }
        if ($date) {
            $io->note(sprintf('You passed a date: %s', $date));
        }

        $result = $this->jobService->importFranceTravailJobs($city, $date);

        if($result->getStatusCode() == 200) {
            $url = json_decode($result->getContent(), true)['url'];
            $io->success(sprintf('The log file is located at: %s', $url));
        } else {
            $io->error(sprintf('The import sent the message: %s', $result->getContent()));
        }

        return Command::SUCCESS;
    }
}
