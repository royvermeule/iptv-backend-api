<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Jobs\JobRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class JobsListCommand extends Command
{
    public function __construct(private readonly JobRegistry $registry)
    {
        parent::__construct('jobs:list');
    }

    protected function configure(): void
    {
        $this->setDescription('List all registered jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobs = $this->registry->all();

        if (empty($jobs)) {
            $output->writeln('<comment>No jobs registered.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Description']);

        foreach ($jobs as $job) {
            $table->addRow([$job->getName(), $job->getDescription()]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
